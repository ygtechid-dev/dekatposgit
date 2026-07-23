<h1>Hey {{$name}}!</h1>
<h3>{{number_format((float)$amount, 2, ',', '.')}} {{$general_setting->currency}} has successfully recharged in your GiftCard. Card no: {{$card_no}}.</h3>
<p>Your current balance is: {{number_format((float)$balance, 2, ',', '.')}}</p>
<p>Thank you</p>