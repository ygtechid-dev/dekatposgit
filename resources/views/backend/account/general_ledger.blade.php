@extends('backend.layout.main') @section('content')

<style>
    .ledger-print-header { display: none; }
    .ledger-account-title { font-weight: 700; margin: 14px 0 6px; }
    .ledger-table th:nth-child(4),
    .ledger-table th:nth-child(5),
    .ledger-table th:nth-child(6),
    .ledger-table td:nth-child(4),
    .ledger-table td:nth-child(5),
    .ledger-table td:nth-child(6) { text-align: right; white-space: nowrap; }
    .ledger-table td:nth-child(3) { white-space: normal; word-break: break-word; }

    @media print {
        @page { size: A4 landscape; margin: 10mm; }
        html, body { background: #fff !important; color: #000 !important; font-size: 10px; }
        #loader,
        nav.side-navbar,
        body > .page > header,
        footer,
        #print-ledger-page,
        #ledger-filter-form,
        .dt-buttons,
        .dataTables_length,
        .dataTables_filter,
        .dataTables_info,
        .dataTables_paginate,
        .dataTables_processing { display: none !important; }
        .page {
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            left: 0 !important;
            transform: none !important;
        }
        .content-inner,
        .container-fluid,
        section.forms {
            width: 100% !important;
            max-width: none !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        .ledger-print-header {
            display: block !important;
            text-align: center;
            margin-bottom: 10px;
        }
        .ledger-print-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
        }
        .ledger-print-header .period {
            margin-top: 3px;
            font-size: 11px;
        }
        .ledger-account-block {
            page-break-inside: avoid;
            break-inside: avoid;
            margin-bottom: 12px !important;
        }
        .ledger-account-title {
            margin: 8px 0 4px !important;
            font-size: 11px;
        }
        .table-responsive {
            overflow: visible !important;
            display: block !important;
            width: 100% !important;
        }
        table.ledger-table {
            width: 100% !important;
            border-collapse: collapse !important;
            table-layout: fixed;
            margin: 0 0 8px !important;
        }
        table.ledger-table th,
        table.ledger-table td {
            border: 1px solid #333 !important;
            padding: 4px 5px !important;
            vertical-align: top !important;
            color: #000 !important;
            background: #fff !important;
        }
        table.ledger-table thead th {
            background: #eee !important;
            font-weight: 700 !important;
            text-align: center;
        }
        table.ledger-table th:nth-child(1), table.ledger-table td:nth-child(1) { width: 10%; }
        table.ledger-table th:nth-child(2), table.ledger-table td:nth-child(2) { width: 14%; }
        table.ledger-table th:nth-child(3), table.ledger-table td:nth-child(3) { width: 38%; }
        table.ledger-table th:nth-child(4), table.ledger-table td:nth-child(4) { width: 12%; }
        table.ledger-table th:nth-child(5), table.ledger-table td:nth-child(5) { width: 12%; }
        table.ledger-table th:nth-child(6), table.ledger-table td:nth-child(6) { width: 14%; }
    }
</style>

<section class="forms ledger-print-area">
    <div class="container-fluid">
        <h3>Buku Besar</h3>
        <button type="button" id="print-ledger-page" class="btn btn-info mb-3"><i class="fa fa-print"></i> Print Buku Besar</button>
    </div>
    <div class="ledger-print-header">
        <h3>Buku Besar</h3>
        <div class="period">Periode: {{ date(config('date_format'), strtotime($start_date)) }} - {{ date(config('date_format'), strtotime($end_date)) }}</div>
    </div>
    {!! Form::open(['route' => 'accounts.generalledger', 'method' => 'get', 'id' => 'ledger-filter-form']) !!}
    <div class="row mb-3">
        <div class="col-md-4">
            <select name="chart_of_account_id" class="selectpicker form-control" data-live-search="true" data-live-search-style="contains" data-size="10" title="- Pilih Akun -" required>
                <option value="all" @if($chart_of_account_id == 'all') selected @endif>- Semua Akun -</option>
                @foreach($lims_coa_all as $coa)
                <option value="{{ $coa->id }}" data-tokens="{{ $coa->code }} {{ $coa->name }}" @if($chart_of_account_id == $coa->id) selected @endif>{{ $coa->code }} - {{ $coa->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <input type="date" name="start_date" class="form-control" value="{{$start_date}}">
        </div>
        <div class="col-md-2">
            <input type="date" name="end_date" class="form-control" value="{{$end_date}}">
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary" type="submit">{{trans('file.submit')}}</button>
        </div>
    </div>
    {!! Form::close() !!}

    @foreach($all_ledgers as $index => $ledger)
    <div class="ledger-account-block">
    <div class="ledger-account-title"><strong>Akun:</strong> {{ $ledger['coa']->code }} - {{ $ledger['coa']->name }}</div>
    <div class="table-responsive mb-4">
        <table id="ledger-table-{{ $index }}" class="table table-hover table-bordered ledger-table">
            <thead>
                <tr>
                    <th>{{trans('file.date')}}</th>
                    <th>No. Jurnal</th>
                    <th>Deskripsi</th>
                    <th>Debit</th>
                    <th>Kredit</th>
                    <th>Saldo</th>
                </tr>
            </thead>
            <tbody>
                <tr class="active">
                    <td colspan="5"><strong>Saldo Awal</strong></td>
                    <td><strong>{{ number_format((float)$ledger['opening_balance'], $general_setting->decimal, ',', '.') }}</strong></td>
                </tr>
                @foreach($ledger['ledger_rows'] as $row)
                <tr>
                    <td>{{ date(config('date_format'), strtotime($row['date'])) }}</td>
                    <td>{{ $row['journal_no'] }}</td>
                    <td>{{ $row['description'] }}</td>
                    <td>{{ number_format((float)$row['debit'], $general_setting->decimal, ',', '.') }}</td>
                    <td>{{ number_format((float)$row['credit'], $general_setting->decimal, ',', '.') }}</td>
                    <td>{{ number_format((float)$row['balance'], $general_setting->decimal, ',', '.') }}</td>
                </tr>
                @endforeach
                <tr class="active">
                    <td colspan="5"><strong>Saldo Akhir</strong></td>
                    <td><strong>{{ number_format((float)$ledger['closing_balance'], $general_setting->decimal, ',', '.') }}</strong></td>
                </tr>
            </tbody>
        </table>
    </div>
    </div>
    @endforeach
</section>

@endsection
@push('scripts')
<script type="text/javascript">
    $("ul#account").siblings('a').attr('aria-expanded','true');
    $("ul#account").addClass("show");
    $("ul#account #general-ledger-menu").addClass("active");

    var ledgerTables = [];
    var ledgerPrintState = [];

    $('#print-ledger-page').on('click', function() {
        ledgerPrintState = [];
        $.each(ledgerTables, function(i, table) {
            ledgerPrintState.push(table.page.len());
            table.page.len(-1).draw(false);
        });
        setTimeout(function() {
            window.print();
        }, 250);
    });

    window.addEventListener('afterprint', function() {
        $.each(ledgerTables, function(i, table) {
            if (typeof ledgerPrintState[i] !== 'undefined') {
                table.page.len(ledgerPrintState[i]).draw(false);
            }
        });
    });

    $('.ledger-table').each(function() {
        var table = $(this).DataTable( {
            "order": [],
            'language': {
                'lengthMenu': '_MENU_ {{trans("file.records per page")}}',
                 "info":      '<small>{{trans("file.Showing")}} _START_ - _END_ (_TOTAL_)</small>',
                "search":  '{{trans("file.Search")}}',
                'paginate': {
                        'previous': '<i class="dripicons-chevron-left"></i>',
                        'next': '<i class="dripicons-chevron-right"></i>'
                }
            },
            'lengthMenu': [[10, 25, 50, -1], [10, 25, 50, "All"]],
            dom: '<"row"lfB>rtip',
            buttons: [
                { extend: 'pdf', text: '<i title="export to pdf" class="fa fa-file-pdf-o"></i>' },
                { extend: 'excel', text: '<i title="export to excel" class="dripicons-document-new"></i>' },
                { extend: 'csv', text: '<i title="export to csv" class="fa fa-file-text-o"></i>' },
                { extend: 'print', text: '<i title="print" class="fa fa-print"></i>' },
            ]
        } );
        ledgerTables.push(table);
    });
</script>
@endpush
