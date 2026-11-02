<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CashRegister;
use App\Models\Sale;
use App\Models\Payment;
use App\Models\Returns;
use App\Models\Expense;
use App\Services\JournalService;
use Auth;

class CashRegisterController extends Controller
{
    private function postApprovedSalesJournal($cash_register_data)
    {
        JournalService::reverseForReference('cash_register_deposit', $cash_register_data->id);
        JournalService::reverseForReference('cash_register_sale', $cash_register_data->id);

        $sales = Sale::where([
            ['cash_register_id', $cash_register_data->id],
            ['sale_status', 1],
        ])->get();

        if($sales->isEmpty())
            return;

        $saleIds = $sales->pluck('id')->toArray();
        foreach($saleIds as $saleId) {
            JournalService::reverseForReference('sale', $saleId);
            JournalService::reverseForReference('sale_cogs', $saleId);
        }
        Payment::where('cash_register_id', $cash_register_data->id)
            ->whereIn('sale_id', $saleIds)
            ->get()
            ->each(function($payment) {
                JournalService::reverseForReference('sale_payment', $payment->id);
            });

        $totalSales = round((float)$sales->sum('grand_total'), 2);
        $paidSales = round((float)Payment::where('cash_register_id', $cash_register_data->id)
            ->whereIn('sale_id', $saleIds)
            ->where('paying_method', '!=', 'Kredit / Piutang')
            ->sum('amount'), 2);
        $creditSales = round(max($totalSales - $paidSales, 0), 2);
        $totalSaleReturn = round((float)Returns::where('cash_register_id', $cash_register_data->id)->sum('grand_total'), 2);
        $totalExpense = round((float)Expense::where('cash_register_id', $cash_register_data->id)->sum('amount'), 2);
        $duePayment = round((float)Payment::join('sales', 'payments.sale_id', '=', 'sales.id')
            ->where('payments.cash_register_id', $cash_register_data->id)
            ->where('payments.paying_method', 'Cash')
            ->where(function ($query) use ($cash_register_data) {
                $query->whereNull('sales.cash_register_id')
                    ->orWhere('sales.cash_register_id', '!=', $cash_register_data->id);
            })
            ->sum('payments.amount'), 2);
        $actualCashSales = round(max(
            (float)$cash_register_data->deposit_amount
            - (float)$cash_register_data->cash_in_hand
            + $totalSaleReturn
            + $totalExpense
            - $duePayment,
            0
        ), 2);

        $cogsAmount = round(array_reduce($saleIds, function($carry, $saleId) {
            return $carry + (float)JournalService::saleCogsAmount($saleId);
        }, 0), 2);

        $warehouseId = $cash_register_data->warehouse_id;
        $lines = [];
        if($actualCashSales > 0) {
            $lines[] = [
                'chart_of_account_id' => JournalService::cashierCashAccountId($cash_register_data->user_id),
                'debit' => $actualCashSales,
                'credit' => 0,
                'note' => 'Uang fisik penjualan kasir ' . ($cash_register_data->user->name ?? ('#' . $cash_register_data->user_id)) . ' register #' . $cash_register_data->id,
            ];
        }
        if($creditSales > 0) {
            $lines[] = [
                'chart_of_account_id' => JournalService::receivableAccountIdForWarehouse($warehouseId),
                'debit' => $creditSales,
                'credit' => 0,
                'note' => 'Penjualan kredit/piutang register #' . $cash_register_data->id,
            ];
        }
        if($totalSales > 0) {
            $lines[] = [
                'chart_of_account_id' => JournalService::salesRevenueAccountIdForWarehouse($warehouseId),
                'debit' => 0,
                'credit' => $totalSales,
                'note' => 'Penjualan register #' . $cash_register_data->id,
            ];
        }

        // Selisih kasir dihitung sebagai SISA, bukan rumus terpisah.
        // Rumus terpisah (actualCashSales - paidSales) hanya kebetulan seimbang
        // selama tidak ada pembulatan / clamp max(...,0); begitu ada selisih
        // kasir, jurnalnya timpang dan approve gagal. Dengan residual, jurnal
        // dijamin balance berapa pun angkanya.
        $cashDifference = round(
            array_sum(array_column($lines, 'credit')) - array_sum(array_column($lines, 'debit')),
            2
        );
        if(abs($cashDifference) >= 0.01) {
            $lines[] = [
                'chart_of_account_id' => JournalService::cashierDifferenceAccountIdForWarehouse($warehouseId),
                'debit'  => $cashDifference > 0 ? $cashDifference : 0,
                'credit' => $cashDifference < 0 ? abs($cashDifference) : 0,
                'note'   => ($cashDifference > 0 ? 'Selisih kasir kurang' : 'Selisih kasir lebih')
                            . ' register #' . $cash_register_data->id,
            ];
        }

        if($cogsAmount > 0) {
            $lines[] = [
                // ensureAccountByCode: kalau akun HPP belum ada, accountIdByCode
                // mengembalikan null dan baris ini hilang -> jurnal timpang.
                'chart_of_account_id' => JournalService::ensureAccountByCode(
                    JournalService::HPP, 'Harga Pokok Penjualan (HPP)', 'expense', 'debit'
                ),
                'debit' => $cogsAmount,
                'credit' => 0,
                'note' => 'HPP penjualan register #' . $cash_register_data->id,
            ];
            $lines[] = [
                'chart_of_account_id' => JournalService::inventoryAccountIdForWarehouse($warehouseId),
                'debit' => 0,
                'credit' => $cogsAmount,
                'note' => 'Persediaan keluar register #' . $cash_register_data->id,
            ];
        }

        JournalService::post(
            $cash_register_data->approved_at,
            'Approve penjualan kasir #' . $cash_register_data->id,
            $lines,
            'cash_register_sale',
            $cash_register_data->id
        );
    }

