@extends('backend.layout.main') @section('content')
@if(session()->has('message'))
  <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('message') }}</div>
@endif
@if(session()->has('not_permitted'))
  <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
@endif

<section>
    <div class="container-fluid">
        <div class="card">
            <div class="card-header mt-2">
                <h3 class="text-center">Jurnal Umum</h3>
            </div>
            {!! Form::open(['route' => 'journals.index', 'method' => 'get']) !!}
            <div class="row mb-3">
                <div class="col-md-4 offset-md-3 mt-3">
                    <div class="form-group row">
                        <label class="d-tc mt-2"><strong>Periode</strong> &nbsp;</label>
                        <div class="d-tc">
                            <div class="input-group">
                                <input type="date" name="starting_date" value="{{$starting_date}}" class="form-control" />
                                <input type="date" name="ending_date" value="{{$ending_date}}" class="form-control" />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 mt-3">
                    <div class="form-group">
                        <button class="btn btn-primary" type="submit">{{trans('file.submit')}}</button>
                    </div>
                </div>
            </div>
            {!! Form::close() !!}
        </div>
        <a class="btn btn-info" href="{{ route('journals.create') }}"><i class="dripicons-plus"></i> Tambah Jurnal Manual</a>
    </div>
    <div class="table-responsive">
        <table id="journal-table" class="table">
            <thead>
                <tr>
                    <th>No. Jurnal</th>
                    <th>Tanggal</th>
                    <th>Deskripsi</th>
                    <th>Sumber</th>
                    <th>Total Debit</th>
                    <th>Total Kredit</th>
                    <th class="not-exported">{{trans('file.action')}}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lims_journal_all as $journal)
                @php
                    $totalDebit = $journal->details->sum('debit');
                    $totalCredit = $journal->details->sum('credit');
                @endphp
                <tr>
                    <td>{{ $journal->journal_no }}</td>
                    <td>{{ date(config('date_format'), strtotime($journal->journal_date)) }}</td>
                    <td>{{ $journal->description }}</td>
                    <td>
                        @if($journal->is_manual)
                            <span class="badge badge-secondary">Manual</span>
                        @else
                            <span class="badge badge-info">{{ ucfirst(str_replace('_', ' ', $journal->reference_type)) }}</span>
                        @endif
                    </td>
                    <td>{{ number_format($totalDebit, $general_setting->decimal, ',', '.') }}</td>
                    <td>{{ number_format($totalCredit, $general_setting->decimal, ',', '.') }}</td>
                    <td>
                        <div class="btn-group">
                            <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">{{trans('file.action')}}
                                <span class="caret"></span>
                                <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default" user="menu">
                                <li><button type="button" class="btn btn-link view-btn" data-id="{{ $journal->id }}"><i class="dripicons-preview"></i> Lihat Detail</button></li>
                                @if($journal->is_manual)
                                <li class="divider"></li>
                                <li><a class="btn btn-link" href="{{ route('journals.edit', $journal->id) }}"><i class="dripicons-document-edit"></i> {{trans('file.edit')}}</a></li>
                                <li class="divider"></li>
                                {{ Form::open(['route' => ['journals.destroy', $journal->id], 'method' => 'DELETE'] ) }}
                                <li>
                                    <button type="submit" class="btn btn-link" onclick="return confirmDelete()"><i class="dripicons-trash"></i> {{trans('file.delete')}}</button>
                                </li>
                                {{ Form::close() }}
                                @endif
                            </ul>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

<div id="viewModal" tabindex="-1" role="dialog" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Jurnal</h5>
                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
            </div>
            <div class="modal-body">
                <table class="table" id="journal-detail-table">
                    <thead>
                        <tr>
                            <th>Akun</th>
                            <th>Debit</th>
                            <th>Kredit</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection
@push('scripts')
<script type="text/javascript">
    $("ul#account").siblings('a').attr('aria-expanded','true');
    $("ul#account").addClass("show");
    $("ul#account #journal-list-menu").addClass("active");

    function confirmDelete() {
        if (confirm("Are you sure want to delete?")) {
            return true;
        }
        return false;
    }

    $('.view-btn').on('click', function() {
        var id = $(this).data('id');
        $.get('journals/' + id, function(journal) {
            var rows = '';
            $.each(journal.details, function(i, d) {
                var fmt = function(n) { return parseFloat(n || 0).toLocaleString('id-ID', { minimumFractionDigits: {{$general_setting->decimal}}, maximumFractionDigits: {{$general_setting->decimal}} }); };
                rows += '<tr><td>' + (d.account ? d.account.code + ' - ' + d.account.name : '-') + '</td><td>' + fmt(d.debit) + '</td><td>' + fmt(d.credit) + '</td><td>' + (d.note ?? '') + '</td></tr>';
            });
            $('#journal-detail-table tbody').html(rows);
            $('#viewModal').modal('show');
        });
    });

    var table = $('#journal-table').DataTable( {
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
        'lengthMenu': [[10, 25, 50, -1], [10, 25, 50, "All"]],
        dom: '<"row"lfB>rtip',
        buttons: [
            { extend: 'pdf', text: '<i title="export to pdf" class="fa fa-file-pdf-o"></i>', exportOptions: { columns: ':visible:Not(.not-exported)' } },
            { extend: 'excel', text: '<i title="export to excel" class="dripicons-document-new"></i>', exportOptions: { columns: ':visible:Not(.not-exported)' } },
            { extend: 'csv', text: '<i title="export to csv" class="fa fa-file-text-o"></i>', exportOptions: { columns: ':visible:Not(.not-exported)' } },
            { extend: 'print', text: '<i title="print" class="fa fa-print"></i>', exportOptions: { columns: ':visible:Not(.not-exported)' } },
            { extend: 'colvis', text: '<i title="column visibility" class="fa fa-eye"></i>' },
        ]
    } );
</script>
@endpush
