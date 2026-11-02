<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Account;
use App\Models\Employee;
use App\Models\Payroll;
use Auth;
use DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Mail\PayrollDetails;
use Mail;
use App\Models\MailSetting;
use App\Services\JournalService;
use App\Traits\FileHandleTrait;

class PayrollController extends Controller
{
    use \App\Traits\MailInfo, FileHandleTrait;

    private function storeDocument($document)
    {
        $directory = public_path('documents/payroll');
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
        if($role->hasPermissionTo('payroll')){
            $lims_account_list = Account::with('chartOfAccount')->where('is_active', true)->get();
            $lims_employee_list = Employee::where('is_active', true)->get();
            $general_setting = DB::table('general_settings')->latest()->first();
            if(Auth::user()->role_id > 2 && $general_setting->staff_access == 'own')
                $lims_payroll_all = Payroll::orderBy('id', 'desc')->where('user_id', Auth::id())->get();
            else
                $lims_payroll_all = Payroll::orderBy('id', 'desc')->get();

            return view('backend.payroll.index', compact('lims_account_list', 'lims_employee_list', 'lims_payroll_all'));
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
            'employee_id' => 'required',
            'account_id' => 'required',
            'amount' => 'required|numeric|min:0.01',
            'document' => 'nullable|file|mimes:jpg,jpeg,png,gif,pdf,csv,doc,docx,xls,xlsx,txt|max:10240',
        ]);

        $data = $request->except('document');
        if ($request->hasFile('document')) {
            $data['document'] = $this->storeDocument($request->file('document'));
        }
        if(isset($data['created_at']))
            $data['created_at'] = date("Y-m-d", strtotime(str_replace("/", "-", $data['created_at'])));
        else
            $data['created_at'] = date("Y-m-d");
        $data['reference_no'] = 'payroll-' . date("Ymd") . '-'. date("his");
        $data['user_id'] = Auth::id();
        $lims_payroll_data = Payroll::create($data);

        JournalService::postPayroll($lims_payroll_data);

        $message = 'Payroll creared succesfully';
        //collecting mail data
        $lims_employee_data = Employee::find($data['employee_id']);
        $mail_data['reference_no'] = $data['reference_no'];
        $mail_data['amount'] = $data['amount'];
        $mail_data['name'] = $lims_employee_data->name;
        $mail_data['email'] = $lims_employee_data->email;
        $mail_data['currency'] = config('currency');
        $mail_setting = MailSetting::latest()->first();
        if($mail_setting) {
            $this->setMailInfo($mail_setting);
            try{
                Mail::to($mail_data['email'])->send(new PayrollDetails($mail_data));
            }
            catch(\Exception $e){
                $message = ' Payroll created successfully. Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
            }
        }
        return redirect('payroll')->with('message', $message);
    }

    public function edit($id)
    {
        //
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'employee_id' => 'required',
            'account_id' => 'required',
            'amount' => 'required|numeric|min:0.01',
            'document' => 'nullable|file|mimes:jpg,jpeg,png,gif,pdf,csv,doc,docx,xls,xlsx,txt|max:10240',
        ]);

        $data = $request->except('document');
        if(isset($data['created_at']))
            $data['created_at'] = date("Y-m-d", strtotime(str_replace("/", "-", $data['created_at'])));
        else
            $data['created_at'] = date("Y-m-d");
        $lims_payroll_data = Payroll::find($data['payroll_id']);
        if ($request->hasFile('document')) {
            $oldDocument = $lims_payroll_data->document;
            $data['document'] = $this->storeDocument($request->file('document'));
            $this->fileDelete('documents/payroll/', $oldDocument);
        }
        $lims_payroll_data->update($data);

        JournalService::reverseForReference('payroll', $lims_payroll_data->id);
        JournalService::postPayroll($lims_payroll_data);

        return redirect('payroll')->with('message', 'Payroll updated succesfully');
    }

    public function deleteBySelection(Request $request)
    {
        $payroll_id = $request['payrollIdArray'];
        foreach ($payroll_id as $id) {
            $lims_payroll_data = Payroll::find($id);
            try {
                JournalService::reverseForReference('payroll', $id);
            } catch (\Exception $e) {}
            if ($lims_payroll_data) {
                $this->fileDelete('documents/payroll/', $lims_payroll_data->document);
                $lims_payroll_data->delete();
            }
        }
        return 'Payroll deleted successfully!';
    }

    public function destroy($id)
    {
        $lims_payroll_data = Payroll::find($id);
        try {
            JournalService::reverseForReference('payroll', $id);
        } catch (\Exception $e) {}
        if ($lims_payroll_data) {
            $this->fileDelete('documents/payroll/', $lims_payroll_data->document);
            $lims_payroll_data->delete();
        }
        return redirect('payroll')->with('not_permitted', 'Payroll deleted succesfully');
    }
}