    private function details($cash_register_data)
    {
        $data['cash_in_hand'] = $cash_register_data->cash_in_hand;
        $data['total_sale_amount'] = Sale::where([
            ['cash_register_id', $cash_register_data->id],
            ['sale_status', 1]
        ])->sum('grand_total');
        $data['total_payment'] = Payment::where('cash_register_id', $cash_register_data->id)->sum('amount');
        $data['cash_payment'] = Payment::where([
            ['cash_register_id', $cash_register_data->id],
            ['paying_method', 'Cash']
        ])->sum('amount');
        $data['credit_card_payment'] = Payment::where([
            ['cash_register_id', $cash_register_data->id],
            ['paying_method', 'Credit Card']
        ])->sum('amount');
        $data['gift_card_payment'] = Payment::where([
            ['cash_register_id', $cash_register_data->id],
            ['paying_method', 'Gift Card']
        ])->sum('amount');
        $data['deposit_payment'] = Payment::where([
            ['cash_register_id', $cash_register_data->id],
            ['paying_method', 'Deposit']
        ])->sum('amount');
        $data['cheque_payment'] = Payment::where([
            ['cash_register_id', $cash_register_data->id],
            ['paying_method', 'Cheque']
        ])->sum('amount');
        $data['paypal_payment'] = Payment::where([
            ['cash_register_id', $cash_register_data->id],
            ['paying_method', 'Paypal']
        ])->sum('amount');
        $data['due_payment'] = Payment::join('sales', 'payments.sale_id', '=', 'sales.id')
            ->where('payments.cash_register_id', $cash_register_data->id)
            ->where('payments.paying_method', 'Cash')
            ->where(function ($query) use ($cash_register_data) {
                $query->whereNull('sales.cash_register_id')
                    ->orWhere('sales.cash_register_id', '!=', $cash_register_data->id);
            })
            ->sum('payments.amount');
        $data['cash_sale_payment'] = max($data['cash_payment'] - $data['due_payment'], 0);
        $data['total_sale_return'] = Returns::where('cash_register_id', $cash_register_data->id)->sum('grand_total');
        $data['total_expense'] = Expense::where('cash_register_id', $cash_register_data->id)->sum('amount');
        $data['expected_cash_amount'] = $data['cash_in_hand'] + $data['cash_payment'] - ($data['total_sale_return'] + $data['total_expense']);
        $data['total_cash'] = $data['expected_cash_amount'];
        $data['closing_cash_amount'] = $cash_register_data->closing_cash_amount;
        $data['deposit_amount'] = $cash_register_data->deposit_amount;
        $data['cash_difference'] = $cash_register_data->cash_difference;
        $data['closing_note'] = $cash_register_data->closing_note;
        $data['approval_status'] = $cash_register_data->approval_status;
        $data['approval_note'] = $cash_register_data->approval_note;
        $data['status'] = $cash_register_data->status;
        $data['id'] = $cash_register_data->id;
        return $data;
    }

