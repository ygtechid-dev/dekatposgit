<?php

namespace App\Http\Controllers;

use App\Models\MoneyTransfer;
use App\Models\Account;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Auth;
use App\Services\JournalService;
use App\Traits\FileHandleTrait;
use App\Traits\MoneyInput;

class MoneyTransferController extends Controller
{
    use FileHandleTrait, MoneyInput;

    private function storeDocument($document)
    {
        $directory = public_path('documents/money_transfer');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $extension = strtolower($document->getClientOriginalExtension());
        $documentName = date('YmdHis') . '-' . uniqid() . '.' . $extension;
        $document->move($directory, $documentName);

        return $documentName;
    }

    public function index()
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('money-transfer')){
            $lims_money_transfer_all = MoneyTransfer::with('fromAccount.chartOfAccount', 'toAccount.chartOfAccount')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get();
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
        $request->validate([
            'from_account_id' => 'required|different:to_account_id',
            'to_account_id' => 'required',
            'amount' => 'required',
            'note' => 'nullable|string|max:5000',
            'document' => 'nullable|file|mimes:jpg,jpeg,png,gif,pdf,csv,doc,docx,xls,xlsx,txt|max:10240',
        ]);

        $data = $this->normalizeMoneyInput($request->only([
            'from_account_id', 'to_account_id', 'amount', 'note',
        ]));
        if ($request->hasFile('document')) {
            $data['document'] = $this->storeDocument($request->file('document'));
        }
        $data['reference_no'] = 'mtr-' . date("Ymd") . '-'. date("his");
        $lims_money_transfer_data = MoneyTransfer::create($data);

        //auto-post journal: transfer between cash/bank accounts (Dr to_account, Cr from_account)
        try {
            $to_id = JournalService::coaIdForCashAccount($lims_money_transfer_data->to_account_id);
            $from_id = JournalService::coaIdForCashAccount($lims_money_transfer_data->from_account_id);
            $description = 'Transfer dana ' . $lims_money_transfer_data->reference_no;
            if ($lims_money_transfer_data->note) {
                $description .= ' - ' . $lims_money_transfer_data->note;
            }
            JournalService::postSimple(now(), $description, $to_id, $from_id, $lims_money_transfer_data->amount, 'money_transfer', $lims_money_transfer_data->id);
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
        $request->validate([
            'from_account_id' => 'required|different:to_account_id',
            'to_account_id' => 'required',
            'amount' => 'required',
            'note' => 'nullable|string|max:5000',
            'document' => 'nullable|file|mimes:jpg,jpeg,png,gif,pdf,csv,doc,docx,xls,xlsx,txt|max:10240',
        ]);

        $data = $this->normalizeMoneyInput($request->only([
            'from_account_id', 'to_account_id', 'amount', 'note',
        ]));
        $lims_money_transfer_data = MoneyTransfer::findOrFail($request->input('id'));
        if ($request->hasFile('document')) {
            $oldDocument = $lims_money_transfer_data->document;
            $data['document'] = $this->storeDocument($request->file('document'));
            $this->fileDelete('documents/money_transfer/', $oldDocument);
        }
        $lims_money_transfer_data->update($data);

        //auto-post journal: reverse old transfer entry and repost with updated values
        try {
            JournalService::reverseForReference('money_transfer', $lims_money_transfer_data->id);
            $to_id = JournalService::coaIdForCashAccount($lims_money_transfer_data->to_account_id);
            $from_id = JournalService::coaIdForCashAccount($lims_money_transfer_data->from_account_id);
            $description = 'Update transfer dana ' . $lims_money_transfer_data->reference_no;
            if ($lims_money_transfer_data->note) {
                $description .= ' - ' . $lims_money_transfer_data->note;
            }
            JournalService::postSimple(now(), $description, $to_id, $from_id, $lims_money_transfer_data->amount, 'money_transfer', $lims_money_transfer_data->id);
        } catch (\Exception $e) {}

        return redirect()->back()->with('message', 'Money transfer updated successfully');
    }

    public function destroy($id)
    {
        try {
            JournalService::reverseForReference('money_transfer', $id);
        } catch (\Exception $e) {}
        $money_transfer = MoneyTransfer::find($id);
        if ($money_transfer) {
            $this->fileDelete('documents/money_transfer/', $money_transfer->document);
            $money_transfer->delete();
        }
        return redirect()->back()->with('not_permitted', 'Data deleted successfully');
    }
}
