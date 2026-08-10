@extends('backend.layout.main') @section('content')

@if(session()->has('message'))
  <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('message') }}</div>
@endif
@if(session()->has('not_permitted'))
  <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
@endif

<section>
    <div class="table-responsive">
        <table id="cash-register-table" class="table">
            <thead>
                <tr>
                    <th class="not-exported"></th>
                    <th>{{trans('file.User')}}</th>
                    <th>{{trans('file.Warehouse')}}</th>
                    <th>Modal Kasir</th>
                    <th>Setor Kasir</th>
                    <th>Catatan</th>
                    <th>{{trans('file.Opened at')}}</th>
                    <th>{{trans('file.Closed at')}}</th>
                    <th>{{trans('file.Status')}}</th>
                    <th>Approval</th>
                    <th class="not-exported">{{trans('file.action')}}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lims_cash_register_all as $key=>$cash_register)
                <tr data-id="{{$cash_register->id}}">
                    <td>{{$key}}</td>
                    <td>{{ $cash_register->user->name }}</td>
                    <td>{{ $cash_register->warehouse->name }}</td>
                    <td>{{ number_format((float)$cash_register->cash_in_hand, $general_setting->decimal, ',', '.') }}</td>
                    <td>{{ $cash_register->deposit_amount !== null ? number_format((float)$cash_register->deposit_amount, $general_setting->decimal, ',', '.') : 'N/A' }}</td>
                    <td>{{ $cash_register->closing_note ?: '-' }}</td>
                    <td>{{ date($general_setting->date_format . " h:i:s", strtotime($cash_register->created_at)) }}</td>
                    @if($cash_register->status)
                        <td>N/A</td>
                        <td><div class="badge badge-success">{{trans('file.Active')}}</div></td>
                    @else
                        <td>{{ date($general_setting->date_format . " h:i:s", strtotime($cash_register->updated_at)) }}</td>
                        <td><div class="badge badge-danger">{{trans('file.Closed')}}</div></td>
                    @endif
                    <td>
                        @if($cash_register->approval_status == 'approved')
                            <div class="badge badge-success">Approved</div>
                        @elseif($cash_register->approval_status == 'pending')
                            <div class="badge badge-warning">Pending</div>
                        @else
                            <div class="badge badge-info">Open</div>
                        @endif
                    </td>
                    <td>
                        <div class="btn-group">
                            <button type="button" data-id="{{$cash_register->id}}" class="register-details-btn btn btn-sm btn-info" data-toggle="modal" data-target="#register-details-modal" title="{{trans('file.View')}}"><i class="dripicons-preview"></i></button>
                            @if(\Auth::user()->role_id <= 2)
                            <a href="{{route('cashRegister.print', $cash_register->id)}}" target="_blank" class="btn btn-sm btn-primary" title="Print"><i class="fa fa-print"></i></a>
                            @endif
                            @if(!$cash_register->status)
                            @if(\Auth::user()->role_id <= 2)
                            <button type="button"
                                class="edit-deposit-btn btn btn-sm btn-warning"
                                data-id="{{$cash_register->id}}"
                                data-closing_cash_amount="{{$cash_register->closing_cash_amount}}"
                                data-deposit_amount="{{$cash_register->deposit_amount}}"
                                data-closing_note="{{$cash_register->closing_note}}"
                                data-toggle="modal"
                                data-target="#edit-deposit-modal"
                                title="{{trans('file.edit')}}"><i class="dripicons-document-edit"></i></button>
                            @if($cash_register->approval_status != 'approved')
                            <button type="button" data-id="{{$cash_register->id}}" class="approve-btn btn btn-sm btn-success" data-toggle="modal" data-target="#approve-modal" title="Approve"><i class="fa fa-check"></i></button>
                            @endif
                            @endif
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div id="register-details-modal" tabindex="-1" role="dialog" aria-hidden="true" class="modal fade text-left">
        <div role="document" class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">{{trans('file.Cash Register Details')}}</h5>
              <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-12">
                        <table class="table table-hover">
                            <tbody>
                                <tr><td>Modal Kembalian:</td><td id="cash_in_hand" class="text-right"></td></tr>
                                @if(\Auth::user()->role_id <= 2)
                                <tr><td>{{trans('file.Total Sale Amount')}}:</td><td id="total_sale_amount" class="text-right"></td></tr>
                                <tr><td>{{trans('file.Total Payment')}}:</td><td id="total_payment" class="text-right"></td></tr>
                                <tr><td>Pembayaran Cash Penjualan:</td><td id="cash_sale_payment" class="text-right"></td></tr>
                                <tr><td>Pembayaran Cash Piutang:</td><td id="due_payment" class="text-right"></td></tr>
                                <tr><td>{{trans('file.Cash Payment')}}:</td><td id="cash_payment" class="text-right"></td></tr>
                                <tr><td>{{trans('file.Credit Card Payment')}}:</td><td id="credit_card_payment" class="text-right"></td></tr>
                                <tr><td>{{trans('file.Cheque Payment')}}:</td><td id="cheque_payment" class="text-right"></td></tr>
                                <tr><td>{{trans('file.Gift Card Payment')}}:</td><td id="gift_card_payment" class="text-right"></td></tr>
                                <tr><td>{{trans('file.Paypal Payment')}}:</td><td id="paypal_payment" class="text-right"></td></tr>
                                <tr><td>{{trans('file.Total Sale Return')}}:</td><td id="total_sale_return" class="text-right"></td></tr>
                                <tr><td>{{trans('file.Total Expense')}}:</td><td id="total_expense" class="text-right"></td></tr>
                                <tr><td><strong>Kas Sistem:</strong></td><td id="expected_cash_amount" class="text-right"></td></tr>
                                <tr><td>Uang Fisik Dihitung:</td><td id="closing_cash_amount" class="text-right"></td></tr>
                                <tr><td>Setor Kasir:</td><td id="deposit_amount" class="text-right"></td></tr>
                                <tr><td>Selisih:</td><td id="cash_difference" class="text-right"></td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                    <div class="col-md-12" id="closing-section">
                        <form action="{{route('cashRegister.close')}}" method="POST">
                            @csrf
                            <input type="hidden" name="cash_register_id">
                            <div class="form-group">
                                <label>{{ \Auth::user()->role_id <= 2 ? 'Uang Fisik Dihitung *' : 'Uang Fisik Dihitung / Setor Kasir *' }}</label>
                                <input type="number" step="any" name="closing_cash_amount" required class="form-control">
                            </div>
                            @if(\Auth::user()->role_id > 2)
                                <input type="hidden" name="deposit_amount">
                            @else
                            <div class="form-group">
                                <label>Setor Kasir</label>
                                <input type="number" step="any" name="deposit_amount" class="form-control">
                            </div>
                            @endif
                            <div class="form-group">
                                <label>Catatan Tutup Kasir</label>
                                <textarea name="closing_note" class="form-control"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary" onclick="return confirmClose()">Tutup & Setor Kasir</button>
                        </form>
                    </div>
                </div>
            </div>
          </div>
        </div>
    </div>

    <div id="edit-deposit-modal" tabindex="-1" role="dialog" aria-hidden="true" class="modal fade text-left">
        <div role="document" class="modal-dialog">
            <div class="modal-content">
                {!! Form::open(['route' => 'cashRegister.updateDeposit', 'method' => 'post']) !!}
                <div class="modal-header">
                    <h5 class="modal-title">Edit Setor Kasir</h5>
                    <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="cash_register_id">
                    <div class="form-group">
                        <label>Uang Fisik Dihitung *</label>
                        <input type="number" step="any" name="closing_cash_amount" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Setor Kasir</label>
                        <input type="number" step="any" name="deposit_amount" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Catatan Tutup Kasir</label>
                        <textarea name="closing_note" class="form-control"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">{{trans('file.submit')}}</button>
                </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>

    <div id="approve-modal" tabindex="-1" role="dialog" aria-hidden="true" class="modal fade text-left">
        <div role="document" class="modal-dialog">
            <div class="modal-content">
                {!! Form::open(['route' => 'cashRegister.approve', 'method' => 'post']) !!}
                <div class="modal-header">
                    <h5 class="modal-title">Approve Setor Kasir</h5>
                    <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="cash_register_id">
                    <div class="form-group">
                        <label>Catatan Approval</label>
                        <textarea name="approval_note" class="form-control"></textarea>
                    </div>
                    <button type="submit" class="btn btn-success">Approve</button>
                </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>
