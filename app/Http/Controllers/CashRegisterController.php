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

    public function index()
    {
        if (Auth::user()->role_id <= 2) {
            $lims_cash_register_all = CashRegister::with('user', 'warehouse', 'approver')->get();
            return view('backend.cash_register.index', compact('lims_cash_register_all'));
        } else {
            $lims_cash_register_all = CashRegister::with('user', 'warehouse', 'approver')
                ->where('user_id', Auth::id())
                ->get();
            return view('backend.cash_register.index', compact('lims_cash_register_all'));
        }
    }
    public function store(Request $request)
    {
        $data = $request->all();
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

    public function getDetails($id)
    {
        $cash_register_data = CashRegister::find($id);
        return $this->details($cash_register_data);
    }

    public function showDetails($warehouse_id)
    {
        $cash_register_data = CashRegister::where([
            ['user_id', Auth::id()],
            ['warehouse_id', $warehouse_id],
            ['status', true]
        ])->first();

        return $this->details($cash_register_data);
    }

    public function close(Request $request)
    {
        $request->validate([
            'cash_register_id'    => 'required|exists:cash_registers,id',
            'closing_cash_amount' => 'required|numeric',
            'deposit_amount'      => 'required|numeric|min:0.01',
        ], [
            'deposit_amount.required' => 'Anda wajib mengisi jumlah setoran kasir sebelum menutup kasir!',
            'deposit_amount.min'      => 'Jumlah setoran tidak boleh 0 atau kosong!',
        ]);

        $cash_register_data = CashRegister::find($request->cash_register_id);
        $details = $this->details($cash_register_data);
        $cash_register_data->status = 0;
        $cash_register_data->expected_cash_amount = $details['expected_cash_amount'];
        $cash_register_data->closing_cash_amount = $request->closing_cash_amount;
        $cash_register_data->deposit_amount = $request->deposit_amount; // tidak ada fallback lagi
        $cash_register_data->cash_difference = $cash_register_data->closing_cash_amount - $details['expected_cash_amount'];
        $cash_register_data->closing_note = $request->closing_note;
        $cash_register_data->approval_status = 'pending';
        $cash_register_data->save();
        return redirect()->back()->with('message', 'Cash register closed successfully');
    }

    public function updateDeposit(Request $request)
    {
        $cash_register_data = CashRegister::find($request->cash_register_id);
        $expected_cash_amount = $cash_register_data->expected_cash_amount ?: $this->details($cash_register_data)['expected_cash_amount'];
        $cash_register_data->expected_cash_amount = $expected_cash_amount;
        $cash_register_data->closing_cash_amount = $request->closing_cash_amount;
        $cash_register_data->deposit_amount = $request->deposit_amount ?: $request->closing_cash_amount;
        $cash_register_data->cash_difference = $cash_register_data->closing_cash_amount - $expected_cash_amount;
        $cash_register_data->closing_note = $request->closing_note;
        $cash_register_data->approval_status = 'pending';
        $cash_register_data->approved_by = null;
        $cash_register_data->approved_at = null;
        $cash_register_data->approval_note = null;
        $cash_register_data->save();
        return redirect()->back()->with('message', 'Cashier deposit updated successfully');
    }

    public function approve(Request $request)
    {
        if (Auth::user()->role_id > 2)
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to approve this deposit');

        $cash_register_data = CashRegister::find($request->cash_register_id);
        $cash_register_data->approval_status = 'approved';
        $cash_register_data->approved_by = Auth::id();
        $cash_register_data->approved_at = date('Y-m-d H:i:s');
        $cash_register_data->approval_note = $request->approval_note;
        $cash_register_data->save();

        try {
            JournalService::reverseForReference('cash_register_deposit', $cash_register_data->id);
            $kas_id = JournalService::accountIdByCode(JournalService::KAS);
            $selisih_id = JournalService::accountIdByCode(JournalService::SELISIH_KASIR);
            JournalService::postSimple(
                $cash_register_data->approved_at,
                'Approve setoran kasir #' . $cash_register_data->id,
                $kas_id,
                $selisih_id,
                $cash_register_data->deposit_amount,
                'cash_register_deposit',
                $cash_register_data->id
            );
        } catch (\Exception $e) {}
        return redirect()->back()->with('message', 'Cashier deposit approved successfully');
    }

    public function printReport($id)
    {
        $cash_register_data = CashRegister::with('user', 'warehouse', 'approver')->find($id);
        $details = $this->details($cash_register_data);
        return view('backend.cash_register.print', compact('cash_register_data', 'details'));
    }

    private function availabilityStatus($warehouse_id)
    {
        $today = date('Y-m-d');
        $open_register = CashRegister::where([
                ['user_id', Auth::id()],
                ['warehouse_id', $warehouse_id],
                ['status', true]
            ])->latest('id')->first();

        if ($open_register) {
            if (date('Y-m-d', strtotime($open_register->created_at)) < $today) {
                return [
                    'status' => 'need_deposit',
                    'message' => 'Kasir sebelumnya belum ditutup/setor dan sudah melewati hari. Tutup kasir dan selesaikan setoran terlebih dahulu.',
                    'cash_register_id' => $open_register->id,
                ];
            }
            return ['status' => 'open', 'message' => 'Kasir sudah terbuka.', 'cash_register_id' => $open_register->id];
        }

        $closed_today = CashRegister::where([
                ['user_id', Auth::id()],
                ['warehouse_id', $warehouse_id],
                ['status', false]
            ])
            ->whereDate('created_at', $today)
            ->latest('id')
            ->first();

        if ($closed_today) {
            return [
                'status' => 'closed_today',
                'message' => 'Kasir hari ini sudah tutup/setor. Kasir yang sudah tutup dan setor di hari yang sama tidak bisa dibuka lagi.',
                'cash_register_id' => $closed_today->id,
            ];
        }

        $pending_deposit = CashRegister::where([
                ['user_id', Auth::id()],
                ['warehouse_id', $warehouse_id],
                ['status', false]
            ])
            ->where('approval_status', '!=', 'approved')
            ->latest('id')
            ->first();

        if ($pending_deposit) {
            return [
                'status' => 'need_deposit',
                'message' => 'Anda masih punya setoran kasir yang belum selesai/approve. Selesaikan setoran terlebih dahulu sebelum membuka kasir baru.',
                'cash_register_id' => $pending_deposit->id,
            ];
        }

        return ['status' => 'available', 'message' => 'Silakan input modal awal kasir.'];
    }

    public function checkAvailability($warehouse_id)
    {
        return response()->json($this->availabilityStatus($warehouse_id));
    }
}
