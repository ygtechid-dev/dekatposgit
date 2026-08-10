@extends('backend.layout.main') @section('content')

<section class="forms">
    <div class="container-fluid">
        <h3>Buku Besar (General Ledger)</h3>
        <div class="row mt-3">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="{{ route('general_ledger') }}" class="form-inline">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="mr-2">{{ trans('file.Start Date') }}</label>
                                        <input type="text" name="start_date" class="form-control date" value="{{ $start_date }}" autocomplete="off" />
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="mr-2">{{ trans('file.End Date') }}</label>
                                        <input type="text" name="end_date" class="form-control date" value="{{ $end_date }}" autocomplete="off" />
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-primary mt-4">{{ trans('file.submit') }}</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="table-responsive mb-4">
        <table id="ledger-table" class="table table-hover table-striped">
            <thead>
                <tr>
                    <th class="not-exported"></th>
                    <th>{{ trans('file.date') }}</th>
                    <th>{{ trans('file.Account') }}</th>
                    <th>{{ trans('file.Reference No') }}</th>
                    <th>{{ trans('file.Description') }}</th>
                    <th>{{ trans('file.Debit') }}</th>
                    <th>{{ trans('file.Credit') }}</th>
                    <th>{{ trans('file.Balance') }}</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $balances = [];
                    foreach ($lims_account_list as $acc) {
                        $balances[$acc->id] = $acc->initial_balance;
                    }
                @endphp
                @foreach($all_transactions as $key => $trx)
                @php
                    $account_id = $lims_account_list->where('name', $trx->account_name)->first()->id ?? 0;
                    if (isset($balances[$account_id])) {
                        $balances[$account_id] = $balances[$account_id] + $trx->credit - $trx->debit;
                    } else {
                        $balances[$account_id] = $trx->credit - $trx->debit;
                    }
                    $running_balance = $balances[$account_id];
                    $description = '';
                    if ($trx->related_type == 'sale') $description = 'Penjualan';
                    elseif ($trx->related_type == 'purchase') $description = 'Pembelian';
                    elseif ($trx->related_type == 'expense') $description = 'Biaya';
                    elseif ($trx->related_type == 'sale_return') $description = 'Retur Penjualan';
                    elseif ($trx->related_type == 'purchase_return') $description = 'Retur Pembelian';
                    elseif ($trx->related_type == 'payroll') $description = 'Gaji';
                    elseif ($trx->related_type == 'money_transfer_in') $description = 'Transfer Masuk';
                    elseif ($trx->related_type == 'money_transfer_out') $description = 'Transfer Keluar';
                @endphp
                <tr>
                    <td>{{ $key }}</td>
                    <td data-sort="{{ date('Y-m-d', strtotime($trx->created_at)) }}">{{ date($general_setting->date_format, strtotime($trx->created_at)) }}</td>
                    <td>{{ $trx->account_name }} [{{ $trx->account_no }}]</td>
                    <td>{{ $trx->reference_no }}</td>
                    <td>{{ $description }}</td>
                    <td>{{ number_format((float)$trx->debit, $general_setting->decimal, '.', '') }}</td>
                    <td>{{ number_format((float)$trx->credit, $general_setting->decimal, '.', '') }}</td>
                    <td>{{ number_format((float)$running_balance, $general_setting->decimal, '.', '') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="tfoot active">
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th>{{ trans('file.Total') }}</th>
                <th></th>
                <th></th>
                <th></th>
            </tfoot>
        </table>
    </div>
</section>

@endsection
@push('scripts')
<script type="text/javascript">
    $("ul#account").siblings('a').attr('aria-expanded','true');
    $("ul#account").addClass("show");
    $("ul#account #general-ledger-menu").addClass("active");

    $('.date').datepicker({
        format: "yyyy-mm-dd",
        autoclose: true,
        todayHighlight: true
    });

    var table = $('#ledger-table').DataTable({
        "order": [[1, "desc"]],
        'language': {
            'lengthMenu': '_MENU_ {{ trans("file.records per page") }}',
            "info":      '<small>{{ trans("file.Showing") }} _START_ - _END_ (_TOTAL_)</small>',
            "search":  '{{ trans("file.Search") }}',
            'paginate': {
                'previous': '<i class="dripicons-chevron-left"></i>',
                'next': '<i class="dripicons-chevron-right"></i>'
            }
        },
        'columnDefs': [
            {
                "orderable": false,
                'targets': [0, 4]
            },
            {
                'render': function(data, type, row, meta){
                    if(type === 'display'){
                        data = '<div class="checkbox"><input type="checkbox" class="dt-checkboxes"><label></label></div>';
                    }
                    return data;
                },
                'checkboxes': {
                   'selectRow': true,
                   'selectAllRender': '<div class="checkbox"><input type="checkbox" class="dt-checkboxes"><label></label></div>'
                },
                'targets': [0]
            }
        ],
        'select': { style: 'multi', selector: 'td:first-child'},
        'lengthMenu': [[10, 25, 50, -1], [10, 25, 50, "All"]],
        dom: '<"row"lfB>rtip',
        buttons: [
            {
                extend: 'pdf',
                text: '<i title="export to pdf" class="fa fa-file-pdf-o"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
                    rows: ':visible'
                },
                footer: true,
                customize: function(doc) {
                    doc.content[1].table.widths = ['auto', 'auto', '*', 'auto', 'auto', 'auto', 'auto', 'auto'];
                }
            },
            {
                extend: 'csv',
                text: '<i title="export to csv" class="fa fa-file-text-o"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
                    rows: ':visible'
                },
                footer: true
            },
            {
                extend: 'print',
                text: '<i title="print" class="fa fa-print"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
                    rows: ':visible'
                },
                footer: true,
                customize: function(win) {
                    $(win.document.body).find('h1').text('Buku Besar (General Ledger)');
                    $(win.document.body).find('table').addClass('compact').css('font-size', 'inherit');
                }
            },
            {
                extend: 'colvis',
                text: '<i title="column visibility" class="fa fa-eye"></i>',
                columns: ':gt(0)'
            },
        ],
        drawCallback: function () {
            var api = this.api();
            // Sum debit column (5) and credit column (6)
            var debitSum = api.column(5, {page: 'current'}).data().reduce(function(a, b) {
                return parseFloat(String(a).replace(/[^0-9.-]/g, '')) + parseFloat(String(b).replace(/[^0-9.-]/g, ''));
            }, 0);
            var creditSum = api.column(6, {page: 'current'}).data().reduce(function(a, b) {
                return parseFloat(String(a).replace(/[^0-9.-]/g, '')) + parseFloat(String(b).replace(/[^0-9.-]/g, ''));
            }, 0);
            $(api.column(5).footer()).html(debitSum.toFixed({{ $general_setting->decimal }}));
            $(api.column(6).footer()).html(creditSum.toFixed({{ $general_setting->decimal }}));
        }
    });
</script>
@endpush
