@extends('backend.layout.main') @section('content')

<section class="forms">
    <div class="container-fluid">
        <h3>Buku Besar</h3>
    </div>
    {!! Form::open(['route' => 'accounts.generalledger', 'method' => 'get']) !!}
    <div class="row mb-3">
        <div class="col-md-4">
            <select name="chart_of_account_id" class="selectpicker form-control" data-live-search="true" data-live-search-style="begins" title="- Pilih Akun -" required>
                <option value="all" @if($chart_of_account_id == 'all') selected @endif>- Semua Akun -</option>
                @foreach($lims_coa_all as $coa)
                <option value="{{ $coa->id }}" @if($chart_of_account_id == $coa->id) selected @endif>{{ $coa->code }} - {{ $coa->name }}</option>
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
    <div class="mb-2"><strong>Akun:</strong> {{ $ledger['coa']->code }} - {{ $ledger['coa']->name }}</div>
    <div class="table-responsive mb-4">
        <table id="ledger-table-{{ $index }}" class="table table-hover ledger-table">
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
    @endforeach
</section>

@endsection
@push('scripts')
<script type="text/javascript">
    $("ul#account").siblings('a').attr('aria-expanded','true');
    $("ul#account").addClass("show");
    $("ul#account #general-ledger-menu").addClass("active");

    $('.ledger-table').each(function() {
        $(this).DataTable( {
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
    });
</script>
@endpush
