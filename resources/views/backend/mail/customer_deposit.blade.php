<h1>Hey {{$deposit_data['name']}}!</h1>
<h3>{{$deposit_data['currency']}} {{number_format((float)$deposit_data['amount'], 2, ',', '.')}} has successfully recharged in your account.</h3>
<p>Your current balance is: {{number_format((float)$deposit_data['balance'], 2, ',', '.')}}</p>
<p>Thank you</p>