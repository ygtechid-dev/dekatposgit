<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CustomerGroup;
use App\Models\Customer;
use App\Models\Deposit;
use App\Models\User;
use App\Models\Supplier;
use App\Models\Sale;
use App\Models\Payment;
use App\Models\CashRegister;
use App\Models\Account;
use App\Models\MailSetting;
use Auth;
use DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Mail\CustomerCreate;
use App\Mail\SupplierCreate;
use App\Mail\CustomerDeposit;
use Mail;
use App\Models\CustomField;
use App\Services\JournalService;

class CustomerController extends Controller
{
    use \App\Traits\CacheForget;
    use \App\Traits\MailInfo;

    private function normalizeCurrencyInput($value)
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }
        $value = preg_replace('/[^0-9,.-]/', '', $value);
        if (strpos($value, ',') !== false) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }
        elseif (substr_count($value, '.') > 1 || preg_match('/^\d{1,3}(\.\d{3})+$/', $value)) {
            $value = str_replace('.', '', $value);
        }
        return (float) $value;
    }

    public function index()
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('customers-index')){
            $permissions = Role::findByName($role->name)->permissions;
            foreach ($permissions as $permission)
                $all_permission[] = $permission->name;
            if(empty($all_permission))
                $all_permission[] = 'dummy text';
            $lims_customer_all = Customer::with('customerGroup')->where('is_active', true)->get();
            $custom_fields = CustomField::where([
                                ['belongs_to', 'customer'],
                                ['is_table', true]
                            ])->pluck('name');
            return view('backend.customer.index', compact('lims_customer_all', 'all_permission', 'custom_fields'));
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    public function clearDue(Request $request)
    {
        $request->merge([
            'amount' => $this->normalizeCurrencyInput($request->input('amount')),
        ]);

        $request->validate([
            'customer_id' => 'required',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $lims_due_sale_data = Sale::select('id', 'warehouse_id', 'grand_total', 'paid_amount', 'payment_status')
                            ->where([
                                ['payment_status', '!=', 4],
                                ['customer_id', $request->customer_id]
                            ])->orderBy('id')->get();
        $total_paid_amount = $request->amount;
        $account_data = Account::select('id')->where('is_default', 1)->first();
        $piutang_id = JournalService::accountIdByCode(JournalService::PIUTANG_USAHA);
        $kas_id = JournalService::coaIdForCashAccount($account_data ? $account_data->id : null);

        foreach ($lims_due_sale_data as $key => $sale_data) {
            if($total_paid_amount == 0)
                break;
            $due_amount = $sale_data->grand_total - $sale_data->paid_amount;
            $lims_cash_register_data =  CashRegister::select('id')
                                        ->where([
                                            ['user_id', Auth::id()],
                                            ['warehouse_id', $sale_data->warehouse_id],
                                            ['status', 1]
                                        ])->first();
            if($lims_cash_register_data)
                $cash_register_id = $lims_cash_register_data->id;
            else
                $cash_register_id = null;
            if($total_paid_amount >= $due_amount) {
                $paid_amount = $due_amount;
                $payment_status = 4;
            }
            else {
                $paid_amount = $total_paid_amount;
                $payment_status = 2;
            }
            $lims_payment_data = Payment::create([
                'payment_reference' => 'spr-'.date("Ymd").'-'.date("his"),
                'sale_id' => $sale_data->id,
                'user_id' => Auth::id(),
                'cash_register_id' => $cash_register_id,
                'account_id' => $account_data ? $account_data->id : null,
                'amount' => $paid_amount,
                'change' => 0,
                'paying_method' => 'Cash',
                'payment_note' => $request->note
            ]);
            $sale_data->paid_amount += $paid_amount;
            $sale_data->payment_status = $payment_status;
            $sale_data->save();
            $total_paid_amount -= $paid_amount;

            // Jurnal otomatis: pembayaran piutang -> Dr Kas / Cr Piutang Usaha
            // (sama seperti pembayaran per-invoice biasa di SaleController).
            try {
                JournalService::postSimple(now(), 'Pembayaran piutang ' . $sale_data->id, $kas_id, $piutang_id, $paid_amount, 'sale_payment', $lims_payment_data->id);
            } catch (\Exception $e) {}
        }
        return redirect()->back()->with('message', 'Due cleared successfully');
    }

    /**
     * Halaman khusus "Pembayaran Piutang": daftar pelanggan yang punya sisa
     * piutang (bukan cuma tombol "Clear Due" yang nyempil di dropdown Action
     * Customer List). Menggunakan JournalService::PIUTANG_USAHA sale query
     * yang sama seperti clearDue() supaya angkanya konsisten.
     */
    public function duePayment()
    {
        $role = Role::find(Auth::user()->role_id);
        if(!$role->hasPermissionTo('customers-index'))
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');

        $lims_customer_due = Customer::where('is_active', true)
            ->get()
            ->map(function($customer) {
                $due = Sale::where([
                    ['customer_id', $customer->id],
                    ['payment_status', '!=', 4],
                ])->selectRaw('COALESCE(SUM(grand_total - paid_amount), 0) as total_due')->value('total_due');
                $customer->total_due = (float) $due;
                return $customer;
            })
            ->filter(function($customer) {
                return $customer->total_due > 0.009;
            })
            ->sortByDesc('total_due')
            ->values();

        return view('backend.customer.due_payment', compact('lims_customer_due'));
    }

    /**
     * Rincian invoice yang belum lunas milik satu pelanggan - dipakai oleh
     * halaman Pembayaran Piutang supaya kasir tahu invoice mana saja yang
     * akan otomatis dilunasi (FIFO, invoice paling lama duluan) sebelum
     * submit pembayaran lewat clearDue().
     */
    public function dueInvoices($id)
    {
        $sales = Sale::where([
                ['customer_id', $id],
                ['payment_status', '!=', 4],
            ])
            ->orderBy('id')
            ->get(['id', 'reference_no', 'created_at', 'grand_total', 'paid_amount']);

        $data = $sales->map(function($sale) {
            return [
                'reference_no' => $sale->reference_no,
                'date' => $sale->created_at ? $sale->created_at->format('d-m-Y') : '-',
                'grand_total' => (float) $sale->grand_total,
                'paid_amount' => (float) $sale->paid_amount,
                'due' => (float) ($sale->grand_total - $sale->paid_amount),
            ];
        });

        return response()->json($data);
    }

    public function create()
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('customers-add')){
            $lims_customer_group_all = CustomerGroup::where('is_active',true)->get();
            $custom_fields = CustomField::where('belongs_to', 'customer')->get();
            return view('backend.customer.create', compact('lims_customer_group_all', 'custom_fields'));
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }


    public function store(Request $request)
    {
        $this->validate($request, [
            'phone_number' => [
                'max:255',
                Rule::unique('customers')->where(function ($query) {
                    return $query->where('is_active', 1);
                }),
            ],
        ]);
        //validation for supplier if create both user and supplier
        if(isset($request->both)) {
            $this->validate($request, [
                'company_name' => [
                    'max:255',
                    Rule::unique('suppliers')->where(function ($query) {
                        return $query->where('is_active', 1);
                    }),
                ],
                'email' => [
                    'max:255',
                    Rule::unique('suppliers')->where(function ($query) {
                        return $query->where('is_active', 1);
                    }),
                ],
            ]);
        }
        //validation for user if given user access
        if(isset($request->user)) {
            $this->validate($request, [
                'name' => [
                    'max:255',
                        Rule::unique('users')->where(function ($query) {
                        return $query->where('is_deleted', false);
                    }),
                ],
                'email' => [
                    'email',
                    'max:255',
                        Rule::unique('users')->where(function ($query) {
                        return $query->where('is_deleted', false);
                    }),
                ],
            ]);
        }
        $customer_data = $request->all();
        //return $customer_data;
        $customer_data['is_active'] = true;
        $prefixMessage = 'Customer';
        if(isset($request->user)) {
            $customer_data['phone'] = $customer_data['phone_number'];
            $customer_data['role_id'] = 5;
            $customer_data['is_deleted'] = false;
            $customer_data['password'] = bcrypt($customer_data['password']);
            $user = User::create($customer_data);
            $customer_data['user_id'] = $user->id;
            $prefixMessage .= ', User';
        }
        $customer_data['name'] = $customer_data['customer_name'];
        if(isset($request->both)) {
            Supplier::create($customer_data);
            $prefixMessage .= ' and Supplier';
        }

        $fullMessage = $prefixMessage.' created successfully!';
        $mail_setting = MailSetting::latest()->first();
        $message = $this->mailAction($customer_data, $mail_setting, $request, $fullMessage);

        // if($customer_data['email'] && $mail_setting) {
        //     $this->setMailInfo($mail_setting);
        //     try {
        //         Mail::to($customer_data['email'])->send(new CustomerCreate($customer_data));
        //         if(isset($request->both))
        //             Mail::to($customer_data['email'])->send(new SupplierCreate($customer_data));
        //         $message .= ' created successfully!';
        //     }
        //     catch(\Exception $e){
        //         $message .= ' created successfully. Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
        //     }
        // }
        // else
        //     $message .= ' created successfully!';

        $lims_customer_data = Customer::create($customer_data);
        //inserting data for custom fields
        $custom_field_data = [];
        $custom_fields = CustomField::where('belongs_to', 'customer')->select('name', 'type')->get();
        foreach ($custom_fields as $type => $custom_field) {
            $field_name = str_replace(' ', '_', strtolower($custom_field->name));
            if(isset($customer_data[$field_name])) {
                if($custom_field->type == 'checkbox' || $custom_field->type == 'multi_select')
                    $custom_field_data[$field_name] = implode(",", $customer_data[$field_name]);
                else
                    $custom_field_data[$field_name] = $customer_data[$field_name];
            }
        }
        if(count($custom_field_data))
            DB::table('customers')->where('id', $lims_customer_data->id)->update($custom_field_data);
        $this->cacheForget('customer_list');
        $customerInfo['id'] = $lims_customer_data->id;
        $customerInfo['name'] = $lims_customer_data->name;
        $customerInfo['phone_number'] = $lims_customer_data->phone_number;
        if($customer_data['pos'])
            return $customerInfo;
        else
            return redirect('customer')->with('create_message', $message);
    }

    public function edit($id)
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('customers-edit')){
            $lims_customer_data = Customer::find($id);
            $lims_customer_group_all = CustomerGroup::where('is_active',true)->get();
            $custom_fields = CustomField::where('belongs_to', 'customer')->get();
            return view('backend.customer.edit', compact('lims_customer_data','lims_customer_group_all', 'custom_fields'));
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'phone_number' => [
                'max:255',
                    Rule::unique('customers')->ignore($id)->where(function ($query) {
                    return $query->where('is_active', 1);
                }),
            ],
        ]);

        $input = $request->all();
        $lims_customer_data = Customer::find($id);

        if(isset($input['user'])) {
            $this->validate($request, [
                'name' => [
                    'max:255',
                        Rule::unique('users')->where(function ($query) {
                        return $query->where('is_deleted', false);
                    }),
                ],
                'email' => [
                    'email',
                    'max:255',
                        Rule::unique('users')->where(function ($query) {
                        return $query->where('is_deleted', false);
                    }),
                ],
            ]);

            $input['phone'] = $input['phone_number'];
            $input['role_id'] = 5;
            $input['is_active'] = true;
            $input['is_deleted'] = false;
            $input['password'] = bcrypt($input['password']);
            $user = User::create($input);
            $input['user_id'] = $user->id;
            $message = 'Customer updated and user created successfully';
        }
        else {
            $message = 'Customer updated successfully';
        }

        $input['name'] = $input['customer_name'];
        $lims_customer_data->update($input);
        //update custom field data
        $custom_field_data = [];
        $custom_fields = CustomField::where('belongs_to', 'customer')->select('name', 'type')->get();
        foreach ($custom_fields as $type => $custom_field) {
            $field_name = str_replace(' ', '_', strtolower($custom_field->name));
            if(isset($input[$field_name])) {
                if($custom_field->type == 'checkbox' || $custom_field->type == 'multi_select')
                    $custom_field_data[$field_name] = implode(",", $input[$field_name]);
                else
                    $custom_field_data[$field_name] = $input[$field_name];
            }
        }
        if(count($custom_field_data))
            DB::table('customers')->where('id', $lims_customer_data->id)->update($custom_field_data);
        $this->cacheForget('customer_list');

        return redirect('customer')->with('edit_message', $message);
    }

    public function importCustomer(Request $request)
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('customers-add')){
            $upload=$request->file('file');
            $ext = pathinfo($upload->getClientOriginalName(), PATHINFO_EXTENSION);
            if($ext != 'csv')
                return redirect()->back()->with('not_permitted', 'Please upload a CSV file');
            $filename =  $upload->getClientOriginalName();
            $filePath=$upload->getRealPath();
            //open and read
            $file=fopen($filePath, 'r');
            $header= fgetcsv($file);
            $escapedHeader=[];
            //validate
            foreach ($header as $key => $value) {
                $lheader=strtolower($value);
                $escapedItem=preg_replace('/[^a-z]/', '', $lheader);
                array_push($escapedHeader, $escapedItem);
            }

            $mail_setting = MailSetting::latest()->first();

            //looping through othe columns
            while($columns=fgetcsv($file))
            {
                if($columns[0]=="")
                    continue;
                foreach ($columns as $key => $value) {
                    $value=preg_replace('/\D/','',$value);
                }
               $data= array_combine($escapedHeader, $columns);
               $lims_customer_group_data = CustomerGroup::where('name', $data['customergroup'])->first();
               $customer = Customer::firstOrNew(['name'=>$data['name']]);
               $customer->customer_group_id = $lims_customer_group_data->id;
               $customer->name = $data['name'];
               $customer->company_name = $data['companyname'];
               $customer->email = $data['email'];
               $customer->phone_number = $data['phonenumber'];
               $customer->address = $data['address'];
               $customer->city = $data['city'];
               $customer->state = $data['state'];
               $customer->postal_code = $data['postalcode'];
               $customer->country = $data['country'];
               $customer->is_active = true;
               $customer->save();

               $message = $this->mailAction($data, $mail_setting, $request, 'Customer Imported Successfully');

            //    $mail_setting = MailSetting::latest()->first();
            //    if($data['email'] && $mail_setting) {
            //         $this->setMailInfo($mail_setting);
            //         try {
            //             Mail::to($data['email'])->send(new CustomerCreate($data));
            //         }
            //         catch(\Exception $e){
            //             $message = 'Customer imported successfully. Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
            //         }
            //     }

            }
            $this->cacheForget('customer_list');
            return redirect('customer')->with('import_message', $message);
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    public function getDeposit($id)
    {
        $lims_deposit_list = Deposit::where('customer_id', $id)->get();
        $deposit_id = [];
        $deposits = [];
        foreach ($lims_deposit_list as $deposit) {
            $deposit_id[] = $deposit->id;
            $date[] = $deposit->created_at->toDateString() . ' '. $deposit->created_at->toTimeString();
            $amount[] = $deposit->amount;
            $note[] = $deposit->note;
            $lims_user_data = User::find($deposit->user_id);
            $name[] = $lims_user_data->name;
            $email[] = $lims_user_data->email;
        }
        if(!empty($deposit_id)){
            $deposits[] = $deposit_id;
            $deposits[] = $date;
            $deposits[] = $amount;
            $deposits[] = $note;
            $deposits[] = $name;
            $deposits[] = $email;
        }
        return $deposits;
    }

    public function addDeposit(Request $request)
    {
        $data = $request->all();
        $data['user_id'] = Auth::id();
        $lims_customer_data = Customer::find($data['customer_id']);
        $lims_customer_data->deposit += $data['amount'];
        $lims_customer_data->save();
        $lims_deposit_data = Deposit::create($data);

        //auto-post journal: customer deposits cash (Dr Kas/Bank default, Cr Titipan Pelanggan)
        try {
            $lims_default_account = Account::where('is_default', true)->first();
            $kas_id = JournalService::coaIdForCashAccount($lims_default_account ? $lims_default_account->id : null);
            $titipan_id = JournalService::accountIdByCode(JournalService::TITIPAN_PELANGGAN);
            JournalService::postSimple(now(), 'Deposit pelanggan ' . $lims_customer_data->name, $kas_id, $titipan_id, $lims_deposit_data->amount, 'deposit', $lims_deposit_data->id);
        } catch (\Exception $e) {}

        $message = 'Data inserted successfully';
        $mail_setting = MailSetting::latest()->first();

        if($lims_customer_data->email && $mail_setting) {
            $data['name'] = $lims_customer_data->name;
            $data['email'] = $lims_customer_data->email;
            $data['balance'] = $lims_customer_data->deposit - $lims_customer_data->expense;
            $data['currency'] = config('currency');
            $message = $this->mailAction($data, $mail_setting, $request);

            // $this->setMailInfo($mail_setting);
            // try {
            //     Mail::to($data['email'])->send(new CustomerDeposit($data));
            // }
            // catch(\Exception $e){
            //     $message = 'Data inserted successfully. Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
            // }
        }
        return redirect('customer')->with('create_message', $message);
    }

    public function updateDeposit(Request $request)
    {
        $data = $request->all();
        $lims_deposit_data = Deposit::find($data['deposit_id']);
        $lims_customer_data = Customer::find($lims_deposit_data->customer_id);
        $amount_dif = $data['amount'] - $lims_deposit_data->amount;
        $lims_customer_data->deposit += $amount_dif;
        $lims_customer_data->save();
        $lims_deposit_data->update($data);

        //auto-post journal: reverse old deposit entry and repost with updated amount
        try {
            JournalService::reverseForReference('deposit', $lims_deposit_data->id);
            $lims_default_account = Account::where('is_default', true)->first();
            $kas_id = JournalService::coaIdForCashAccount($lims_default_account ? $lims_default_account->id : null);
            $titipan_id = JournalService::accountIdByCode(JournalService::TITIPAN_PELANGGAN);
            JournalService::postSimple(now(), 'Update deposit pelanggan ' . $lims_customer_data->name, $kas_id, $titipan_id, $lims_deposit_data->amount, 'deposit', $lims_deposit_data->id);
        } catch (\Exception $e) {}

        return redirect('customer')->with('create_message', 'Data updated successfully');
    }

    public function deleteDeposit(Request $request)
    {
        $data = $request->all();
        $lims_deposit_data = Deposit::find($data['id']);
        $lims_customer_data = Customer::find($lims_deposit_data->customer_id);
        $lims_customer_data->deposit -= $lims_deposit_data->amount;
        $lims_customer_data->save();
        try {
            JournalService::reverseForReference('deposit', $lims_deposit_data->id);
        } catch (\Exception $e) {}
        $lims_deposit_data->delete();
        return redirect('customer')->with('not_permitted', 'Data deleted successfully');
    }

    public function deleteBySelection(Request $request)
    {
        $customer_id = $request['customerIdArray'];
        foreach ($customer_id as $id) {
            $lims_customer_data = Customer::find($id);
            $lims_customer_data->is_active = false;
            $lims_customer_data->save();
        }
        $this->cacheForget('customer_list');
        return 'Customer deleted successfully!';
    }

    public function destroy($id)
    {
        $lims_customer_data = Customer::find($id);
        $lims_customer_data->is_active = false;
        $lims_customer_data->save();
        $this->cacheForget('customer_list');
        return redirect('customer')->with('not_permitted','Data deleted Successfully');
    }

    protected function mailAction($data, $mailSetting, $request, $customMessage=null)
    {
        $message = $customMessage ?? 'Data inserted successfully';
        if(!$mailSetting) {
            $message = 'Data inserted successfully. Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
        }
        else if($data['email'] && $mailSetting) {
            try{
                $this->setMailInfo($mailSetting);
                Mail::to($data['email'])->send(new CustomerCreate($data));
                if(isset($request->both))
                    Mail::to($data['email'])->send(new SupplierCreate($data));
            }
            catch(\Exception $e){
                $message = $e->getMessage();
            }
        }
        return $message;
    }

    public function customersAll()
    {
        $lims_customer_list = DB::table('customers')->where('is_active', true)->get();
        
        $html = '';
        foreach($lims_customer_list as $customer){
            $html .='<option value="'.$customer->id.'">'.$customer->name . ' (' . $customer->phone_number. ')'.'</option>';
        }

        return response()->json($html);
    }

}
