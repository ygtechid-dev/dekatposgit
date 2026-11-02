@extends('backend.layout.main') @section('content')

<section class="forms">
    <div class="container-fluid d-flex justify-content-between align-items-center">
        <h3>Laporan Laba Rugi</h3>
        <button type="button" id="print-profit-loss" class="btn btn-primary"><i class="fa fa-print"></i> Print Laba Rugi</button>
    </div>
    {!! Form::open(['route' => 'accounts.profitloss', 'method' => 'get', 'id' => 'profit-loss-filter']) !!}
    <div class="row mb-3">
        <div class="col-md-3">
            <label>Filter Bulan</label>
            <input type="month" name="filter_month" id="filter_month" class="form-control" value="{{ date('Y-m', strtotime($start_date)) }}">
        </div>
        <div class="col-md-3">
            <label>Dari Tanggal</label>
            <input type="date" name="start_date" id="start_date" class="form-control" value="{{$start_date}}">
        </div>
        <div class="col-md-3">
            <label>Sampai Tanggal</label>
            <input type="date" name="end_date" id="end_date" class="form-control" value="{{$end_date}}">
        </div>
        <div class="col-md-2">
            <label>&nbsp;</label>
            <button class="btn btn-primary form-control" type="submit">{{trans('file.submit')}}</button>
        </div>
    </div>
    {!! Form::close() !!}

    <div id="profit-loss-print-area" class="table-responsive mb-4">
        <table id="profit-loss-table" class="table table-hover">
            <tbody>
                <tr>
                    <th colspan="2">Pendapatan</th>
                </tr>
                @forelse($revenue_rows as $row)
                    <tr>
                        <td>
                            {{ $row['coa']->code }} - {{ $row['coa']->name }}
                            <a class="ml-2" href="{{ route('accounts.generalledger', ['chart_of_account_id' => $row['coa']->id, 'start_date' => $start_date, 'end_date' => $end_date]) }}" target="_blank">Mutasi</a>
                        </td>
                        <td class="text-right">{{number_format((float)$row['amount'], $general_setting->decimal, ',', '.')}}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="text-muted">Belum ada mutasi pendapatan pada periode ini.</td>
                    </tr>
                @endforelse
                <tr class="active">
                    <th>Total Pendapatan Bersih</th>
                    <th class="text-right">{{number_format((float)$total_revenue, $general_setting->decimal, ',', '.')}}</th>
                </tr>
                @foreach($hpp_rows as $row)
                    <tr>
                        <td>
                            {{ $row['coa']->code }} - {{ $row['coa']->name }}
                            <a class="ml-2" href="{{ route('accounts.generalledger', ['chart_of_account_id' => $row['coa']->id, 'start_date' => $start_date, 'end_date' => $end_date]) }}" target="_blank">Mutasi</a>
                        </td>
                        <td class="text-right">-{{number_format((float)$row['amount'], $general_setting->decimal, ',', '.')}}</td>
                    </tr>
                @endforeach
                @if(!count($hpp_rows))
                    <tr>
                        <td>5100 - Harga Pokok Penjualan</td>
                        <td class="text-right">-{{number_format(0, $general_setting->decimal, ',', '.')}}</td>
                    </tr>
                @endif
                <tr class="active">
                    <th>Laba Kotor</th>
                    <th class="text-right">{{number_format((float)$gross_profit, $general_setting->decimal, ',', '.')}}</th>
                </tr>
                <tr>
                    <th colspan="2">Beban</th>
                </tr>
                @forelse($expense_rows as $row)
                    <tr>
                        <td>
                            {{ $row['coa']->code }} - {{ $row['coa']->name }}
                            <a class="ml-2" href="{{ route('accounts.generalledger', ['chart_of_account_id' => $row['coa']->id, 'start_date' => $start_date, 'end_date' => $end_date]) }}" target="_blank">Mutasi</a>
                        </td>
                        <td class="text-right">-{{number_format((float)$row['amount'], $general_setting->decimal, ',', '.')}}</td>
                    </tr>
                    @if(!empty($row['children']))
                        @foreach($row['children'] as $child)
                            <tr>
                                <td class="pl-4 text-muted">- {{ $child['label'] }}</td>
                                <td class="text-right text-muted">-{{number_format((float)$child['amount'], $general_setting->decimal, ',', '.')}}</td>
                            </tr>
                        @endforeach
                    @endif
                @empty
                    <tr>
                        <td colspan="2" class="text-muted">Belum ada mutasi beban pada periode ini.</td>
                    </tr>
                @endforelse
                <tr class="active">
                    <th>Total Beban</th>
                    <th class="text-right">-{{number_format((float)$total_expenses, $general_setting->decimal, ',', '.')}}</th>
                </tr>
                <tr class="tfoot active">
                    <th>Laba / Rugi Bersih</th>
                    <th class="text-right">{{number_format((float)$net_profit, $general_setting->decimal, ',', '.')}}</th>
                </tr>
            </tbody>
        </table>
    </div>
