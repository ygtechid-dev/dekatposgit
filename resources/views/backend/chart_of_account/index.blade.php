@extends('backend.layout.main') @section('content')
@if(session()->has('message'))
  <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('message') }}</div>
@endif
@if(session()->has('not_permitted'))
  <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
@endif
@if($errors->has('code'))
<div class="alert alert-danger alert-dismissible text-center">
    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ $errors->first('code') }}</div>
@endif

<section>
    <div class="container-fluid">
        <button class="btn btn-info" data-toggle="modal" data-target="#coa-modal"><i class="dripicons-plus"></i> Tambah Akun</button>
    </div>
    <div class="table-responsive">
        <table id="coa-table" class="table">
            <thead>
                <tr>
                    <th>Kode Akun</th>
                    <th>Nama Akun</th>
                    <th>Tipe</th>
                    <th>Saldo Normal</th>
                    <th>Induk Akun</th>
                    <th>Saldo Awal</th>
                    <th>Kas/Bank</th>
                    <th class="not-exported">{{trans('file.action')}}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lims_coa_all as $coa)
                <tr>
                    <td>{{ $coa->code }}</td>
                    <td>{{ $coa->name }}</td>
                    <td>
                        @php
                            $types = ['asset' => 'Aset', 'liability' => 'Kewajiban', 'equity' => 'Modal', 'revenue' => 'Pendapatan', 'expense' => 'Beban'];
                        @endphp
                        {{ $types[$coa->type] ?? $coa->type }}
                    </td>
                    <td>{{ $coa->normal_balance == 'debit' ? 'Debit' : 'Kredit' }}</td>
                    <td>{{ $coa->parent ? $coa->parent->code . ' - ' . $coa->parent->name : '-' }}</td>
                    <td>{{ number_format((float)$coa->opening_balance, $general_setting->decimal, ',', '.') }}</td>
                    <td>{{ $coa->is_cash_account ? 'Ya' : '-' }}</td>
                    <td>
                        <div class="btn-group">
                            <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">{{trans('file.action')}}
                                <span class="caret"></span>
                                <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default" user="menu">
                                <li>
                                    <button type="button"
                                        data-id="{{ $coa->id }}"
                                        data-code="{{ $coa->code }}"
                                        data-name="{{ $coa->name }}"
                                        data-type="{{ $coa->type }}"
                                        data-normal_balance="{{ $coa->normal_balance }}"
                                        data-parent_id="{{ $coa->parent_id }}"
                                        data-opening_balance="{{ $coa->opening_balance }}"
                                        data-description="{{ $coa->description }}"
                                        data-is_cash_account="{{ $coa->is_cash_account ? 1 : 0 }}"
                                        class="edit-btn btn btn-link" data-toggle="modal" data-target="#editModal"><i class="dripicons-document-edit"></i> {{trans('file.edit')}}</button>
                                </li>
                                @if(!$coa->is_default)
                                <li class="divider"></li>
                                {{ Form::open(['route' => ['chart-of-accounts.destroy', $coa->id], 'method' => 'DELETE'] ) }}
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

<div id="coa-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 id="exampleModalLabel" class="modal-title">Tambah Akun</h5>
                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
            </div>
            <div class="modal-body">
                <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                {!! Form::open(['route' => 'chart-of-accounts.store', 'method' => 'post']) !!}
                    <div class="form-group">
                        <label>Kode Akun *</label>
                        <input type="text" name="code" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Nama Akun *</label>
                        <input type="text" name="name" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Tipe Akun *</label>
                        <select name="type" required class="form-control">
                            <option value="asset">Aset</option>
                            <option value="liability">Kewajiban</option>
                            <option value="equity">Modal</option>
                            <option value="revenue">Pendapatan</option>
                            <option value="expense">Beban</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Saldo Normal *</label>
                        <select name="normal_balance" required class="form-control">
                            <option value="debit">Debit</option>
                            <option value="credit">Kredit</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Induk Akun</label>
                        <select name="parent_id" class="form-control">
                            <option value="">- Tanpa Induk -</option>
                            @foreach($lims_coa_all as $parent_coa)
                            <option value="{{ $parent_coa->id }}">{{ $parent_coa->code }} - {{ $parent_coa->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Saldo Awal</label>
                        <input type="number" name="opening_balance" step="any" value="0" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Deskripsi</label>
                        <textarea name="description" rows="2" class="form-control"></textarea>
                    </div>
                    <div class="form-group">
                        <div class="checkbox">
                            <input type="checkbox" id="add_is_cash_account" name="is_cash_account" value="1">
                            <label for="add_is_cash_account">Jadikan akun Kas/Bank (muncul di semua pilihan akun pembayaran)</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">{{trans('file.save')}}</button>
                    </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>
</div>

<div id="editModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 id="exampleModalLabel" class="modal-title">Edit Akun</h5>
                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
            </div>
            <div class="modal-body">
                {!! Form::open(['route' => ['chart-of-accounts.update', 1], 'method' => 'put']) !!}
                    <div class="form-group">
                        <label>Kode Akun *</label>
                        <input type="text" name="code" required class="form-control">
                        <input type="hidden" name="coa_id">
                    </div>
                    <div class="form-group">
                        <label>Nama Akun *</label>
                        <input type="text" name="name" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Tipe Akun *</label>
                        <select name="type" required class="form-control">
                            <option value="asset">Aset</option>
                            <option value="liability">Kewajiban</option>
                            <option value="equity">Modal</option>
                            <option value="revenue">Pendapatan</option>
                            <option value="expense">Beban</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Saldo Normal *</label>
                        <select name="normal_balance" required class="form-control">
                            <option value="debit">Debit</option>
                            <option value="credit">Kredit</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Induk Akun</label>
                        <select name="parent_id" class="form-control">
                            <option value="">- Tanpa Induk -</option>
                            @foreach($lims_coa_all as $parent_coa)
                            <option value="{{ $parent_coa->id }}">{{ $parent_coa->code }} - {{ $parent_coa->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Saldo Awal</label>
                        <input type="number" name="opening_balance" step="any" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Deskripsi</label>
                        <textarea name="description" rows="2" class="form-control"></textarea>
                    </div>
                    <div class="form-group">
                        <div class="checkbox">
                            <input type="checkbox" id="edit_is_cash_account" name="is_cash_account" value="1">
                            <label for="edit_is_cash_account">Jadikan akun Kas/Bank (muncul di semua pilihan akun pembayaran)</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">{{trans('file.update')}}</button>
                    </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>
</div>

@endsection
@push('scripts')
<script type="text/javascript">
    $("ul#account").siblings('a').attr('aria-expanded','true');
    $("ul#account").addClass("show");
    $("ul#account #coa-list-menu").addClass("active");

    function confirmDelete() {
        if (confirm("Are you sure want to delete?")) {
            return true;
        }
        return false;
    }

    $('.edit-btn').on('click', function() {
        $("#editModal input[name='code']").val( $(this).data('code') );
        $("#editModal input[name='name']").val( $(this).data('name') );
        $("#editModal select[name='type']").val( $(this).data('type') );
        $("#editModal select[name='normal_balance']").val( $(this).data('normal_balance') );
        $("#editModal select[name='parent_id']").val( $(this).data('parent_id') );
        $("#editModal input[name='opening_balance']").val( $(this).data('opening_balance') );
        $("#editModal textarea[name='description']").val( $(this).data('description') );
        $("#editModal input[name='coa_id']").val( $(this).data('id') );
        $("#editModal input[name='is_cash_account']").prop('checked', $(this).data('is_cash_account') == 1);
    });

    var table = $('#coa-table').DataTable( {
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