    /**
     * Izin kasir per role.
     *
     * Permission ini baru ditambahkan; server lama belum punya barisnya di tabel
     * permissions. Selama permission-nya belum ada, fungsi ini mengembalikan true
     * supaya kasir yang sedang berjalan tidak ikut terkunci. Begitu admin menyimpan
     * halaman Group Permission sekali (atau .sql-nya dijalankan), barulah izin ini
     * benar-benar menyaring.
     *
     * Pembagian admin vs kasir (role_id <= 2 atau > 2) tetap berlaku di masing-masing
     * method — izin ini hanya menentukan boleh/tidaknya membuka aksinya.
     */
    private function cashRegisterAllowed($permission_name)
    {
        if (!\Spatie\Permission\Models\Permission::where('name', $permission_name)->exists())
            return true;

        $role = \Spatie\Permission\Models\Role::find(Auth::user()->role_id);

        return $role && $role->hasPermissionTo($permission_name);
    }

    public function index()
    {
        if (!$this->cashRegisterAllowed('cash_register-index'))
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');

        if (Auth::user()->role_id <= 2) {
            $lims_cash_register_all = CashRegister::with('user', 'warehouse', 'approver')
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->get();
            return view('backend.cash_register.index', compact('lims_cash_register_all'));
        } else {
            $lims_cash_register_all = CashRegister::with('user', 'warehouse', 'approver')
                ->where('user_id', Auth::id())
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->get();
            return view('backend.cash_register.index', compact('lims_cash_register_all'));
        }
    }
    public function store(Request $request)
    {
        if (!$this->cashRegisterAllowed('cash_register-add'))
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to open a cash register');

        $data = $request->all();

        // Kasir tidak boleh memilih gudang sendiri: pakai penetapan admin di profil user.
        if (Auth::user()->role_id > 2) {
            if (!Auth::user()->warehouse_id)
                return redirect()->back()->with('not_permitted', 'Gudang/toko Anda belum ditetapkan admin. Hubungi admin sebelum membuka kasir.');
            $data['warehouse_id'] = Auth::user()->warehouse_id;
        }

        if (!\App\Models\Warehouse::isSellable($data['warehouse_id'] ?? null))
            return redirect()->back()->with('not_permitted', 'Kasir hanya bisa dibuka pada gudang bertipe Toko.');

        $availability = $this->availabilityStatus($data['warehouse_id']);
        if ($availability['status'] != 'available') {
            return redirect()->back()->with('not_permitted', $availability['message']);
        }
        $data['status'] = true;
        $data['user_id'] = Auth::id();
        $data['approval_status'] = 'open';
        CashRegister::create($data);
        return redirect()->back()->with('message', 'Cash register created successfully');
    }

    /**
     * Kasir tidak boleh melihat rekap penjualan sebelum menyetor.
     * Kalau angka sistem terlihat, setoran bisa "disesuaikan" dengan angka itu
     * sehingga kelebihan uang dari penjualan yang lalai diinput tidak pernah muncul
     * sebagai selisih kasir. Jadi untuk role non-admin hanya modal kembalian yang dikirim.
     */
    private function sanitizeDetails($data)
    {
        if (Auth::user()->role_id <= 2) {
            return $data;
        }

        $allowed = ['cash_in_hand', 'id', 'status', 'approval_status', 'approval_note', 'closing_note'];
        $safe = [];
        foreach ($allowed as $key) {
            $safe[$key] = isset($data[$key]) ? $data[$key] : null;
        }
        $safe['restricted'] = true;
        return $safe;
    }

    public function getDetails($id)
    {
        $cash_register_data = CashRegister::find($id);
        if (!$cash_register_data)
            return [];
        if (Auth::user()->role_id > 2 && $cash_register_data->user_id != Auth::id())
            return [];

        return $this->sanitizeDetails($this->details($cash_register_data));
    }

    public function showDetails($warehouse_id)
    {
        $cash_register_data = CashRegister::where([
            ['user_id', Auth::id()],
            ['warehouse_id', $warehouse_id],
            ['status', true]
        ])->first();

        if (!$cash_register_data)
            return [];

        return $this->sanitizeDetails($this->details($cash_register_data));
    }

    public function close(Request $request)
    {
        if (!$this->cashRegisterAllowed('cash_register-edit'))
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to close this register');

        $request->validate([
            'cash_register_id'    => 'required|exists:cash_registers,id',
            'closing_cash_amount' => 'required|numeric|min:0.01',
        ], [
            'closing_cash_amount.required' => 'Anda wajib mengisi jumlah uang yang dihitung sebelum menutup kasir!',
            'closing_cash_amount.min'      => 'Jumlah uang yang dihitung tidak boleh 0 atau kosong!',
        ]);

        $cash_register_data = CashRegister::find($request->cash_register_id);
        if (Auth::user()->role_id > 2 && $cash_register_data->user_id != Auth::id())
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to close this register');

        // Kasir hanya mengisi satu angka: uang yang dihitung = uang yang disetor.
        $deposit_amount = Auth::user()->role_id <= 2 && $request->filled('deposit_amount')
            ? (float)$request->deposit_amount
            : (float)$request->closing_cash_amount;

        $details = $this->details($cash_register_data);
        $cash_register_data->status = 0;
        $cash_register_data->expected_cash_amount = $details['expected_cash_amount'];
        $cash_register_data->closing_cash_amount = $request->closing_cash_amount;
        $cash_register_data->deposit_amount = $deposit_amount;
        $cash_register_data->cash_difference = $cash_register_data->deposit_amount - $details['expected_cash_amount'];
        $cash_register_data->closing_note = $request->closing_note;
        $cash_register_data->approval_status = 'pending';
        $cash_register_data->save();
        return redirect()->back()->with('message', 'Cash register closed successfully');
    }