</section>

@endsection
@push('scripts')
<script type="text/javascript">
    $("ul#account").siblings('a').attr('aria-expanded','true');
    $("ul#account").addClass("show");
    $("ul#account #profit-loss-menu").addClass("active");

    // "Filter Bulan" is a convenience shortcut: picking a month fills in the
    // start/end date fields with that month's boundaries before submitting.
    $('#filter_month').on('change', function() {
        var val = $(this).val();
        if (!val) return;
        var parts = val.split('-');
        var year = parseInt(parts[0], 10);
        var month = parseInt(parts[1], 10);
        var firstDay = new Date(year, month - 1, 1);
        var lastDay = new Date(year, month, 0);
        var pad = function(n) { return String(n).padStart(2, '0'); };
        $('#start_date').val(firstDay.getFullYear() + '-' + pad(firstDay.getMonth() + 1) + '-' + pad(firstDay.getDate()));
        $('#end_date').val(lastDay.getFullYear() + '-' + pad(lastDay.getMonth() + 1) + '-' + pad(lastDay.getDate()));
    });
    $('#start_date, #end_date').on('change', function() {
        $('#filter_month').val('');
    });

    $('#print-profit-loss').on('click', function() {
        var report = $('#profit-loss-print-area').clone();
        report.find('.dt-buttons, .dataTables_filter, .dataTables_info, .dataTables_paginate, .dataTables_length, a').remove();
        var printWindow = window.open('', '_blank');
        printWindow.document.write('<html><head><title>Laporan Laba Rugi</title>' +
            '<style>body{font-family:Arial,sans-serif;color:#222;margin:24px}h2,p{text-align:center;margin:0 0 8px}table{width:100%;border-collapse:collapse;margin-top:18px}th,td{border:1px solid #bbb;padding:8px}.text-right{text-align:right}.text-muted{color:#666}.pl-4{padding-left:28px}.active,.tfoot{font-weight:700;background:#eee}@page{size:A4 portrait;margin:12mm}</style>' +
            '</head><body><h2>Laporan Laba Rugi</h2><p>{{ date('d/m/Y', strtotime($start_date)) }} - {{ date('d/m/Y', strtotime($end_date)) }}</p>' + report.html() + '</body></html>');
        printWindow.document.close();
        printWindow.onload = function() {
            printWindow.focus();
            printWindow.print();
            printWindow.close();
        };
    });

    $('#profit-loss-table').DataTable({
        paging: false,
        searching: false,
        info: false,
        ordering: false,
        dom: '<"row"B>rt',
        buttons: [
            {extend: 'pdf', text: '<i title="export to pdf" class="fa fa-file-pdf-o"></i>'},
            {extend: 'excel', text: '<i title="export to excel" class="dripicons-document-new"></i>'},
            {extend: 'csv', text: '<i title="export to csv" class="fa fa-file-text-o"></i>'},
        ]
    });
</script>
@endpush
