@extends('backend.layout.main') @section('content')

<section class="forms">
    <div class="container-fluid">
        <h3>Laporan Laba Rugi</h3>
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

    <div class="table-responsive mb-4">
        <table id="profit-loss-table" class="table table-hover">
            <tbody>
                <tr>
                    <th colspan="2">Pendapatan</th>
                </tr>
                <tr>
                    <td>Penjualan Kotor</td>
                    <td class="text-right">{{number_format((float)$gross_sales, $general_setting->decimal, ',', '.')}}</td>
                </tr>
                <tr>
                    <td>Diskon Order</td>
                    <td class="text-right">-{{number_format((float)$order_discount, $general_setting->decimal, ',', '.')}}</td>
                </tr>
                <tr>
                    <td>Pajak Order</td>
                    <td class="text-right">{{number_format((float)$order_tax, $general_setting->decimal, ',', '.')}}</td>
                </tr>
                <tr>
                    <td>Ongkos Kirim</td>
                    <td class="text-right">{{number_format((float)$shipping_cost, $general_setting->decimal, ',', '.')}}</td>
                </tr>
                <tr>
                    <td>Retur Penjualan</td>
                    <td class="text-right">-{{number_format((float)$sale_returns, $general_setting->decimal, ',', '.')}}</td>
                </tr>
                <tr class="active">
                    <th>Penjualan Bersih</th>
                    <th class="text-right">{{number_format((float)$net_sales, $general_setting->decimal, ',', '.')}}</th>
                </tr>
                <tr>
                    <td>Harga Pokok Penjualan</td>
                    <td class="text-right">-{{number_format((float)$cost_of_goods_sold, $general_setting->decimal, ',', '.')}}</td>
                </tr>
                <tr class="active">
                    <th>Laba Kotor</th>
                    <th class="text-right">{{number_format((float)$gross_profit, $general_setting->decimal, ',', '.')}}</th>
                </tr>
                <tr>
                    <th colspan="2">Beban</th>
                </tr>
                <tr>
                    <td>Biaya Operasional</td>
                    <td class="text-right">-{{number_format((float)$expenses, $general_setting->decimal, ',', '.')}}</td>
                </tr>
                <tr>
                    <td>Payroll / Gaji</td>
                    <td class="text-right">-{{number_format((float)$payrolls, $general_setting->decimal, ',', '.')}}</td>
                </tr>
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
            {extend: 'print', text: '<i title="print" class="fa fa-print"></i>'},
        ]
    });
</script>
@endpush