    public function updateDeposit(Request $request)
    {
        // Hanya admin yang boleh merevisi setoran. Tanpa cek ini kasir bisa POST langsung
        // ke endpoint dan menyesuaikan setorannya setelah tahu berapa selisihnya.
        if (Auth::user()->role_id > 2 || !$this->cashRegisterAllowed('cash_register-edit'))
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to edit this deposit');

        $request->validate([
            'cash_register_id'    => 'required|exists:cash_registers,id',
            'closing_cash_amount' => 'required|numeric|min:0.01',
        ]);

        $cash_register_data = CashRegister::find($request->cash_register_id);
        $expected_cash_amount = $cash_register_data->expected_cash_amount ?: $this->details($cash_register_data)['expected_cash_amount'];
        $cash_register_data->expected_cash_amount = $expected_cash_amount;
        $cash_register_data->closing_cash_amount = $request->closing_cash_amount;
        $cash_register_data->deposit_amount = $request->deposit_amount ?: $request->closing_cash_amount;
        $cash_register_data->cash_difference = $cash_register_data->deposit_amount - $expected_cash_amount;
        $cash_register_data->closing_note = $request->closing_note;
        $cash_register_data->approval_status = 'pending';
        $cash_register_data->approved_by = null;
        $cash_register_data->approved_at = null;
        $cash_register_data->approval_note = null;
        $cash_register_data->save();

        // Setoran berubah => jurnal approve sebelumnya sudah tidak valid, batalkan
        // sampai admin approve ulang.
        try {
            JournalService::reverseForReference('cash_register_sale', $cash_register_data->id);
            JournalService::reverseForReference('cash_register_deposit', $cash_register_data->id);
        } catch (\Throwable $e) {
            \Log::error('Gagal reverse jurnal kasir #' . $cash_register_data->id . ': ' . $e->getMessage());
        }

        return redirect()->back()->with('message', 'Cashier deposit updated successfully');
    }

    public function approve(Request $request)
    {
        if (Auth::user()->role_id > 2 || !$this->cashRegisterAllowed('cash_register-delete'))
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to approve this deposit');

        $cash_register_data = CashRegister::find($request->cash_register_id);
        if (!$cash_register_data)
            return redirect()->back()->with('not_permitted', 'Data kasir tidak ditemukan');

        // Status + jurnal disimpan dalam satu transaksi. Sebelumnya status
        // disimpan duluan, jadi kalau jurnalnya gagal setoran tercatat
        // "approved" tapi tanpa jurnal - datanya jadi tidak konsisten dan
        // approve ulang tidak memperbaikinya.
        try {
            \DB::transaction(function () use ($cash_register_data, $request) {
                $cash_register_data->approval_status = 'approved';
                $cash_register_data->approved_by = Auth::id();
                $cash_register_data->approved_at = date('Y-m-d H:i:s');
                $cash_register_data->approval_note = $request->approval_note;
                $cash_register_data->save();

                $this->postApprovedSalesJournal($cash_register_data);
            });
        } catch (\Throwable $e) {
            \Log::error('Gagal posting jurnal approve kasir #' . $cash_register_data->id . ': ' . $e->getMessage());
            return redirect()->back()->with('not_permitted', 'Approve dibatalkan, jurnal gagal diposting: ' . $e->getMessage());
        }
        return redirect()->back()->with('message', 'Cashier deposit approved successfully');
    }

    public function printReport($id)
    {
        $cash_register_data = CashRegister::with('user', 'warehouse', 'approver')->find($id);
        if (!$cash_register_data)
            return redirect()->back()->with('not_permitted', 'Data kasir tidak ditemukan');
        // Laporan ini memuat Kas Sistem dan Selisih, jadi hanya admin yang boleh membukanya.
        if (Auth::user()->role_id > 2)
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to view this report');

        $details = $this->details($cash_register_data);
        return view('backend.cash_register.print', compact('cash_register_data', 'details'));
    }

