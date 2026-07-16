<h1>Transfer Details</h1>
<p><strong>Date: </strong>{{$mailData['date']}}</p>
<p><strong>Reference: </strong>{{$mailData['reference_no']}}</p>
<p>
	<strong>Transfer Status: </strong>
	@if($mailData['status']==1){{'Completed'}}
	@elseif($mailData['status']==2){{'Pending'}}
	@elseif($mailData['status']==2){{'Sent'}}
	@endif
</p>
<p>
    <strong>Transfer From:</strong> {{ $mailData['from_warehouse']}} <br>
    <strong>Transfer To:</strong> {{ $mailData['to_warehouse']}} 
</p>

<h3>Transfer Table</h3>
<table style="border-collapse: collapse; width: 100%;">
	<thead>
		<th style="border: 1px solid #000; padding: 5px">#</th>
		<th style="border: 1px solid #000; padding: 5px">Product</th>
		<th style="border: 1px solid #000; padding: 5px">Qty</th>
		<th style="border: 1px solid #000; padding: 5px">Unit Cost</th>
		<th style="border: 1px solid #000; padding: 5px">Tax</th>
		<th style="border: 1px solid #000; padding: 5px">SubTotal</th>
	</thead>
	<tbody>
        @php
         $i = 0;   
        @endphp
		@foreach($mailData['products'] as $key=>$product)
		<tr>
			<td style="border: 1px solid #000; padding: 5px">{{ ++$i }}</td>
			<td style="border: 1px solid #000; padding: 5px">{{$product}}</td>
			<td style="border: 1px solid #000; padding: 5px">{{$mailData['qty'][$key].' '.$mailData['unit'][$key]}}</td>
			<td style="border: 1px solid #000; padding: 5px">{{number_format((float)($mailData['total'][$key] / $mailData['qty'][$key]), $general_setting->decimal, '.', '')}}</td>
			<td style="border: 1px solid #000; padding: 5px">{{$mailData['tax'][$key]}} ({{ $mailData['tax_rate'][$key]}} %)</td>
			<td style="border: 1px solid #000; padding: 5px">{{$mailData['total'][$key]}}</td>
		</tr>
		@endforeach
        <tr>
			<td colspan="3" style="border: 1px solid #000; padding: 5px"><strong>Total </strong></td>
			<td style="border: 1px solid #000; padding: 5px"></td>
			<td style="border: 1px solid #000; padding: 5px"></td>
			<td style="border: 1px solid #000; padding: 5px">{{$mailData['total_cost']}}</td>
		</tr>
        @if($mailData['shipping_cost'])
        <tr>
			<td colspan="3" style="border: 1px solid #000; padding: 5px"><strong>Shipping Cost </strong></td>
			<td style="border: 1px solid #000; padding: 5px"></td>
			<td style="border: 1px solid #000; padding: 5px"></td>
			<td style="border: 1px solid #000; padding: 5px">{{$mailData['shipping_cost']}}</td>
		</tr>
        @endif
        <tr>
			<td colspan="3" style="border: 1px solid #000; padding: 5px"><strong>Grand Total </strong></td>
			<td style="border: 1px solid #000; padding: 5px"></td>
			<td style="border: 1px solid #000; padding: 5px"></td>
			<td style="border: 1px solid #000; padding: 5px">{{$mailData['grand_total']}}</td>
		</tr>
	</tbody>
</table>

<p>Thank You</p>