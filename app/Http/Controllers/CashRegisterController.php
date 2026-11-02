<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CashRegister;
use App\Models\Sale;
use App\Models\Payment;
use App\Models\Returns;
use App\Models\Expense;
use Auth;
use Illuminate\Support\Facades\DB;

class CashRegisterController extends Controller
{
	public function index()
	{
		if(Auth::user()->role_id <= 2) {
			$lims_cash_register_all = CashRegister::with('user', 'warehouse')->get();
			return view('backend.cash_register.index', compact('lims_cash_register_all'));
		}
		else
			return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
	}
	public function store(Request $request)
	{
		$data = $request->all();
		$data['status'] = true;
		$data['user_id'] = Auth::id();
		CashRegister::create($data);
		return redirect()->back()->with('message', 'Cash register created successfully');
	}

	public function getDetails($id)
	{
		$cash_register_data = CashRegister::find($id);

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
		$data['total_sale_return'] = Returns::where('cash_register_id', $cash_register_data->id)->sum('grand_total');
		$data['total_expense'] = Expense::where('cash_register_id', $cash_register_data->id)->sum('amount');
		$data['total_cash'] = $data['cash_in_hand'] + $data['total_payment'] - ($data['total_sale_return'] + $data['total_expense']);
		$data['status'] = $cash_register_data->status;
		return $data;
	}

	public function showDetails($warehouse_id)
	{
		$cash_register_data = CashRegister::where([
					    		['user_id', Auth::id()],
					    		['warehouse_id', $warehouse_id],
					    		['status', true]
					    	])->first();

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
		$data['total_sale_return'] = Returns::where('cash_register_id', $cash_register_data->id)->sum('grand_total');
		$data['total_expense'] = Expense::where('cash_register_id', $cash_register_data->id)->sum('amount');
		$data['total_cash'] = $data['cash_in_hand'] + $data['total_payment'] - ($data['total_sale_return'] + $data['total_expense']);
		$data['id'] = $cash_register_data->id;
		return $data;
	}

	public function close(Request $request)
	{
		$cash_register_data = CashRegister::find($request->cash_register_id);

		// Hitung "setoran" = kas di tangan + semua pembayaran - retur - pengeluaran
		$base = [
			'cash_in_hand'          => $cash_register_data->cash_in_hand,
			'total_sale_amount'     => Sale::where(['cash_register_id' => $cash_register_data->id, 'sale_status' => 1])->sum('grand_total'),
			'total_payment'         => Payment::where('cash_register_id', $cash_register_data->id)->sum('amount'),
			'total_sale_return'     => Returns::where('cash_register_id', $cash_register_data->id)->sum('grand_total'),
			'total_expense'         => Expense::where('cash_register_id', $cash_register_data->id)->sum('amount'),
		];
		$base['total_cash'] = $base['cash_in_hand'] + $base['total_payment'] - ($base['total_sale_return'] + $base['total_expense']);

		$cash_register_data->status        = 0;
		$cash_register_data->closed_at     = now();
		$cash_register_data->settle_status = 0; // pending
		$cash_register_data->settle_amount = $base['total_cash'];
		$cash_register_data->save();

		return redirect()->back()->with('message', 'Cash register closed. Setoran menunggu approval keuangan.');
	}

	public function settleList()
	{
		if (Auth::user()->role_id > 2) {
			return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
		}

		$lims_settle_all = CashRegister::with('user', 'warehouse')
			->where('status', 0)
			->orderByDesc('closed_at')
			->get();

		// Kumpulkan detail tiap setoran
		$rows = [];
		foreach ($lims_settle_all as $reg) {
			$rows[] = [
				'register'   => $reg->loadMissing(['user', 'warehouse']),
				'total_cash' => $reg->settle_amount ?? 0,
			];
		}

		return view('backend.cash_register.settle', compact('rows'));
	}

	public function approve($id)
	{
		if (Auth::user()->role_id > 2) {
			return back()->with('not_permitted', 'Not allowed');
		}

		$reg = CashRegister::find($id);
		if (!$reg || $reg->status != 0) {
			return back()->with('error', 'Register tidak valid atau sudah ditutup.');
		}

		$reg->settle_status = 1;
		$reg->approved_at   = now();
		$reg->approved_by   = Auth::user()->email;
		$reg->save();

		return back()->with('message', 'Setoran #' . $reg->id . ' di-approve. Angka masuk ke laporan laba-rugi periode register (' .
			($reg->created_at ? $reg->created_at->format('d M Y') : '-') . ').');
	}

	public function reject($id)
	{
		if (Auth::user()->role_id > 2) {
			return back()->with('not_permitted', 'Not allowed');
		}

		$reg = CashRegister::find($id);
		if (!$reg || $reg->status != 0) {
			return back()->with('error', 'Register tidak valid.');
		}

		$reg->settle_status = 2;
		$reg->approved_at   = now();
		$reg->approved_by   = Auth::user()->email;
		$reg->save();

		return back()->with('message', 'Setoran #' . $reg->id . ' ditolak. Angka tidak masuk laba-rugi.');
	}

    public function checkAvailability($warehouse_id)
    {
    	$open_register_number = CashRegister::where([
						    		['user_id', Auth::id()],
						    		['warehouse_id', $warehouse_id],
						    		['status', true]
						    	])->count();
    	if($open_register_number)
    		return 'true';
    	else
    		return 'false';
    }
}
