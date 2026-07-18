<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
use App\Models\Account;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Auth;

class ChartOfAccountController extends Controller
{
    public function index()
    {
        $role = Role::find(Auth::user()->role_id);
        if ($role->hasPermissionTo('chart-of-account-index')) {
            $lims_coa_all = ChartOfAccount::where('is_active', true)->orderBy('code')->get();
            return view('backend.chart_of_account.index', compact('lims_coa_all'));
        } else {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'code' => 'required|max:255|unique:chart_of_accounts,code',
            'name' => 'required|max:255',
            'type' => 'required|in:asset,liability,equity,revenue,expense',
            'normal_balance' => 'required|in:debit,credit',
        ]);

        $data = $request->all();
        $data['opening_balance'] = $data['opening_balance'] ?? 0;
        $data['parent_id'] = $data['parent_id'] ?? null;
        $data['is_default'] = 0;
        $data['is_active'] = true;
        $data['is_cash_account'] = $request->boolean('is_cash_account');
        $lims_coa_data = ChartOfAccount::create($data);

        $this->syncCashAccount($lims_coa_data);

        return redirect('chart-of-accounts')->with('message', 'Akun berhasil ditambahkan');
    }

    public function edit($id)
    {
        //
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'code' => ['required', 'max:255', Rule::unique('chart_of_accounts')->ignore($request->coa_id)],
            'name' => 'required|max:255',
            'type' => 'required|in:asset,liability,equity,revenue,expense',
            'normal_balance' => 'required|in:debit,credit',
        ]);

        $data = $request->all();
        $data['parent_id'] = $data['parent_id'] ?? null;
        $data['is_cash_account'] = $request->boolean('is_cash_account');
        $lims_coa_data = ChartOfAccount::find($data['coa_id']);
        $lims_coa_data->update($data);

        $this->syncCashAccount($lims_coa_data);

        return redirect('chart-of-accounts')->with('message', 'Akun berhasil diperbarui');
    }

    /**
     * Keep the legacy `accounts` (Daftar Akun) table in sync with a Chart of
     * Account that is flagged as a Kas/Bank account, so this single "Kode
     * Akun" screen is the only place needed to manage cash/bank accounts
     * (payments, expenses, payroll, money transfers, etc. keep working off
     * `accounts.id` under the hood, unchanged).
     */
    private function syncCashAccount(ChartOfAccount $coa)
    {
        $linked = Account::where('chart_of_account_id', $coa->id)->first();

        if (!$coa->is_cash_account || $coa->type != 'asset') {
            // No longer (or never) a cash/bank account: hide it from pickers
            // without deleting it, so historical transactions stay intact.
            if ($linked) {
                $linked->is_active = false;
                $linked->save();
            }
            return;
        }

        if ($linked) {
            $linked->account_no = $coa->code;
            $linked->name = $coa->name;
            $linked->initial_balance = $coa->opening_balance;
            $linked->note = $coa->description;
            $linked->is_active = true;
            $linked->save();
            return;
        }

        $hasDefault = Account::where('is_active', true)->where('is_default', true)->exists();
        Account::create([
            'account_no' => $coa->code,
            'name' => $coa->name,
            'initial_balance' => $coa->opening_balance,
            'total_balance' => $coa->opening_balance,
            'note' => $coa->description,
            'is_default' => $hasDefault ? 0 : 1,
            'is_active' => true,
            'chart_of_account_id' => $coa->id,
        ]);
    }

    public function destroy($id)
    {
        $lims_coa_data = ChartOfAccount::find($id);
        if (!$lims_coa_data) {
            return redirect('chart-of-accounts')->with('not_permitted', 'Akun tidak ditemukan');
        }
        if ($lims_coa_data->is_default) {
            return redirect('chart-of-accounts')->with('not_permitted', 'Akun default tidak dapat dihapus');
        }
        if ($lims_coa_data->journalDetails()->exists()) {
            return redirect('chart-of-accounts')->with('not_permitted', 'Akun sudah memiliki transaksi jurnal dan tidak dapat dihapus');
        }
        $lims_coa_data->is_active = false;
        $lims_coa_data->save();
        return redirect('chart-of-accounts')->with('message', 'Akun berhasil dihapus');
    }

    public function chartOfAccountsAll()
    {
        $lims_coa_list = ChartOfAccount::where('is_active', true)->orderBy('code')->get();
        $html = '';
        foreach ($lims_coa_list as $coa) {
            $html .= '<option value="' . $coa->id . '">' . $coa->code . ' - ' . $coa->name . '</option>';
        }
        return response()->json($html);
    }
}
