<?php

namespace App\Services;

use App\Models\Account;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\JournalDetail;
use App\Models\ExpenseCategory;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Auth;

/**
 * Central helper for posting/reversing double-entry journal entries.
 * Used both by the manual "Jurnal Umum" screen and by auto-posting hooks
 * placed inside Sale/Purchase/Expense/Payroll/MoneyTransfer/Return/Deposit
 * controllers so that Buku Besar & Neraca Saldo always reflect reality.
 */
class JournalService
{
    // Standard chart of account codes seeded by the create_chart_of_accounts_table migration
    const KAS = '1101';
    const PIUTANG_USAHA = '1200';
    const PERSEDIAAN = '1300';
    const TITIPAN_SUPPLIER = '1400';
    const HUTANG_USAHA = '2100';
    const TITIPAN_PELANGGAN = '2200';
    const SELISIH_KASIR = '2300';
    const MODAL_PEMILIK = '3100';
    const PENJUALAN = '4100';
    const RETUR_PENJUALAN = '4200';
    const DISKON_PENJUALAN = '4300';
    const PENDAPATAN_LAIN = '4900';
    const HPP = '5100';
    const BEBAN_OPERASIONAL = '5200';
    const BIAYA_KIRIM_PEMBELIAN = '5210';
    const BEBAN_GAJI = '5300';
    const RETUR_PEMBELIAN = '5400';
    const DISKON_PEMBELIAN = '5410';
    const BEBAN_LAIN = '5900';

    protected static $codeCache = [];

    /**
     * Find a chart-of-account id by its standard code (cached per request).
     */
    public static function accountIdByCode($code)
    {
        if (isset(self::$codeCache[$code])) {
            return self::$codeCache[$code];
        }
        $coa = ChartOfAccount::where('code', $code)->first();
        self::$codeCache[$code] = $coa ? $coa->id : null;
        return self::$codeCache[$code];
    }

    /**
     * Resolve the chart-of-account id tied to a cash/bank Account row
     * (accounts.id). If the account was created before the accounting
     * module existed (or after, without going through the normal flow),
     * lazily create a child COA under "Kas" (1101) for it.
     */
    public static function coaIdForCashAccount($accountId)
    {
        $kasId = self::ensureAccountByCode(self::KAS, 'Kas', 'asset', 'debit');

        if (!$accountId) {
            return $kasId;
        }
        $account = Account::find($accountId);
        if (!$account) {
            return $kasId;
        }
        if ($account->chart_of_account_id && ChartOfAccount::where('id', $account->chart_of_account_id)->exists()) {
            return $account->chart_of_account_id;
        }

        $existingChildren = ChartOfAccount::where('parent_id', $kasId)->count();
        $coa = ChartOfAccount::create([
            'code' => self::KAS . '.' . ($existingChildren + 1),
            'name' => $account->name . ' (' . $account->account_no . ')',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'parent_id' => $kasId,
            'opening_balance' => $account->initial_balance ?: 0,
            'description' => 'Auto-generated for account: ' . $account->name,
            'is_default' => 0,
            'is_active' => 1,
        ]);
        $account->chart_of_account_id = $coa->id;
        $account->save();
        return $coa->id;
    }

    /**
     * Resolve the payable account for a warehouse. Example:
     * "2100.1 - Hutang Gudang Default". This keeps purchase payable and
     * purchase payment journals visible per warehouse instead of one generic
     * Hutang Usaha account.
     */
    public static function payableAccountIdForWarehouse($warehouseId)
    {
        if (!$warehouseId) {
            return self::accountIdByCode(self::HUTANG_USAHA);
        }

        $warehouse = Warehouse::find($warehouseId);
        $parentId = self::accountIdByCode(self::HUTANG_USAHA);
        $code = self::HUTANG_USAHA . '.' . $warehouseId;
        $coa = ChartOfAccount::where('code', $code)->first();
        if ($coa) {
            return $coa->id;
        }

        $name = 'Hutang Gudang ' . ($warehouse ? $warehouse->name : $warehouseId);
        $coa = ChartOfAccount::create([
            'code' => $code,
            'name' => $name,
            'type' => 'liability',
            'normal_balance' => 'credit',
            'parent_id' => $parentId,
            'opening_balance' => 0,
            'description' => 'Auto-generated payable account for warehouse: ' . ($warehouse ? $warehouse->name : $warehouseId),
            'is_default' => 0,
            'is_active' => 1,
        ]);

        return $coa->id;
    }

