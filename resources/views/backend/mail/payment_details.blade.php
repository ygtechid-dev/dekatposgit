<h1>Payment Details</h1>
<p><strong>Sale Reference: </strong>{{$payment_data['sale_reference']}}</p>
<p><strong>Payment Reference: </strong>{{$payment_data['payment_reference']}}</p>
<p><strong>Payment Method: </strong>{{$payment_data['payment_method']}}</p>
<p><strong>Grand Total: </strong>{{$payment_data['currency']}} {{number_format((float)$payment_data['grand_total'], $general_setting->decimal, ',', '.')}}</p>
<p><strong>Paid Amount: </strong>{{$payment_data['currency']}} {{number_format((float)$payment_data['paid_amount'], $general_setting->decimal, ',', '.')}}</p>
<p><strong>Due: </strong>{{$payment_data['currency']}} {{number_format((float)($payment_data['due']), $general_setting->decimal, ',', '.')}}</p>
<p>Thank You</p>
