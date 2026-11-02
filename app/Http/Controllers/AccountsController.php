<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Collection;
use App\Models\Account;
use App\Models\Payment;
use App\Models\Returns;
use App\Models\ReturnPurchase;
use App\Models\Expense;
use App\Models\Payroll;
use App\Models\MoneyTransfer;
use App\Models\ChartOfAccount;
use App\Models\JournalDetail;
use DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Auth;
use App\Services\JournalService;


class AccountsController extends Controller
{
    public function index()
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('account-index')){
            $lims_account_all = Account::where('is_active', true)->get();
            return view('backend.account.index', compact('lims_account_all'));
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'account_no' => [
                'max:255',
                    Rule::unique('accounts')->where(function ($query) {
                    return $query->where('is_active', 1);
                }),
            ],
        ]);

        $lims_account_data = Account::where('is_active', true)->first();
        $data = $request->all();
        if($data['initial_balance'])
            $data['total_balance'] = $data['initial_balance'];
        else
            $data['total_balance'] = 0;
        if(!$lims_account_data)
            $data['is_default'] = 1;
        $data['is_active'] = true;
        Account::create($data);
        return redirect('accounts')->with('message', 'Account created successfully');
    }

    public function makeDefault($id)
    {
        $lims_account_data = Account::where('is_default', true)->first();
        $lims_account_data->is_default = false;
        $lims_account_data->save();

        $lims_account_data = Account::find($id);
        $lims_account_data->is_default = true;
        $lims_account_data->save();

        return 'Account set as default successfully';
    }

    public function edit($id)
    {
        //
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'account_no' => [
                'max:255',
                    Rule::unique('accounts')->ignore($request->account_id)->where(function ($query) {
                    return $query->where('is_active', 1);
                }),
            ],
        ]);

        $data = $request->all();
        $lims_account_data = Account::find($data['account_id']);
        if($data['initial_balance'])
            $data['total_balance'] = $data['initial_balance'];
        else
            $data['total_balance'] = 0;
        $lims_account_data->update($data);
        return redirect('accounts')->with('message', 'Account updated successfully');
    }

    public function balanceSheet(Request $request)
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('balance-sheet')){
            $end_date = $request->input('end_date', date('Y-m-d'));
            $this->syncPayrollJournals($end_date);

            // Aktiva/Pasiva/Modal are now derived from the Chart of Accounts + Jurnal Umum
            // (same balance calculation as trialBalance()) so the Neraca always stays in
            // sync with journal postings/reversals, instead of re-deriving figures from
            // legacy Payment/Returns/Expense/Payroll/MoneyTransfer tables independently.
            Account::where('is_active', true)->get()->each(function($account) {
                JournalService::coaIdForCashAccount($account->id);
            });
            $lims_coa_all = ChartOfAccount::where('is_active', true)->orderBy('code')->get();

            $assets = [];
            $liabilities = [];
            $equity_items = [];
            $total_assets = 0;
            $total_liabilities = 0;
            $total_equity_accounts = 0;
            $total_revenue = 0;
            $total_expense = 0;

            foreach($lims_coa_all as $coa) {
                $sums = JournalDetail::join('journals', 'journals.id', '=', 'journal_details.journal_id')
                            ->where('journal_details.chart_of_account_id', $coa->id)
                            ->whereDate('journals.journal_date', '<=', $end_date)
                            ->selectRaw('COALESCE(SUM(debit),0) as total_debit, COALESCE(SUM(credit),0) as total_credit')
                            ->first();

                $isDebitNormal = $coa->normal_balance == 'debit';
                $movement = $isDebitNormal
                            ? ($sums->total_debit - $sums->total_credit)
                            : ($sums->total_credit - $sums->total_debit);
                $balance = (float)$coa->opening_balance + $movement;

                if(abs($balance) < 0.005)
                    continue;

                switch($coa->type) {
                    case 'asset':
                        $assets[] = ['name' => $coa->code . ' - ' . $coa->name, 'amount' => $balance];
                        $total_assets += $balance;
                        break;
                    case 'liability':
                        $liabilities[] = ['name' => $coa->code . ' - ' . $coa->name, 'amount' => $balance];
                        $total_liabilities += $balance;
                        break;
                    case 'equity':
                        $equity_items[] = ['name' => $coa->code . ' - ' . $coa->name, 'amount' => $balance];
                        $total_equity_accounts += $balance;
                        break;
                    // Akun kontra (Diskon Pembelian 5410 & Retur Pembelian 5400 bersaldo
                    // normal kredit, Diskon/Retur Penjualan 4200-4300 bersaldo normal debet)
                    // harus MENGURANGI kelompoknya, bukan menambah. Jadi kontribusinya
                    // dihitung memakai arah alami tipe akun, bukan arah saldo normalnya:
                    // pendapatan = kredit - debet, beban = debet - kredit.
                    case 'revenue':
                        $total_revenue += $isDebitNormal ? -$balance : $balance;
                        break;
                    case 'expense':
                        $total_expense += $isDebitNormal ? $balance : -$balance;
                        break;
                }
            }

            // Revenue/expense accounts are temporary equity accounts: fold the running
            // net income for the period into Modal/Ekuitas so Aktiva = Pasiva + Modal.
            $net_income = $total_revenue - $total_expense;
            if(abs($net_income) >= 0.005)
                $equity_items[] = ['name' => 'Laba / Rugi Berjalan', 'amount' => $net_income];
            $equity = $total_equity_accounts + $net_income;

            // Detail Saldo Akun now reads from journal_details too, so Kas Kecil,
            // Kas Besar, bank accounts, etc. move exactly like Buku Besar/Neraca.
            $lims_account_list = Account::with('chartOfAccount')->where('is_active', true)->get();
            $account_rows = [];
            foreach ($lims_account_list as $account) {
                $coa = $account->chartOfAccount;
                if(!$coa)
                    continue;
                $sums = JournalDetail::join('journals', 'journals.id', '=', 'journal_details.journal_id')
                            ->where('journal_details.chart_of_account_id', $coa->id)
                            ->whereDate('journals.journal_date', '<=', $end_date)
                            ->selectRaw('COALESCE(SUM(debit),0) as total_debit, COALESCE(SUM(credit),0) as total_credit')
                            ->first();
                $debit = (float)$coa->opening_balance + (float)$sums->total_debit;
                $credit = (float)$sums->total_credit;
                $balance = $debit - $credit;
                if(abs($balance) < 0.005)
                    continue;

                $account_rows[] = compact('account', 'debit', 'credit', 'balance');
            }

            return view('backend.account.balance_sheet', compact('account_rows', 'assets', 'liabilities', 'equity_items', 'total_assets', 'total_liabilities', 'equity', 'end_date'));
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    private function journalBalanceByCode($code, $start_date, $end_date)
    {
        $accounts = ChartOfAccount::where('code', $code)
            ->orWhere('code', 'like', $code . '.%')
            ->get();
        if($accounts->isEmpty())
            return 0;

        $total = 0;
        foreach($accounts as $coa) {
            $sums = JournalDetail::join('journals', 'journals.id', '=', 'journal_details.journal_id')
                        ->where('journal_details.chart_of_account_id', $coa->id)
                        ->whereDate('journals.journal_date', '>=', $start_date)
                        ->whereDate('journals.journal_date', '<=', $end_date)
                        ->selectRaw('COALESCE(SUM(debit),0) as total_debit, COALESCE(SUM(credit),0) as total_credit')
                        ->first();
            $total += $coa->normal_balance == 'debit'
                ? (float)($sums->total_debit - $sums->total_credit)
                : (float)($sums->total_credit - $sums->total_debit);
        }

        return $total;
    }

    private function journalBalanceByType($type, $start_date, $end_date)
    {
        $total = 0;
        $accounts = ChartOfAccount::where('type', $type)->where('is_active', true)->get();
        foreach($accounts as $coa) {
            $sums = JournalDetail::join('journals', 'journals.id', '=', 'journal_details.journal_id')
                        ->where('journal_details.chart_of_account_id', $coa->id)
                        ->whereDate('journals.journal_date', '>=', $start_date)
                        ->whereDate('journals.journal_date', '<=', $end_date)
                        ->selectRaw('COALESCE(SUM(debit),0) as total_debit, COALESCE(SUM(credit),0) as total_credit')
                        ->first();
            // Pakai arah alami tipe akun supaya akun kontra ikut mengurangi total:
            // beban = debet - kredit (Diskon/Retur Pembelian bersaldo kredit -> negatif),
            // pendapatan = kredit - debet (Diskon/Retur Penjualan bersaldo debet -> negatif).
            $total += $type == 'revenue' || $type == 'liability' || $type == 'equity'
                ? (float)($sums->total_credit - $sums->total_debit)
                : (float)($sums->total_debit - $sums->total_credit);
        }
        return $total;
    }

    private function profitLossAccountRows($type, $start_date, $end_date, $prefixes = [], $excludePrefixes = [])
    {
        $rows = [];
        $accounts = ChartOfAccount::where('type', $type)->where('is_active', true)->orderBy('code')->get();
        foreach($accounts as $coa) {
            $code = (string)$coa->code;
            if($prefixes) {
                $matched = false;
                foreach($prefixes as $prefix) {
                    if($code === $prefix || strpos($code, $prefix . '.') === 0) {
                        $matched = true;
                        break;
                    }
                }
                if(!$matched)
                    continue;
            }
            $excluded = false;
            foreach($excludePrefixes as $prefix) {
                if($code === $prefix || strpos($code, $prefix . '.') === 0) {
                    $excluded = true;
                    break;
                }
            }
            if($excluded)
                continue;

            $sums = JournalDetail::join('journals', 'journals.id', '=', 'journal_details.journal_id')
                        ->where('journal_details.chart_of_account_id', $coa->id)
                        ->whereDate('journals.journal_date', '>=', $start_date)
                        ->whereDate('journals.journal_date', '<=', $end_date)
                        ->selectRaw('COALESCE(SUM(debit),0) as total_debit, COALESCE(SUM(credit),0) as total_credit')
                        ->first();

            $amount = $type == 'revenue'
                ? (float)($sums->total_credit - $sums->total_debit)
                : (float)($sums->total_debit - $sums->total_credit);

            if(abs($amount) < 0.005)
                continue;

            $rows[] = [
                'coa' => $coa,
                'amount' => $amount,
            ];
        }

        return $rows;
    }

    private function operationalExpenseBreakdown($start_date, $end_date, $coaId, $coaAmount)
    {
        $rows = [];
        $expenses = Expense::leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->whereDate('expenses.created_at', '>=', $start_date)
            ->whereDate('expenses.created_at', '<=', $end_date)
            ->where(function($query) use ($coaId) {
                $query->whereNull('expense_categories.chart_of_account_id')
                    ->orWhere('expense_categories.chart_of_account_id', $coaId);
            })
            ->selectRaw('COALESCE(expense_categories.code, "-") as category_code, COALESCE(expense_categories.name, "Tanpa Kategori") as category_name, COALESCE(SUM(expenses.amount), 0) as amount')
            ->groupBy('expense_categories.id', 'expense_categories.code', 'expense_categories.name')
            ->orderBy('expense_categories.code')
            ->get();

        foreach($expenses as $expense) {
            if(abs((float)$expense->amount) < 0.005)
                continue;
            $rows[] = [
                'label' => trim($expense->category_code . ' - ' . $expense->category_name, ' -'),
                'amount' => (float)$expense->amount,
            ];
        }

        $detailTotal = array_sum(array_column($rows, 'amount'));
        $manualAmount = (float)$coaAmount - $detailTotal;
        if(abs($manualAmount) >= 0.005) {
            $rows[] = [
                'label' => 'Jurnal manual / lainnya',
                'amount' => $manualAmount,
            ];
        }

        return $rows;
    }

    public function profitLoss(Request $request)
    {
        $role = Role::find(Auth::user()->role_id);
        if(!$role->hasPermissionTo('profit-loss'))
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');

        $start_date = $request->input('start_date', date('Y-m-01'));
        $end_date = $request->input('end_date', date('Y-m-d'));
        $this->syncPayrollJournals($end_date);

        $revenue_rows = $this->profitLossAccountRows('revenue', $start_date, $end_date);
        $hpp_rows = $this->profitLossAccountRows('expense', $start_date, $end_date, [JournalService::HPP]);
        $expense_rows = $this->profitLossAccountRows('expense', $start_date, $end_date, [], [JournalService::HPP]);
        foreach($expense_rows as $key => $row) {
            if($row['coa']->code == JournalService::BEBAN_OPERASIONAL) {
                $expense_rows[$key]['children'] = $this->operationalExpenseBreakdown($start_date, $end_date, $row['coa']->id, $row['amount']);
            }
        }

        $total_revenue = array_sum(array_column($revenue_rows, 'amount'));
        $cost_of_goods_sold = array_sum(array_column($hpp_rows, 'amount'));
        $gross_profit = $total_revenue - $cost_of_goods_sold;
        $total_expenses = array_sum(array_column($expense_rows, 'amount'));
        $net_profit = $gross_profit - $total_expenses;

        return view('backend.account.profit_loss', compact('start_date', 'end_date', 'revenue_rows', 'hpp_rows', 'expense_rows', 'total_revenue', 'cost_of_goods_sold', 'gross_profit', 'total_expenses', 'net_profit'));
    }

    private function buildLedgerForAccount($coa, $start_date, $end_date)
    {
        $isDebitNormal = $coa->normal_balance == 'debit';
        $legacyAccount = Account::where('chart_of_account_id', $coa->id)->first();

        // balance carried forward from opening_balance + everything before start_date
        $prior = JournalDetail::join('journals', 'journals.id', '=', 'journal_details.journal_id')
                    ->where('journal_details.chart_of_account_id', $coa->id)
                    ->whereDate('journals.journal_date', '<', $start_date)
                    ->selectRaw('COALESCE(SUM(debit),0) as total_debit, COALESCE(SUM(credit),0) as total_credit')
                    ->first();
        $priorMovement = $isDebitNormal
                        ? ($prior->total_debit - $prior->total_credit)
                        : ($prior->total_credit - $prior->total_debit);
        if($legacyAccount) {
            $priorMovement += $this->legacyCashAccountMovement($legacyAccount->id, null, date('Y-m-d', strtotime($start_date . ' -1 day')), $isDebitNormal);
        }
        $opening_balance = (float)$coa->opening_balance + $priorMovement;

        $details = JournalDetail::join('journals', 'journals.id', '=', 'journal_details.journal_id')
                    ->where('journal_details.chart_of_account_id', $coa->id)
                    ->whereDate('journals.journal_date', '>=', $start_date)
                    ->whereDate('journals.journal_date', '<=', $end_date)
                    ->orderBy('journals.journal_date')
                    ->orderBy('journal_details.id')
                    ->select('journal_details.*', 'journals.journal_no', 'journals.journal_date', 'journals.description as journal_description')
                    ->get();

        $ledger_rows = [];
        foreach($details as $d) {
            $ledger_rows[] = [
                'date' => $d->journal_date,
                'journal_no' => $d->journal_no,
                'description' => $d->note ?: $d->journal_description,
                'debit' => $d->debit,
                'credit' => $d->credit,
                'sort_id' => $d->id,
                'source' => 'journal',
            ];
        }

        if($legacyAccount) {
            $ledger_rows = array_merge($ledger_rows, $this->legacyCashAccountRows($legacyAccount->id, $start_date, $end_date));
        }

        usort($ledger_rows, function($a, $b) {
            $dateCompare = strcmp((string)$a['date'], (string)$b['date']);
            if($dateCompare !== 0)
                return $dateCompare;
            if(($a['source'] ?? '') === ($b['source'] ?? ''))
                return ($a['sort_id'] ?? 0) <=> ($b['sort_id'] ?? 0);
            return ($a['source'] ?? '') === 'journal' ? -1 : 1;
        });

        $running = $opening_balance;
        foreach($ledger_rows as $key => $row) {
            $running += $isDebitNormal ? ((float)$row['debit'] - (float)$row['credit']) : ((float)$row['credit'] - (float)$row['debit']);
            $ledger_rows[$key]['balance'] = $running;
        }
        $closing_balance = $running;

        return [
            'coa' => $coa,
            'opening_balance' => $opening_balance,
            'closing_balance' => $closing_balance,
            'ledger_rows' => $ledger_rows,
        ];
    }

    private function journalExistsForReference($referenceType, $referenceId)
    {
        if(!$referenceId)
            return false;
        return DB::table('journals')
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->exists();
    }

    private function legacyCashAccountRows($accountId, $start_date, $end_date)
    {
        $rows = [];
        $add = function($type, $id, $date, $journalNo, $description, $debit, $credit) use (&$rows) {
            if($this->journalExistsForReference($type, $id))
                return;
            $rows[] = [
                'date' => $date,
                'journal_no' => $journalNo,
                'description' => $description,
                'debit' => (float)$debit,
                'credit' => (float)$credit,
                'sort_id' => (int)$id,
                'source' => 'legacy',
            ];
        };

        Payment::where('account_id', $accountId)
            ->whereDate('created_at', '>=', $start_date)
            ->whereDate('created_at', '<=', $end_date)
            ->whereNull('cash_register_id')
            ->get()
            ->each(function($payment) use ($add) {
                if($payment->sale_id) {
                    $add('sale_payment', $payment->id, $payment->created_at, $payment->payment_reference, 'Pembayaran penjualan ' . $payment->payment_reference, $payment->amount, 0);
                }
                elseif($payment->purchase_id) {
                    $add('purchase_payment', $payment->id, $payment->created_at, $payment->payment_reference, 'Pembayaran pembelian ' . $payment->payment_reference, 0, $payment->amount);
                }
            });

        MoneyTransfer::where('to_account_id', $accountId)
            ->whereDate('created_at', '>=', $start_date)
            ->whereDate('created_at', '<=', $end_date)
            ->get()
            ->each(function($transfer) use ($add) {
                $add('money_transfer', $transfer->id, $transfer->created_at, $transfer->reference_no, 'Transfer masuk ' . $transfer->reference_no, $transfer->amount, 0);
            });

        MoneyTransfer::where('from_account_id', $accountId)
            ->whereDate('created_at', '>=', $start_date)
            ->whereDate('created_at', '<=', $end_date)
            ->get()
            ->each(function($transfer) use ($add) {
                $add('money_transfer', $transfer->id, $transfer->created_at, $transfer->reference_no, 'Transfer keluar ' . $transfer->reference_no, 0, $transfer->amount);
            });

        ReturnPurchase::where('account_id', $accountId)
            ->whereDate('created_at', '>=', $start_date)
            ->whereDate('created_at', '<=', $end_date)
            ->get()
            ->each(function($returnPurchase) use ($add) {
                $add('return_purchase', $returnPurchase->id, $returnPurchase->created_at, $returnPurchase->reference_no, 'Retur pembelian ' . $returnPurchase->reference_no, $returnPurchase->grand_total, 0);
            });

        Returns::where('account_id', $accountId)
            ->whereDate('created_at', '>=', $start_date)
            ->whereDate('created_at', '<=', $end_date)
            ->get()
            ->each(function($return) use ($add) {
                $add('return', $return->id, $return->created_at, $return->reference_no, 'Retur penjualan ' . $return->reference_no, 0, $return->grand_total);
            });

        Expense::where('account_id', $accountId)
            ->whereDate('created_at', '>=', $start_date)
            ->whereDate('created_at', '<=', $end_date)
            ->get()
            ->each(function($expense) use ($add) {
                $add('expense', $expense->id, $expense->created_at, $expense->reference_no, 'Pengeluaran ' . $expense->reference_no, 0, $expense->amount);
            });

        Payroll::where('account_id', $accountId)
            ->whereDate('created_at', '>=', $start_date)
            ->whereDate('created_at', '<=', $end_date)
            ->get()
            ->each(function($payroll) use ($add) {
                $add('payroll', $payroll->id, $payroll->created_at, $payroll->reference_no, 'Payroll ' . $payroll->reference_no, 0, $payroll->amount);
            });

        return $rows;
    }

    private function legacyCashAccountMovement($accountId, $start_date, $end_date, $isDebitNormal)
    {
        if(!$end_date)
            return 0;

        $rows = $this->legacyCashAccountRows($accountId, $start_date ?: '1970-01-01', $end_date);
        return array_reduce($rows, function($carry, $row) use ($isDebitNormal) {
            return $carry + ($isDebitNormal ? ((float)$row['debit'] - (float)$row['credit']) : ((float)$row['credit'] - (float)$row['debit']));
        }, 0);
    }

    public function generalLedger(Request $request)
    {
        $role = Role::find(Auth::user()->role_id);
        if(!$role->hasPermissionTo('general-ledger'))
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');

        $end_date = $request->input('end_date', date('Y-m-d'));
        $this->syncPayrollJournals($end_date);

        $lims_coa_all = ChartOfAccount::where('is_active', true)->orderBy('code')->get();
        $start_date = $request->input('start_date', date('Y-m-01'));
        $chart_of_account_id = $request->input('chart_of_account_id');

        $all_ledgers = [];

        if($chart_of_account_id === 'all') {
            foreach($lims_coa_all as $coa) {
                $all_ledgers[] = $this->buildLedgerForAccount($coa, $start_date, $end_date);
            }
        }
        elseif($chart_of_account_id) {
            $lims_coa_data = ChartOfAccount::find($chart_of_account_id);
            if($lims_coa_data) {
                $all_ledgers[] = $this->buildLedgerForAccount($lims_coa_data, $start_date, $end_date);
            }
        }

        return view('backend.account.general_ledger', compact('lims_coa_all', 'start_date', 'end_date', 'chart_of_account_id', 'all_ledgers'));
    }

    public function trialBalance(Request $request)
    {
        $role = Role::find(Auth::user()->role_id);
        if(!$role->hasPermissionTo('trial-balance'))
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');

        $end_date = $request->input('end_date', date('Y-m-d'));
        $this->syncPayrollJournals($end_date);

        $lims_coa_all = ChartOfAccount::where('is_active', true)->orderBy('code')->get();
        $rows = [];
        $total_debit = 0;
        $total_credit = 0;

        foreach($lims_coa_all as $coa) {
            $sums = JournalDetail::join('journals', 'journals.id', '=', 'journal_details.journal_id')
                        ->where('journal_details.chart_of_account_id', $coa->id)
                        ->whereDate('journals.journal_date', '<=', $end_date)
                        ->selectRaw('COALESCE(SUM(debit),0) as total_debit, COALESCE(SUM(credit),0) as total_credit')
                        ->first();

            $isDebitNormal = $coa->normal_balance == 'debit';
            $movement = $isDebitNormal
                        ? ($sums->total_debit - $sums->total_credit)
                        : ($sums->total_credit - $sums->total_debit);
            $balance = (float)$coa->opening_balance + $movement;

            if(abs($balance) < 0.005)
                continue;

            $debit_col = $isDebitNormal ? max($balance, 0) : max(-$balance, 0);
            $credit_col = $isDebitNormal ? max(-$balance, 0) : max($balance, 0);

            $total_debit += $debit_col;
            $total_credit += $credit_col;

            $rows[] = [
                'code' => $coa->code,
                'name' => $coa->name,
                'debit' => $debit_col,
                'credit' => $credit_col,
            ];
        }

        return view('backend.account.trial_balance', compact('rows', 'end_date', 'total_debit', 'total_credit'));
    }

    private function syncPayrollJournals($end_date = null)
    {
        $query = Payroll::where('amount', '>', 0)->orderBy('id');
        if($end_date) {
            $query->whereDate('created_at', '<=', $end_date);
        }

        $query->get()->each(function($payroll) {
            if($this->journalExistsForReference('payroll', $payroll->id))
                return;
            JournalService::postPayroll($payroll);
        });
    }

    public function accountStatement(Request $request)
    {
        $data = $request->all();
        //return $data;
        $lims_account_data = Account::with('chartOfAccount')->find($data['account_id']);
        $credit_list = new Collection;
        $debit_list = new Collection;
        $expense_list = new Collection;
        $return_list = new Collection;
        $purchase_return_list = new Collection;
        $payroll_list = new Collection;
        $recieved_money_transfer_list = new Collection;
        $sent_money_transfer_list = new Collection;

        if($data['type'] == '0' || $data['type'] == '2') {
            $credit_list = Payment::whereNotNull('sale_id')
                            ->where('account_id', $data['account_id'])
                            ->whereDate('created_at', '>=' , $data['start_date'])
                            ->whereDate('created_at', '<=' , $data['end_date'])
                            ->select('payment_reference as reference_no', 'sale_id', 'amount', 'created_at')
                            ->get();

            $recieved_money_transfer_list = MoneyTransfer::where('to_account_id', $data['account_id'])
                                            ->whereDate('created_at', '>=' , $data['start_date'])
                                            ->whereDate('created_at', '<=' , $data['end_date'])
                                            ->select('reference_no', 'to_account_id', 'amount', 'created_at')
                                            ->get();
            $purchase_return_list = ReturnPurchase::where('account_id', $data['account_id'])
                                    ->whereDate('created_at', '>=' , $data['start_date'])
                                    ->whereDate('created_at', '<=' , $data['end_date'])
                                    ->select('reference_no', 'grand_total as amount', 'created_at')
                                    ->get();
        }
        if($data['type'] == '0' || $data['type'] == '1') {
            $debit_list = Payment::whereNotNull('purchase_id')
                            ->where('account_id', $data['account_id'])
                            ->whereDate('created_at', '>=' , $data['start_date'])
                            ->whereDate('created_at', '<=' , $data['end_date'])
                            ->select('payment_reference as reference_no', 'purchase_id', 'amount', 'created_at')
                            ->get();
            $expense_list = Expense::where('account_id', $data['account_id'])
                            ->whereDate('created_at', '>=' , $data['start_date'])
                            ->whereDate('created_at', '<=' , $data['end_date'])
                            ->select('reference_no', 'amount', 'created_at')
                            ->get();
            $return_list = Returns::where('account_id', $data['account_id'])
                            ->whereDate('created_at', '>=' , $data['start_date'])
                            ->whereDate('created_at', '<=' , $data['end_date'])
                            ->select('reference_no', 'grand_total as amount', 'created_at')
                            ->get();
            $payroll_list = Payroll::where('account_id', $data['account_id'])
                            ->whereDate('created_at', '>=' , $data['start_date'])
                            ->whereDate('created_at', '<=' , $data['end_date'])
                            ->select('reference_no', 'amount', 'created_at')
                            ->get();
            $sent_money_transfer_list = MoneyTransfer::where('from_account_id', $data['account_id'])
                                        ->whereDate('created_at', '>=' , $data['start_date'])
                                        ->whereDate('created_at', '<=' , $data['end_date'])
                                        ->select('reference_no', 'to_account_id', 'amount', 'created_at')
                                        ->get();
        }
        $all_transaction_list = new Collection;
        $all_transaction_list = $credit_list->concat($recieved_money_transfer_list)
                                ->concat($debit_list)
                                ->concat($expense_list)
                                ->concat($return_list)
                                ->concat($purchase_return_list)
                                ->concat($payroll_list)
                                ->concat($sent_money_transfer_list)
                                ->sortByDesc('created_at');
        $balance = 0;
        return view('backend.account.account_statement', compact('lims_account_data', 'all_transaction_list', 'balance'));
    }

    public function destroy($id)
    {
        if(!env('USER_VERIFIED'))
            return redirect()->back()->with('not_permitted', 'This feature is disable for demo!');
        $lims_account_data = Account::find($id);
        if(!$lims_account_data->is_default){
            $lims_account_data->is_active = false;
            $lims_account_data->save();
            return redirect('accounts')->with('not_permitted', 'Account deleted successfully!');
        }
        else
            return redirect('accounts')->with('not_permitted', 'Please make another account default first!');
    }

    public function accountsAll()
    {
        $lims_account_list = Account::with('chartOfAccount')->where('is_active', true)->get();

        $html = '';
        foreach($lims_account_list as $account){
            if($account->is_default == 1){
                $html .='<option selected value="'.$account->id.'">'.$account->coa_label.'</option>';
            }else{
                $html .='<option value="'.$account->id.'">'.$account->coa_label.'</option>';
            }
        }

        return response()->json($html);
    }
}
