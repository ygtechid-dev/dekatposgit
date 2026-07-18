<?php

namespace App\Services;

use App\Models\Account;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\JournalDetail;
use App\Models\ExpenseCategory;
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
    const BEBAN_GAJI = '5300';
    const RETUR_PEMBELIAN = '5400';
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
        if (!$accountId) {
            return self::accountIdByCode(self::KAS);
        }
        $account = Account::find($accountId);
        if (!$account) {
            return self::accountIdByCode(self::KAS);
        }
        if ($account->chart_of_account_id) {
            return $account->chart_of_account_id;
        }

        $kasId = self::accountIdByCode(self::KAS);
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
     * Resolve which chart-of-account should be debited when an Expense is
     * posted: the Expense Category's own "Akun Beban" if one was picked,
     * otherwise the generic "Beban Operasional" (5200) account.
     */
    public static function expenseDebitAccountId($expenseCategoryId)
    {
        if ($expenseCategoryId) {
            $category = ExpenseCategory::find($expenseCategoryId);
            if ($category && $category->chart_of_account_id) {
                return $category->chart_of_account_id;
            }
        }
        return self::accountIdByCode(self::BEBAN_OPERASIONAL);
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

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
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
        if ($amount <= 0 || !$debitAccountId || !$creditAccountId) {
            return null;
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
