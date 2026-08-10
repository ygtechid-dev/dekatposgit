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

            $linked_cash_coa_ids = Account::where('is_active', true)
                ->whereNotNull('chart_of_account_id')
                ->pluck('chart_of_account_id')
                ->toArray();

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

                $shouldShowZeroAsset = $coa->type == 'asset'
                    && (in_array($coa->id, $linked_cash_coa_ids) || $coa->code == JournalService::PERSEDIAAN);

                if(abs($balance) < 0.005 && !$shouldShowZeroAsset)
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
            $equity_items[] = ['name' => 'Laba / Rugi Berjalan', 'amount' => $net_income];
            $equity = $total_equity_accounts + $net_income;

            // Detail Saldo Akun now reads from journal_details too, so Kas Kecil,
            // Kas Besar, bank accounts, etc. move exactly like Buku Besar/Neraca.
            $lims_account_list = Account::with('chartOfAccount')->where('is_active', true)->get();
            $account_debit = [];
            $account_credit = [];
            $account_balance = [];
            foreach ($lims_account_list as $account) {
                $coa = $account->chartOfAccount;
                if(!$coa) {
                    $account_debit[] = 0;
                    $account_credit[] = 0;
                    $account_balance[] = 0;
                    continue;
                }
                $sums = JournalDetail::join('journals', 'journals.id', '=', 'journal_details.journal_id')
                            ->where('journal_details.chart_of_account_id', $coa->id)
                            ->whereDate('journals.journal_date', '<=', $end_date)
                            ->selectRaw('COALESCE(SUM(debit),0) as total_debit, COALESCE(SUM(credit),0) as total_credit')
                            ->first();
                $account_debit[] = (float)$coa->opening_balance + (float)$sums->total_debit;
                $account_credit[] = (float)$sums->total_credit;
                $account_balance[] = ((float)$coa->opening_balance + (float)$sums->total_debit) - (float)$sums->total_credit;
            }

            return view('backend.account.balance_sheet', compact('lims_account_list', 'account_debit', 'account_credit', 'account_balance', 'assets', 'liabilities', 'equity_items', 'total_assets', 'total_liabilities', 'equity', 'end_date'));
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

    public function profitLoss(Request $request)
    {
        $role = Role::find(Auth::user()->role_id);
        if(!$role->hasPermissionTo('profit-loss'))
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');

        $start_date = $request->input('start_date', date('Y-m-01'));
        $end_date = $request->input('end_date', date('Y-m-d'));

        $gross_sales = $this->journalBalanceByCode('4100', $start_date, $end_date);
        $sale_returns = $this->journalBalanceByCode('4200', $start_date, $end_date);
        $order_discount = $this->journalBalanceByCode('4300', $start_date, $end_date);
        $order_tax = 0;
        $shipping_cost = 0;
        $grand_total = $gross_sales;
        $cost_of_goods_sold = $this->journalBalanceByCode('5100', $start_date, $end_date);
        $payrolls = $this->journalBalanceByCode('5300', $start_date, $end_date);
        $total_expense_accounts = $this->journalBalanceByType('expense', $start_date, $end_date);
        // Jangan di-clamp ke 0: potongan/retur pembelian bernilai negatif dan memang
        // harus mengurangi beban (menambah laba), bukan dibuang.
        $expenses = $total_expense_accounts - $cost_of_goods_sold - $payrolls;

        $net_sales = $gross_sales - $sale_returns - $order_discount;
        $gross_profit = $net_sales - $cost_of_goods_sold;
        $total_expenses = $expenses + $payrolls;
        $net_profit = $gross_profit - $total_expenses;

        return view('backend.account.profit_loss', compact('start_date', 'end_date', 'gross_sales', 'order_discount', 'order_tax', 'shipping_cost', 'grand_total', 'sale_returns', 'net_sales', 'cost_of_goods_sold', 'gross_profit', 'expenses', 'payrolls', 'total_expenses', 'net_profit'));
    }

    private function buildLedgerForAccount($coa, $start_date, $end_date)
    {
        $isDebitNormal = $coa->normal_balance == 'debit';

        // balance carried forward from opening_balance + everything before start_date
        $prior = JournalDetail::join('journals', 'journals.id', '=', 'journal_details.journal_id')
                    ->where('journal_details.chart_of_account_id', $coa->id)
                    ->whereDate('journals.journal_date', '<', $start_date)
                    ->selectRaw('COALESCE(SUM(debit),0) as total_debit, COALESCE(SUM(credit),0) as total_credit')
                    ->first();
        $priorMovement = $isDebitNormal
                        ? ($prior->total_debit - $prior->total_credit)
                        : ($prior->total_credit - $prior->total_debit);
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
        $running = $opening_balance;
        foreach($details as $d) {
            $running += $isDebitNormal ? ($d->debit - $d->credit) : ($d->credit - $d->debit);
            $ledger_rows[] = [
                'date' => $d->journal_date,
                'journal_no' => $d->journal_no,
                'description' => $d->note ?: $d->journal_description,
                'debit' => $d->debit,
                'credit' => $d->credit,
                'balance' => $running,
            ];
        }
        $closing_balance = $running;

        return [
            'coa' => $coa,
            'opening_balance' => $opening_balance,
            'closing_balance' => $closing_balance,
            'ledger_rows' => $ledger_rows,
        ];
    }

    public function generalLedger(Request $request)
    {
        $role = Role::find(Auth::user()->role_id);
        if(!$role->hasPermissionTo('general-ledger'))
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');

        $lims_coa_all = ChartOfAccount::where('is_active', true)->orderBy('code')->get();
        $start_date = $request->input('start_date', date('Y-m-01'));
        $end_date = $request->input('end_date', date('Y-m-d'));
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