</section>

@endsection

@push('scripts')
<script type="text/javascript">
    function confirmClose() {
      return confirm("Tutup kasir dan simpan setoran?");
    }

    function money(value) {
        value = parseFloat(value || 0);
        var parts = value.toFixed({{$general_setting->decimal}}).split(".");
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        return parts.length > 1 ? parts.join(",") : parts[0];
    }

    $(".register-details-btn").on("click", function () {
      var id = $(this).data('id');
      $.ajax({
          url: 'cash-register/getDetails/'+id,
          type: "GET",
          success:function(data) {
              if(data['status'])
                $("#register-details-modal #closing-section").removeClass('d-none');
              else
                $("#register-details-modal #closing-section").addClass('d-none');

              $.each(data, function(key, value) {
                  $('#register-details-modal #' + key).text(value === null ? 'N/A' : money(value));
              });
              $('#register-details-modal textarea[name=closing_note]').val(data['closing_note']);
              $('#register-details-modal input[name=cash_register_id]').val(id);
              // Jangan prefill dengan angka sistem: setoran harus murni hasil hitungan uang fisik.
              $('#register-details-modal input[name=closing_cash_amount]').val('');
              $('#register-details-modal input[name=deposit_amount]').val('');
              $('#register-details-modal input[name=closing_cash_amount]').focus();
          }
      });
    });

    $('#register-details-modal input[name=closing_cash_amount]').on('input', function() {
        @if(\Auth::user()->role_id > 2)
        $('#register-details-modal input[name=deposit_amount]').val($(this).val());
        @endif
    });

    $(".edit-deposit-btn").on("click", function () {
        $('#edit-deposit-modal input[name=cash_register_id]').val($(this).data('id'));
        $('#edit-deposit-modal input[name=closing_cash_amount]').val($(this).data('closing_cash_amount'));
        $('#edit-deposit-modal input[name=deposit_amount]').val($(this).data('deposit_amount'));
        $('#edit-deposit-modal textarea[name=closing_note]').val($(this).data('closing_note'));
    });

    $(".approve-btn").on("click", function () {
        $('#approve-modal input[name=cash_register_id]').val($(this).data('id'));
    });

    $('#cash-register-table').DataTable( {
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
        'columnDefs': [
            {"orderable": false, 'targets': [0, 10]},
            {
                'render': function(data, type) {
                    if(type === 'display')
                        data = '<div class="checkbox"><input type="checkbox" class="dt-checkboxes"><label></label></div>';
                    return data;
                },
                'checkboxes': {
                   'selectRow': true,
                   'selectAllRender': '<div class="checkbox"><input type="checkbox" class="dt-checkboxes"><label></label></div>'
                },
                'targets': [0]
            }
        ],
        'select': { style: 'multi',  selector: 'td:first-child'},
        'lengthMenu': [[10, 25, 50, -1], [10, 25, 50, "All"]],
        dom: '<"row"lfB>rtip',
        buttons: [
            {extend: 'pdf', text: '<i title="export to pdf" class="fa fa-file-pdf-o"></i>', exportOptions: {columns: ':visible:Not(.not-exported)', rows: ':visible'}},
            {extend: 'excel', text: '<i title="export to excel" class="dripicons-document-new"></i>', exportOptions: {columns: ':visible:Not(.not-exported)', rows: ':visible'}},
            {extend: 'csv', text: '<i title="export to csv" class="fa fa-file-text-o"></i>', exportOptions: {columns: ':visible:Not(.not-exported)', rows: ':visible'}},
            {extend: 'print', text: '<i title="print" class="fa fa-print"></i>', exportOptions: {columns: ':visible:Not(.not-exported)', rows: ':visible'}},
            {extend: 'colvis', text: '<i title="column visibility" class="fa fa-eye"></i>', columns: ':gt(0)'},
        ],
    } );
</script>
@endpush
