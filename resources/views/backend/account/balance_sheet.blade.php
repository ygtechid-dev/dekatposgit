@extends('backend.layout.main') @section('content')

<section class="forms">
    <div class="container-fluid">
        <h3>Neraca Keuangan</h3>
    </div>
    {!! Form::open(['route' => 'accounts.balancesheet', 'method' => 'get', 'id' => 'balance-sheet-filter']) !!}
    <div class="row mb-3">
        <div class="col-md-3">
            <label>Filter Bulan</label>
            <input type="month" name="filter_month" id="filter_month" class="form-control" value="{{ date('Y-m', strtotime($end_date)) }}">
        </div>
        <div class="col-md-3">
            <label>Per Tanggal</label>
            <input type="date" name="end_date" id="end_date" class="form-control" value="{{$end_date}}">
        </div>
        <div class="col-md-2">
            <label>&nbsp;</label>
            <button class="btn btn-primary form-control" type="submit">{{trans('file.submit')}}</button>
        </div>
    </div>
    {!! Form::close() !!}

    <div class="row">
        <div class="col-md-6">
            <div class="table-responsive mb-4">
                <table id="aktiva-table" class="table table-hover">
                    <thead>
                        <tr>
                            <th>Aktiva</th>
                            <th class="text-right">{{trans('file.Amount')}}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($assets as $asset)
                        <tr>
                            <td>{{$asset['name']}}</td>
                            <td class="text-right">{{number_format((float)$asset['amount'], $general_setting->decimal, ',', '.')}}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="tfoot active">
                        <tr>
                            <th>Total Aktiva</th>
                            <th class="text-right">{{number_format((float)$total_assets, $general_setting->decimal, ',', '.')}}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <div class="col-md-6">
            <div class="table-responsive mb-4">
                <table id="pasiva-table" class="table table-hover">
                    <thead>
                        <tr>
                            <th>Pasiva</th>
                            <th class="text-right">{{trans('file.Amount')}}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($liabilities as $liability)
                        <tr>
                            <td>{{$liability['name']}}</td>
                            <td class="text-right">{{number_format((float)$liability['amount'], $general_setting->decimal, ',', '.')}}</td>
                        </tr>
                        @endforeach
                        <tr class="active">
                            <th colspan="2">Modal / Ekuitas</th>
                        </tr>
                        @foreach($equity_items as $equity_item)
                        <tr>
                            <td>{{$equity_item['name']}}</td>
                            <td class="text-right">{{number_format((float)$equity_item['amount'], $general_setting->decimal, ',', '.')}}</td>
                        </tr>
                        @endforeach
                        <tr>
                            <th>Total Modal / Ekuitas</th>
                            <th class="text-right">{{number_format((float)$equity, $general_setting->decimal, ',', '.')}}</th>
                        </tr>
                    </tbody>
                    <tfoot class="tfoot active">
                        <tr>
                            <th>Total Pasiva</th>
                            <th class="text-right">{{number_format((float)($total_liabilities + $equity), $general_setting->decimal, ',', '.')}}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <h4>Detail Saldo Akun</h4>
    </div>
    <div class="table-responsive mb-4">
        <table id="account-table" class="table table-hover">
            <thead>
                <tr>
                    <th>Kode Akun</th>
                    <th>{{trans('file.name')}}</th>
                    <th>{{trans('file.Debit')}}</th>
                    <th>{{trans('file.Credit')}}</th>
                    <th>{{trans('file.Balance')}}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lims_account_list as $key=>$account)
                <tr>
                    <td>{{ $account->coa_label_code }}</td>
                    <td>{{ $account->coa_label_name }}</td>
                    <td>{{number_format((float)$account_debit[$key], $general_setting->decimal, ',', '.')}}</td>
                    <td>{{number_format((float)$account_credit[$key], $general_setting->decimal, ',', '.')}}</td>
                    <td>{{number_format((float)$account_balance[$key], $general_setting->decimal, ',', '.')}}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

@endsection
@push('scripts')
<script type="text/javascript">
    $("ul#account").siblings('a').attr('aria-expanded','true');
    $("ul#account").addClass("show");
    $("ul#account #balance-sheet-menu").addClass("active");

    // "Filter Bulan" is a convenience shortcut: picking a month sets the
    // "Per Tanggal" field to the last day of that month before submitting,
    // since the Neraca is always computed as-of a single date.
    $('#filter_month').on('change', function() {
        var val = $(this).val();
        if (!val) return;
        var parts = val.split('-');
        var year = parseInt(parts[0], 10);
        var month = parseInt(parts[1], 10);
        var lastDay = new Date(year, month, 0);
        var mm = String(lastDay.getMonth() + 1).padStart(2, '0');
        var dd = String(lastDay.getDate()).padStart(2, '0');
        $('#end_date').val(lastDay.getFullYear() + '-' + mm + '-' + dd);
    });
    $('#end_date').on('change', function() {
        $('#filter_month').val('');
    });

    function reportButtons() {
        return [
            {extend: 'pdf', text: '<i title="export to pdf" class="fa fa-file-pdf-o"></i>'},
            {extend: 'excel', text: '<i title="export to excel" class="dripicons-document-new"></i>'},
            {extend: 'csv', text: '<i title="export to csv" class="fa fa-file-text-o"></i>'},
            {extend: 'print', text: '<i title="print" class="fa fa-print"></i>'},
        ];
    }

    $('#aktiva-table').DataTable({
        paging: false,
        searching: false,
        info: false,
        ordering: false,
        dom: '<"row"B>rt',
        buttons: reportButtons()
    });
    $('#pasiva-table').DataTable({
        paging: false,
        searching: false,
        info: false,
        ordering: false,
        dom: '<"row"B>rt',
        buttons: reportButtons()
    });

    $('#account-table').DataTable({
        "order": [],
        'language': {
            'lengthMenu': '_MENU_ {{trans("file.records per page")}}',
            "info": '<small>{{trans("file.Showing")}} _START_ - _END_ (_TOTAL_)</small>',
            "search": '{{trans("file.Search")}}',
            'paginate': {
                'previous': '<i class="dripicons-chevron-left"></i>',
                'next': '<i class="dripicons-chevron-right"></i>'
            }
        },
        dom: '<"row"lfB>rtip',
        buttons: [
            {extend: 'pdf', text: '<i title="export to pdf" class="fa fa-file-pdf-o"></i>'},
            {extend: 'excel', text: '<i title="export to excel" class="dripicons-document-new"></i>'},
            {extend: 'csv', text: '<i title="export to csv" class="fa fa-file-text-o"></i>'},
            {extend: 'print', text: '<i title="print" class="fa fa-print"></i>'},
            {extend: 'colvis', text: '<i title="column visibility" class="fa fa-eye"></i>'}
        ]
    });
</script>
@endpush
