<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Setor Kasir</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 13px; color: #222; }
        .wrap { max-width: 760px; margin: 0 auto; }
        h2, h4 { margin-bottom: 6px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        td, th { border: 1px solid #ddd; padding: 8px; }
        th { background: #f5f5f5; text-align: left; }
        .right { text-align: right; }
        .no-border td { border: none; padding: 3px 0; }
        @media print { button { display: none; } }
    </style>
</head>
<body>
    <div class="wrap">
        <button onclick="window.print()">Print</button>
        <h2>Laporan Setor Kasir</h2>
        <table class="no-border">
            <tr><td>Kasir</td><td>: {{$cash_register_data->user->name}}</td></tr>
            <tr><td>Warehouse</td><td>: {{$cash_register_data->warehouse->name}}</td></tr>
            <tr><td>Buka</td><td>: {{$cash_register_data->created_at}}</td></tr>
            <tr><td>Tutup</td><td>: {{$cash_register_data->status ? 'Masih aktif' : $cash_register_data->updated_at}}</td></tr>
            <tr><td>Approval</td><td>: {{$cash_register_data->approval_status}} @if($cash_register_data->approver) oleh {{$cash_register_data->approver->name}} @endif</td></tr>
        </table>

        <h4>Rincian Kas</h4>
        <table>
            <tbody>
                <tr><td>Modal Kembalian</td><td class="right">{{number_format((float)$details['cash_in_hand'], config('decimal'), '.', '')}}</td></tr>
                <tr><td>Pembayaran Cash Penjualan</td><td class="right">{{number_format((float)$details['cash_sale_payment'], config('decimal'), '.', '')}}</td></tr>
                <tr><td>Pembayaran Cash Piutang</td><td class="right">{{number_format((float)$details['due_payment'], config('decimal'), '.', '')}}</td></tr>
                <tr><td>Total Cash Payment</td><td class="right">{{number_format((float)$details['cash_payment'], config('decimal'), '.', '')}}</td></tr>
                <tr><td>Retur Penjualan</td><td class="right">-{{number_format((float)$details['total_sale_return'], config('decimal'), '.', '')}}</td></tr>
                <tr><td>Expense Kasir</td><td class="right">-{{number_format((float)$details['total_expense'], config('decimal'), '.', '')}}</td></tr>
                <tr><th>Kas Sistem</th><th class="right">{{number_format((float)$details['expected_cash_amount'], config('decimal'), '.', '')}}</th></tr>
                <tr><td>Uang Fisik Dihitung</td><td class="right">{{number_format((float)$details['closing_cash_amount'], config('decimal'), '.', '')}}</td></tr>
                <tr><td>Setor Kasir</td><td class="right">{{number_format((float)$details['deposit_amount'], config('decimal'), '.', '')}}</td></tr>
                <tr><th>Selisih</th><th class="right">{{number_format((float)$details['cash_difference'], config('decimal'), '.', '')}}</th></tr>
            </tbody>
        </table>

        <h4>Rincian Non Tunai</h4>
        <table>
            <tbody>
                <tr><td>Credit Card</td><td class="right">{{number_format((float)$details['credit_card_payment'], config('decimal'), '.', '')}}</td></tr>
                <tr><td>Cheque</td><td class="right">{{number_format((float)$details['cheque_payment'], config('decimal'), '.', '')}}</td></tr>
                <tr><td>Gift Card</td><td class="right">{{number_format((float)$details['gift_card_payment'], config('decimal'), '.', '')}}</td></tr>
                <tr><td>Deposit</td><td class="right">{{number_format((float)$details['deposit_payment'], config('decimal'), '.', '')}}</td></tr>
                <tr><td>Paypal</td><td class="right">{{number_format((float)$details['paypal_payment'], config('decimal'), '.', '')}}</td></tr>
                <tr><th>Total Payment Semua Metode</th><th class="right">{{number_format((float)$details['total_payment'], config('decimal'), '.', '')}}</th></tr>
            </tbody>
        </table>

        <p><strong>Catatan tutup kasir:</strong><br>{{ $cash_register_data->closing_note ?: '-' }}</p>
        <p><strong>Catatan approval:</strong><br>{{ $cash_register_data->approval_note ?: '-' }}</p>
    </div>
</body>
</html>