    /**
     * Resolve the inventory account for a warehouse. Example:
     * "1300.1 - Persediaan Barang Gudang Default".
     */
    public static function inventoryAccountIdForWarehouse($warehouseId)
    {
        if (!$warehouseId) {
            return self::accountIdByCode(self::PERSEDIAAN);
        }

        $warehouse = Warehouse::find($warehouseId);
        $parentId = self::accountIdByCode(self::PERSEDIAAN);
        $code = self::PERSEDIAAN . '.' . $warehouseId;
        $coa = ChartOfAccount::where('code', $code)->first();
        if ($coa) {
            return $coa->id;
        }

        $name = 'Persediaan Barang Gudang ' . ($warehouse ? $warehouse->name : $warehouseId);
        $coa = ChartOfAccount::create([
            'code' => $code,
            'name' => $name,
            'type' => 'asset',
            'normal_balance' => 'debit',
            'parent_id' => $parentId,
            'opening_balance' => 0,
            'description' => 'Auto-generated inventory account for warehouse: ' . ($warehouse ? $warehouse->name : $warehouseId),
            'is_default' => 0,
            'is_active' => 1,
        ]);

        return $coa->id;
    }

    /**
     * Akun penampung untuk barang yang sudah keluar dari gudang asal tapi belum
     * diterima gudang tujuan (transfer berstatus "Sent"). Nilainya pindah ke
     * persediaan gudang tujuan begitu transfer diselesaikan.
     */
    public static function inventoryInTransitAccountId()
    {
        $code = self::PERSEDIAAN . '.TRANSIT';
        $coa = ChartOfAccount::where('code', $code)->first();
        if ($coa) {
            return $coa->id;
        }

        $coa = ChartOfAccount::create([
            'code' => $code,
            'name' => 'Persediaan Barang Dalam Perjalanan',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'parent_id' => self::accountIdByCode(self::PERSEDIAAN),
            'opening_balance' => 0,
            'description' => 'Auto-generated in-transit inventory account for stock transfers',
            'is_default' => 0,
            'is_active' => 1,
        ]);

        return $coa->id;
    }

    public static function stockVarianceAccountIdForWarehouse($warehouseId)
    {
        $parentId = self::ensureAccountByCode(
            self::SELISIH_KASIR,
            'Selisih Kasir (Penyesuaian Stok)',
            'liability',
            'credit'
        );

        if (!$warehouseId) {
            return $parentId;
        }

        $warehouse = Warehouse::find($warehouseId);
        $code = self::SELISIH_KASIR . '.' . $warehouseId;
        $coa = ChartOfAccount::where('code', $code)->first();
        if ($coa) {
            return $coa->id;
        }

        $coa = ChartOfAccount::create([
            'code' => $code,
            'name' => 'Selisih Stok ' . ($warehouse ? $warehouse->name : $warehouseId),
            'type' => 'liability',
            'normal_balance' => 'credit',
            'parent_id' => $parentId,
            'opening_balance' => 0,
            'description' => 'Auto-generated stock variance account for warehouse: ' . ($warehouse ? $warehouse->name : $warehouseId),
            'is_default' => 0,
            'is_active' => 1,
        ]);

        return $coa->id;
    }

    public static function cashierDifferenceAccountIdForWarehouse($warehouseId)
    {
        // ensureAccountByCode, bukan accountIdByCode: di server yang migrasinya
        // belum jalan akun 2300 belum ada, dan id null bikin baris selisih hilang
        // dari jurnal -> approve setoran gagal dengan "Jurnal tidak seimbang".
        $parentId = self::ensureAccountByCode(
            self::SELISIH_KASIR,
            'Selisih Kasir (Penyesuaian Stok)',
            'liability',
            'credit'
        );

        if (!$warehouseId) {
            return $parentId;
        }

        $warehouse = Warehouse::find($warehouseId);
        $code = self::SELISIH_KASIR . '.K' . $warehouseId;
        $coa = ChartOfAccount::where('code', $code)->first();
        if ($coa) {
            return $coa->id;
        }

        $coa = ChartOfAccount::create([
            'code' => $code,
            'name' => 'Selisih Kasir ' . ($warehouse ? $warehouse->name : $warehouseId),
            'type' => 'liability',
            'normal_balance' => 'credit',
            'parent_id' => $parentId,
            'opening_balance' => 0,
            'description' => 'Auto-generated cashier difference account for warehouse: ' . ($warehouse ? $warehouse->name : $warehouseId),
            'is_default' => 0,
            'is_active' => 1,
        ]);

        return $coa->id;
    }