    private function availabilityStatus($warehouse_id)
    {
        $today = date('Y-m-d');

        $pending_deposit = CashRegister::with('warehouse')
            ->where([
                ['user_id', Auth::id()],
                ['status', false]
            ])
            // Setoran kemarin boleh tetap menunggu approval. Yang dicegah
            // hanya membuka register kedua pada hari kerja yang sama.
            ->whereDate('created_at', $today)
            ->where(function ($query) {
                $query->whereNull('approval_status')
                    ->orWhere('approval_status', '')
                    ->orWhereRaw('LOWER(approval_status) != ?', ['approved']);
            })
            ->latest('id')
            ->first();

        if ($pending_deposit) {
            return [
                'status' => 'need_deposit',
                'message' => 'Kasir hari ini di ' . ($pending_deposit->warehouse->name ?? 'gudang lain') . ' sudah ditutup dan setoran masih menunggu approval. Kasir baru bisa dibuka lagi pada hari berikutnya.',
                'cash_register_id' => $pending_deposit->id,
                'warehouse_id' => $pending_deposit->warehouse_id,
                'warehouse_name' => $pending_deposit->warehouse->name ?? null,
            ];
        }

        $open_register = CashRegister::where([
                ['user_id', Auth::id()],
                ['warehouse_id', $warehouse_id],
                ['status', true]
            ])->latest('id')->first();

        if ($open_register) {
            if (date('Y-m-d', strtotime($open_register->created_at)) < $today) {
                return [
                    'status' => 'need_deposit',
                    'message' => 'Kasir sebelumnya belum ditutup dan sudah melewati hari. Tutup kasir dan selesaikan setoran terlebih dahulu sebelum input modal kasir hari ini.',
                    'cash_register_id' => $open_register->id,
                ];
            }
            return [
                'status' => 'open',
                'message' => 'Kasir sudah terbuka.',
                'cash_register_id' => $open_register->id,
                'warehouse_id' => $open_register->warehouse_id,
            ];
        }

        /**
         * Admin sering membuka POS dengan gudang lain (nilai tersimpan di localStorage
         * atau default POS Setting) padahal kasirnya sudah dibuka di gudang berbeda.
         * Tanpa cek ini hasilnya "available" dan modal Modal Awal Kasir muncul terus
         * walau register masih open. Jadi kalau user punya register open di gudang lain,
         * beri tahu POS supaya gudangnya disinkronkan, bukan minta buka kasir lagi.
         */
        $open_elsewhere = CashRegister::with('warehouse')
            ->where([
                ['user_id', Auth::id()],
                ['status', true],
            ])
            ->latest('id')
            ->first();

        if ($open_elsewhere) {
            $register_date = date('Y-m-d', strtotime($open_elsewhere->created_at));
            return [
                'status' => $register_date < $today ? 'need_deposit' : 'open_other_warehouse',
                'message' => $register_date < $today
                    ? 'Kasir sebelumnya di ' . ($open_elsewhere->warehouse->name ?? 'gudang lain') . ' belum ditutup dan sudah melewati hari. Tutup kasir dan selesaikan setoran terlebih dahulu sebelum input modal kasir hari ini.'
                    : 'Kasir Anda masih terbuka di ' . ($open_elsewhere->warehouse->name ?? 'gudang lain') . '. Tutup kasir itu dulu sebelum membuka kasir di gudang lain.',
                'cash_register_id' => $open_elsewhere->id,
                'warehouse_id' => $open_elsewhere->warehouse_id,
                'warehouse_name' => $open_elsewhere->warehouse->name ?? null,
            ];
        }

        $closed_today = CashRegister::with('warehouse')
            ->where([
                ['user_id', Auth::id()],
                ['status', false]
            ])
            ->whereDate('created_at', $today)
            ->latest('id')
            ->first();

        if ($closed_today) {
            return [
                'status' => 'closed_today',
                'message' => 'Kasir hari ini di ' . ($closed_today->warehouse->name ?? 'gudang lain') . ' sudah tutup dan sudah setoran. Kasir yang sudah tutup dan setoran di hari yang sama tidak bisa dibuka lagi.',
                'cash_register_id' => $closed_today->id,
                'warehouse_id' => $closed_today->warehouse_id,
                'warehouse_name' => $closed_today->warehouse->name ?? null,
            ];
        }

        return ['status' => 'available', 'message' => 'Silakan input modal awal kasir.'];
    }

    public function checkAvailability($warehouse_id)
    {
        return response()->json($this->availabilityStatus($warehouse_id));
    }
}
