<?php

namespace App\Http\Controllers;

use App\Models\MoneyTransfer;
use App\Models\Account;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Auth;
use App\Services\JournalService;

class MoneyTransferController extends Controller
{
    public function index()
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('money-transfer')){
            $lims_money_transfer_all = MoneyTransfer::with('fromAccount.chartOfAccount', 'toAccount.chartOfAccount')->get();
            $lims_account_list = Account::with('chartOfAccount')->where('is_active', true)->get();
            return view('backend.money_transfer.index', compact('lims_money_transfer_all', 'lims_account_list'));
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
        $data = $request->all();
        $data['reference_no'] = 'mtr-' . date("Ymd") . '-'. date("his");
        $lims_money_transfer_data = MoneyTransfer::create($data);

        //auto-post journal: transfer between cash/bank accounts (Dr to_account, Cr from_account)
        try {
            $to_id = JournalService::coaIdForCashAccount($lims_money_transfer_data->to_account_id);
            $from_id = JournalService::coaIdForCashAccount($lims_money_transfer_data->from_account_id);
            JournalService::postSimple(now(), 'Transfer dana ' . $lims_money_transfer_data->reference_no, $to_id, $from_id, $lims_money_transfer_data->amount, 'money_transfer', $lims_money_transfer_data->id);
        } catch (\Exception $e) {}

        return redirect()->back()->with('message', 'Money transfered successfully');
    }

    public function show(MoneyTransfer $moneyTransfer)
    {
        //
    }

    public function edit(MoneyTransfer $moneyTransfer)
    {
        //
    }

    public function update(Request $request, $id)
    {
        $data = $request->all();
        $lims_money_transfer_data = MoneyTransfer::find($data['id']);
        $lims_money_transfer_data->update($data);

        //auto-post journal: reverse old transfer entry and repost with updated values
        try {
            JournalService::reverseForReference('money_transfer', $lims_money_transfer_data->id);
            $to_id = JournalService::coaIdForCashAccount($lims_money_transfer_data->to_account_id);
            $from_id = JournalService::coaIdForCashAccount($lims_money_transfer_data->from_account_id);
            JournalService::postSimple(now(), 'Update transfer dana ' . $lims_money_transfer_data->reference_no, $to_id, $from_id, $lims_money_transfer_data->amount, 'money_transfer', $lims_money_transfer_data->id);
        } catch (\Exception $e) {}

        return redirect()->back()->with('message', 'Money transfer updated successfully');
    }

    public function destroy($id)
    {
        try {
            JournalService::reverseForReference('money_transfer', $id);
        } catch (\Exception $e) {}
        MoneyTransfer::find($id)->delete();
        return redirect()->back()->with('not_permitted', 'Data deleted successfully');
    }
}