    public static function salesRevenueAccountIdForWarehouse($warehouseId)
    {
        $warehouse = $warehouseId ? Warehouse::find($warehouseId) : null;
        $parentId = self::accountIdByCode(self::PENJUALAN);
        $code = self::PENJUALAN . '.' . ($warehouseId ?: '0');
        $coa = ChartOfAccount::where('code', $code)->first();
        if ($coa) {
            return $coa->id;
        }

        $coa = ChartOfAccount::create([
            'code' => $code,
            'name' => 'Penjualan Tunai ' . ($warehouse ? $warehouse->name : $warehouseId),
            'type' => 'revenue',
            'normal_balance' => 'credit',
            'parent_id' => $parentId,
            'opening_balance' => 0,
            'description' => 'Auto-generated sales revenue account for warehouse: ' . ($warehouse ? $warehouse->name : $warehouseId),
            'is_default' => 0,
            'is_active' => 1,
        ]);

        return $coa->id;
    }

    public static function salesCashAccountIdForWarehouse($warehouseId)
    {
        $warehouse = $warehouseId ? Warehouse::find($warehouseId) : null;
        $parentId = self::accountIdByCode(self::KAS);
        $code = self::KAS . '.PJ' . ($warehouseId ?: '0');
        $coa = ChartOfAccount::where('code', $code)->first();
        if ($coa) {
            return $coa->id;
        }

        $coa = ChartOfAccount::create([
            'code' => $code,
            'name' => 'Pendapatan Penjualan ' . ($warehouse ? $warehouse->name : $warehouseId),
            'type' => 'asset',
            'normal_balance' => 'debit',
            'parent_id' => $parentId,
            'opening_balance' => 0,
            'description' => 'Auto-generated cash sales clearing account for warehouse: ' . ($warehouse ? $warehouse->name : $warehouseId),
            'is_default' => 0,
            'is_active' => 1,
        ]);

        return $coa->id;
    }

    public static function receivableAccountIdForWarehouse($warehouseId)
    {
        $warehouse = $warehouseId ? Warehouse::find($warehouseId) : null;
        $parentId = self::accountIdByCode(self::PIUTANG_USAHA);
        $code = self::PIUTANG_USAHA . '.' . ($warehouseId ?: '0');
        $coa = ChartOfAccount::where('code', $code)->first();
        if ($coa) {
            return $coa->id;
        }

        $coa = ChartOfAccount::create([
            'code' => $code,
            'name' => 'Piutang Usaha ' . ($warehouse ? $warehouse->name : $warehouseId),
            'type' => 'asset',
            'normal_balance' => 'debit',
            'parent_id' => $parentId,
            'opening_balance' => 0,
            'description' => 'Auto-generated receivable account for warehouse: ' . ($warehouse ? $warehouse->name : $warehouseId),
            'is_default' => 0,
            'is_active' => 1,
        ]);

        return $coa->id;
    }

    /**
     * Cash/bank account balance from journal movements:
     * opening balance + debit - credit.
     */
    public static function cashAccountBalance($accountId, $asOfDate = null)
    {
        $coaId = self::coaIdForCashAccount($accountId);
        $coa = ChartOfAccount::find($coaId);
        if (!$coa) {
            return 0;
        }

        $query = JournalDetail::join('journals', 'journals.id', '=', 'journal_details.journal_id')
            ->where('journal_details.chart_of_account_id', $coa->id);
        if ($asOfDate) {
            $query->whereDate('journals.journal_date', '<=', $asOfDate);
        }
        $sums = $query->selectRaw('COALESCE(SUM(debit),0) as total_debit, COALESCE(SUM(credit),0) as total_credit')->first();

        return (float)$coa->opening_balance + (float)$sums->total_debit - (float)$sums->total_credit;
    }

