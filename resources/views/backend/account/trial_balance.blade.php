@extends('backend.layout.main') @section('content')

<section class="forms">
    <div class="container-fluid">
        <h3>Neraca Saldo</h3>
    </div>
    {!! Form::open(['route' => 'accounts.trialbalance', 'method' => 'get']) !!}
    <div class="row mb-3">
        <div class="col-md-3">
            <input type="date" name="end_date" class="form-control" value="{{$end_date}}">
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary" type="submit">{{trans('file.submit')}}</button>
        </div>
    </div>
    {!! Form::close() !!}

    <div class="table-responsive mb-4">
        <table id="trial-balance-table" class="table table-hover">
            <thead>
                <tr>
                    <th>Kode Akun</th>
                    <th>Nama Akun</th>
                    <th>Debit</th>
                    <th>Kredit</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                <tr>
                    <td>{{ $row['code'] }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td>{{ number_format((float)$row['debit'], $general_setting->decimal, ',', '.') }}</td>
                    <td>{{ number_format((float)$row['credit'], $general_setting->decimal, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="tfoot active">
                <tr>
                    <th colspan="2" class="text-right">Total</th>
                    <th>{{ number_format((float)$total_debit, $general_setting->decimal, ',', '.') }}</th>
                    <th>{{ number_format((float)$total_credit, $general_setting->decimal, ',', '.') }}</th>
                </tr>
            </tfoot>
        </table>
        @if(abs($total_debit - $total_credit) > 0.01)
        <div class="alert alert-danger">Peringatan: Neraca saldo tidak seimbang. Selisih: {{ number_format(abs($total_debit - $total_credit), $general_setting->decimal, ',', '.') }}</div>
        @else
        <div class="alert alert-success">Neraca saldo seimbang.</div>
        @endif
    </div>
</section>

@endsection
@push('scripts')
<script type="text/javascript">
    $("ul#account").siblings('a').attr('aria-expanded','true');
    $("ul#account").addClass("show");
    $("ul#account #trial-balance-menu").addClass("active");

    $('#trial-balance-table').DataTable( {
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
            { extend: 'print', text: '<i title="print" class="fa fa-print"></i>' },
        ]
    } );
</script>
@endpush
