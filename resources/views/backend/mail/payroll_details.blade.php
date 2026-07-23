<h1>Hey {{$payroll_data['name']}}!</h1>
<p>Reference No: {{$payroll_data['reference_no']}}</p>
<p>Your salary {{$payroll_data['currency']}} {{number_format((float)$payroll_data['amount'], 2, ',', '.')}} is paid.</p>
<p>Thank you</p>