    /**
     * Resolve which chart-of-account should be debited when an Expense is
     * posted: the Expense Category's own "Akun Beban" if one was picked,
     * otherwise the generic "Beban Operasional" (5200) account.
     */
    public static function expenseDebitAccountId($expenseCategoryId)
    {
        if ($expenseCategoryId) {
            $category = ExpenseCategory::find($expenseCategoryId);
            if ($category && $category->chart_of_account_id) {
                // Guard against a category pointing at a COA row that was
                // deleted afterwards - fall through to the generic account
                // instead of posting against a dangling id.
                if (ChartOfAccount::where('id', $category->chart_of_account_id)->exists()) {
                    return $category->chart_of_account_id;
                }
            }
        }
        return self::ensureAccountByCode(
            self::BEBAN_OPERASIONAL,
            'Beban Operasional',
            'expense',
            'debit'
        );
    }

    /**
     * Look up a standard COA by code, creating it if the seed row is missing.
     * Without this a partially-seeded chart_of_accounts table makes every
     * auto-posting hook silently do nothing (null account id => no journal).
     */
    public static function ensureAccountByCode($code, $name, $type, $normalBalance, $parentCode = null)
    {
        $id = self::accountIdByCode($code);
        if ($id) {
            return $id;
        }

        $coa = ChartOfAccount::create([
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'normal_balance' => $normalBalance,
            'parent_id' => $parentCode ? self::accountIdByCode($parentCode) : null,
            'opening_balance' => 0,
            'description' => 'Auto-created because the standard account ' . $code . ' was missing.',
            'is_default' => 1,
            'is_active' => 1,
        ]);
        self::$codeCache[$code] = $coa->id;

        return $coa->id;
    }

    public static function generateJournalNo()
    {
        return 'jr-' . date('Ymd') . '-' . date('His') . '-' . rand(100, 999);
    }

    /**
     * Post a balanced journal. $lines is an array of
     * ['chart_of_account_id' => int, 'debit' => float, 'credit' => float, 'note' => string|null]
     *
     * Throws \Exception if debit total != credit total (rounded to 2 decimals)
     * or if fewer than 2 lines are given.
     */
    public static function post($journalDate, $description, array $lines, $referenceType = 'manual', $referenceId = null, $isManual = false, $journalNo = null)
    {
        // Baris tanpa akun TIDAK boleh dibuang diam-diam: kalau dibuang, jurnal
        // yang tadinya seimbang jadi timpang dan errornya muncul sebagai
        // "Jurnal tidak seimbang" yang menyesatkan. Laporkan akunnya yang hilang.
        foreach ($lines as $line) {
            $hasAmount = (float)($line['debit'] ?? 0) != 0 || (float)($line['credit'] ?? 0) != 0;
            if ($hasAmount && empty($line['chart_of_account_id'])) {
                throw new \Exception(
                    'Akun jurnal belum ada untuk baris "' . ($line['note'] ?? '-') . '". '
                    . 'Periksa Bagan Akun (Chart of Accounts).'
                );
            }
        }

        $lines = array_values(array_filter($lines, function ($l) {
            return !empty($l['chart_of_account_id']) && ((float)($l['debit'] ?? 0) > 0 || (float)($l['credit'] ?? 0) > 0);
        }));

        if (count($lines) < 2) {
            throw new \Exception('Jurnal harus memiliki minimal 2 baris (debit dan kredit).');
        }

        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($lines as $line) {
            $totalDebit += (float)($line['debit'] ?? 0);
            $totalCredit += (float)($line['credit'] ?? 0);
        }

        // Bandingkan dengan toleransi, bukan `!==` pada float. Penjumlahan float
        // dengan urutan berbeda (mis. HPP rata-rata berdesimal) bisa meleset 1 ULP
        // sehingga round() jatuh ke sisi berbeda dan jurnal yang benar ikut ditolak.
        if (abs(round($totalDebit, 2) - round($totalCredit, 2)) > 0.005) {
            throw new \Exception('Jurnal tidak seimbang: total debit (' . $totalDebit . ') harus sama dengan total kredit (' . $totalCredit . ').');
        }

        return DB::transaction(function () use ($journalDate, $description, $lines, $referenceType, $referenceId, $isManual, $journalNo) {
            $journal = Journal::create([
                'journal_no' => $journalNo ?: self::generateJournalNo(),
                'journal_date' => $journalDate,
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'is_manual' => $isManual,
                'created_by' => Auth::check() ? Auth::user()->id : null,
            ]);

            foreach ($lines as $line) {
                JournalDetail::create([
                    'journal_id' => $journal->id,
                    'chart_of_account_id' => $line['chart_of_account_id'],
                    'debit' => (float)($line['debit'] ?? 0),
                    'credit' => (float)($line['credit'] ?? 0),
                    'note' => $line['note'] ?? null,
                ]);
            }

            return $journal;
        });
    }

    /**
     * Simple two-line (single debit account / single credit account) helper
     * covering the common case: Dr X, Cr Y for the same amount.
     */
    public static function postSimple($journalDate, $description, $debitAccountId, $creditAccountId, $amount, $referenceType, $referenceId, $note = null)
    {
        $amount = (float) $amount;
        if ($amount <= 0) {
            return null;
        }
        if (!$debitAccountId || !$creditAccountId) {
            // Previously this returned null silently, so a missing chart of
            // account made auto-posting look like it "just didn't run".
            // Throw instead: every caller wraps this in try/catch + log.
            throw new \Exception('Akun jurnal belum lengkap (debit: ' . var_export($debitAccountId, true) . ', kredit: ' . var_export($creditAccountId, true) . '). Periksa Bagan Akun (Chart of Accounts).');
        }
        return self::post($journalDate, $description, [
            ['chart_of_account_id' => $debitAccountId, 'debit' => $amount, 'credit' => 0, 'note' => $note],
            ['chart_of_account_id' => $creditAccountId, 'debit' => 0, 'credit' => $amount, 'note' => $note],
        ], $referenceType, $referenceId, false);
    }

    /**
     * Sum of (qty * cost) for every product line on a sale, honouring the
     * per-unit operator/operation_value conversion used elsewhere in the app
     * (see AccountsController::profitLoss). Used to post the COGS journal
     * entry (Dr HPP / Cr Persediaan) when a sale is recognized.
     */
    public static function saleCogsAmount($saleId)
    {
        $sold_products = DB::table('product_sales')
            ->join('products', 'product_sales.product_id', '=', 'products.id')
            ->leftJoin('units', 'product_sales.sale_unit_id', '=', 'units.id')
            ->where('product_sales.sale_id', $saleId)
            ->select('product_sales.qty', 'products.cost', 'units.operator', 'units.operation_value')
            ->get();

        $cost = 0;
        foreach ($sold_products as $sp) {
            $qty = $sp->qty;
            if ($sp->operator == '*')
                $qty *= $sp->operation_value;
            elseif ($sp->operator == '/' && $sp->operation_value)
                $qty /= $sp->operation_value;
            $cost += $qty * $sp->cost;
        }
        return $cost;
    }

    /**
     * Sum of (qty * cost) for every product line on a sale return, mirroring
     * saleCogsAmount(). Used to reverse the COGS/inventory recognized when
     * the original sale was made (Dr Persediaan / Cr HPP).
     */
    public static function returnCogsAmount($returnId)
    {
        $returned_products = DB::table('product_returns')
            ->join('products', 'product_returns.product_id', '=', 'products.id')
            ->leftJoin('units', 'product_returns.sale_unit_id', '=', 'units.id')
            ->where('product_returns.return_id', $returnId)
            ->select('product_returns.qty', 'products.cost', 'units.operator', 'units.operation_value')
            ->get();

        $cost = 0;
        foreach ($returned_products as $rp) {
            $qty = $rp->qty;
            if ($rp->operator == '*')
                $qty *= $rp->operation_value;
            elseif ($rp->operator == '/' && $rp->operation_value)
                $qty /= $rp->operation_value;
            $cost += $qty * $rp->cost;
        }
        return $cost;
    }

    /**
     * Remove every auto-posted journal tied to a given reference (used before
     * re-posting on update, and on delete). Manual journals are never touched
     * here since they always carry reference_type = 'manual'.
     */
    public static function reverseForReference($referenceType, $referenceId)
    {
        if (!$referenceId) {
            return;
        }
        $journals = Journal::where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->get();
        foreach ($journals as $journal) {
            JournalDetail::where('journal_id', $journal->id)->delete();
            $journal->delete();
        }
    }
}
