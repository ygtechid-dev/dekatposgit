@extends('backend.layout.main')
@section('content')
@if($errors->has('name'))
<div class="alert alert-danger alert-dismissible text-center">
    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ $errors->first('name') }}</div>
@endif
@if(session()->has('message'))
  <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('message') }}</div>
@endif
@if(session()->has('not_permitted'))
  <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
@endif

<section>
    <div class="container-fluid">
        <a href="#" data-toggle="modal" data-target="#createModal" class="btn btn-info add-warehouse-btn"><i class="dripicons-plus"></i> {{trans('file.Add Warehouse')}}</a>
        <a href="#" data-toggle="modal" data-target="#importWarehouse" class="btn btn-primary add-warehouse-btn"><i class="dripicons-copy"></i> {{trans('file.Import Warehouse')}}</a>
    </div>
    <div class="table-responsive">
        <table id="warehouse-table" class="table">
            <thead>
                <tr>
                    <th class="not-exported"></th>
                    <th>{{trans('file.Warehouse')}}</th>
                    <th>Tipe</th>
                    <th>{{trans('file.Phone Number')}}</th>
                    <th>{{trans('file.Email')}}</th>
                    <th>{{trans('file.Address')}}</th>
                    <th>{{trans('file.Number of Product')}}</th>
                    <th>{{trans('file.Stock Quantity')}}</th>
                    <th class="not-exported">{{trans('file.action')}}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lims_warehouse_all as $key=>$warehouse)
                <?php
                    $number_of_product = App\Models\Product_Warehouse::
                    join('products', 'product_warehouse.product_id', '=', 'products.id')
                    ->where([ ['product_warehouse.warehouse_id', $warehouse->id],
                              ['products.is_active', true]
                    ])->count();

                    $stock_qty = App\Models\Product_Warehouse::
                    join('products', 'product_warehouse.product_id', '=', 'products.id')
                    ->where([ ['product_warehouse.warehouse_id', $warehouse->id],
                              ['products.is_active', true]
                    ])->sum('product_warehouse.qty');
                ?>
                <tr data-id="{{$warehouse->id}}">
                    <td>{{$key}}</td>
                    <td>{{ $warehouse->name }}</td>
                    <td>{{ $warehouse->type == 'toko' ? 'Toko' : 'Gudang' }}</td>
                    <td>{{ $warehouse->phone}}</td>
                    <td>{{ $warehouse->email}}</td>
                    <td>{{ $warehouse->address}}</td>
                    <td>{{$number_of_product}}</td>
                    <td>{{$stock_qty}}</td>
                    <td>
                        <div class="btn-group">
                            <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">{{trans('file.action')}}
                                <span class="caret"></span>
                                <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default" user="menu">
                                <li>
                                    <button type="button" data-id="{{$warehouse->id}}" class="open-EditWarehouseDialog btn btn-link" data-toggle="modal" data-target="#editModal"><i class="dripicons-document-edit"></i> {{trans('file.edit')}}
                                </button>
                                </li>
                                <li>
                                    <button type="button" data-id="{{$warehouse->id}}" data-name="{{$warehouse->name}}" class="open-RackDialog btn btn-link" data-toggle="modal" data-target="#rackModal"><i class="dripicons-view-apps"></i> Kelola Rak
                                </button>
                                </li>
                                <li class="divider"></li>
                                {{ Form::open(['route' => ['warehouse.destroy', $warehouse->id], 'method' => 'DELETE'] ) }}
                                <li>
                                    <button type="submit" class="btn btn-link" onclick="return confirmDelete()"><i class="dripicons-trash"></i> {{trans('file.delete')}}</button>
                                </li>
                                {{ Form::close() }}
                            </ul>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

<div id="createModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
  <div role="document" class="modal-dialog">
    <div class="modal-content">
        {!! Form::open(['route' => 'warehouse.store', 'method' => 'post']) !!}
      <div class="modal-header">
        <h5 id="exampleModalLabel" class="modal-title">{{trans('file.Add Warehouse')}}</h5>
        <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
      </div>
      <div class="modal-body">
        <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
          <div class="form-group">
            <label>{{trans('file.name')}} *</label>
            <input type="text" placeholder="Type WareHouse Name..." name="name" required="required" class="form-control">
          </div>
          <div class="form-group">
            <label>Tipe *</label>
            <select name="type" class="form-control" required>
                <option value="gudang">Gudang</option>
                <option value="toko">Toko</option>
            </select>
          </div>
          <div class="form-group">
            <label>{{trans('file.Phone Number')}} *</label>
            <input type="text" name="phone" class="form-control" required>
          </div>
          <div class="form-group">
            <label>{{trans('file.Email')}}</label>
            <input type="email" name="email" placeholder="example@example.com" class="form-control">
          </div>
          <div class="form-group">
            <label>{{trans('file.Address')}} *</label>
            <textarea required class="form-control" rows="3" name="address"></textarea>
          </div>
          <div class="form-group">
            <input type="submit" value="{{trans('file.submit')}}" class="btn btn-primary">
          </div>
      </div>
      {{ Form::close() }}
    </div>
  </div>
</div>

<div id="editModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
  <div role="document" class="modal-dialog">
    <div class="modal-content">
        {!! Form::open(['route' => ['warehouse.update',1], 'method' => 'put']) !!}
      <div class="modal-header">
        <h5 id="exampleModalLabel" class="modal-title"> {{trans('file.Update Warehouse')}}</h5>
        <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
      </div>
      <div class="modal-body">
        <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
          <div class="form-group">
            <input type="hidden" name="warehouse_id">
            <label>{{trans('file.name')}} *</label>
            <input type="text" placeholder="Type WareHouse Name..." name="name" required="required" class="form-control">
          </div>
          <div class="form-group">
            <label>Tipe *</label>
            <select name="type" class="form-control" required>
                <option value="gudang">Gudang</option>
                <option value="toko">Toko</option>
            </select>
          </div>
          <div class="form-group">
            <label>{{trans('file.Phone Number')}} *</label>
            <input type="text" name="phone" class="form-control" required>
          </div>
          <div class="form-group">
            <label>{{trans('file.Email')}}</label>
            <input type="email" name="email" placeholder="example@example.com" class="form-control">
          </div>
          <div class="form-group">
            <label>{{trans('file.Address')}} *</label>
            <textarea class="form-control" rows="3" name="address" required></textarea>
          </div>
          <div class="form-group">
            <input type="submit" value="{{trans('file.submit')}}" class="btn btn-primary">
          </div>
      </div>
      {{ Form::close() }}
    </div>
  </div>
</div>

<div id="importWarehouse" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
  <div role="document" class="modal-dialog">
    <div class="modal-content">
        {!! Form::open(['route' => 'warehouse.import', 'method' => 'post', 'files' => true]) !!}
      <div class="modal-header">
        <h5 id="exampleModalLabel" class="modal-title">{{trans('file.Import Warehouse')}}</h5>
        <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
      </div>
      <div class="modal-body">
        <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
         <p>{{trans('file.The correct column order is')}} (name*, phone, email, address*) {{trans('file.and you must follow this')}}.</p>
        <div class="row">
              <div class="col-md-6">
                  <div class="form-group">
                      <label>{{trans('file.Upload CSV File')}} *</label>
                      {{Form::file('file', array('class' => 'form-control','required'))}}
                  </div>
              </div>
              <div class="col-md-6">
                  <div class="form-group">
                      <label> {{trans('file.Sample File')}}</label>
                      <a href="sample_file/sample_warehouse.csv" class="btn btn-info btn-block btn-md"><i class="dripicons-download"></i>  {{trans('file.Download')}}</a>
                  </div>
              </div>
        </div>
        <input type="submit" value="{{trans('file.submit')}}" class="btn btn-primary">
      </div>
      {{ Form::close() }}
    </div>
  </div>
</div>

<div id="rackModal" tabindex="-1" role="dialog" aria-labelledby="rackModalLabel" aria-hidden="true" class="modal fade text-left">
  <div role="document" class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 id="rackModalLabel" class="modal-title">Kelola Rak - <span id="rack-warehouse-name"></span></h5>
        <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="rack-warehouse-id">
        <div class="row">
            <div class="col-md-8">
                <input type="text" id="rack-name-input" class="form-control" placeholder="Nama rak...">
            </div>
            <div class="col-md-4">
                <button type="button" id="rack-add-btn" class="btn btn-primary btn-block">Tambah Rak</button>
            </div>
        </div>
        <table class="table table-hover mt-3">
            <thead>
                <tr>
                    <th>Nama Rak</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody id="rack-list-body">
            </tbody>
        </table>
      </div>
    </div>
  </div>
</div>


@endsection

@push('scripts')
<script type="text/javascript">

    $("ul#setting").siblings('a').attr('aria-expanded','true');
    $("ul#setting").addClass("show");
    $("ul#setting #warehouse-menu").addClass("active");

    @if(config('database.connections.saleprosaas_landlord'))
        numberOfWarehouse = <?php echo json_encode($numberOfWarehouse)?>;
        $.ajax({
            type: 'GET',
            async: false,
            url: '{{route("package.fetchData", $general_setting->package_id)}}',
            success: function(data) {
                if(data['number_of_warehouse'] > 0 && data['number_of_warehouse'] <= numberOfWarehouse) {
                    $("a.add-warehouse-btn").addClass('d-none');
                }
            }
        });
    @endif

    var warehouse_id = [];
    var user_verified = <?php echo json_encode(env('USER_VERIFIED')) ?>;

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

  function confirmDelete() {
      if (confirm("Are you sure want to delete?")) {
          return true;
      }
      return false;
  }

    $(document).ready(function() {

        $(document).on('click', '.open-EditWarehouseDialog', function() {
            var url = "warehouse/"
            var id = $(this).data('id').toString();
            url = url.concat(id).concat("/edit");

            $.get(url, function(data) {
                $("#editModal input[name='name']").val(data['name']);
                $("#editModal select[name='type']").val(data['type'] ? data['type'] : 'gudang');
                $("#editModal input[name='phone']").val(data['phone']);
                $("#editModal input[name='email']").val(data['email']);
                $("#editModal textarea[name='address']").val(data['address']);
                $("#editModal input[name='warehouse_id']").val(data['id']);

            });
        });

        $(document).on('click', '.open-RackDialog', function() {
            var warehouse_id = $(this).data('id').toString();
            var warehouse_name = $(this).data('name');
            $("#rack-warehouse-id").val(warehouse_id);
            $("#rack-warehouse-name").text(warehouse_name);
            $("#rack-name-input").val('');
            loadRackList(warehouse_id);
        });

        // URL absolut lewat url()/route(). Sebelumnya dipakai URL relatif
        // ('racks/' + id) yang salah arah kalau halaman gudang dibuka dari
        // path bertingkat, dan tidak ada satu pun handler error - jadi kalau
        // request-nya gagal (izin kurang, sesi habis, tabel racks belum ada)
        // modal ini diam saja dan terlihat seperti tambah rak tidak berfungsi.
        var RACK_LIST_URL  = "{{ url('racks/warehouse') }}";
        var RACK_STORE_URL = "{{ route('rack.store') }}";
        var RACK_BASE_URL  = "{{ url('racks') }}";

        function rackAjaxError(xhr) {
            if (xhr.responseJSON && xhr.responseJSON.message) {
                alert(xhr.responseJSON.message);
            }
            else if (xhr.status === 419) {
                alert('Sesi sudah habis. Muat ulang halaman lalu coba lagi.');
            }
            else if (xhr.status === 403) {
                alert('Anda tidak punya izin untuk mengelola rak.');
            }
            else {
                alert('Gagal menyimpan rak. Pastikan tabel "racks" sudah dibuat di database.');
            }
        }

        $(document).on('click', '#rack-add-btn', function() {
            var warehouse_id = $("#rack-warehouse-id").val();
            var name = $.trim($("#rack-name-input").val());
            if(!name) {
                alert('Nama rak harus diisi');
                return;
            }
            $.ajax({
                type: 'POST',
                url: RACK_STORE_URL,
                data: {
                    warehouse_id: warehouse_id,
                    name: name
                },
                success: function(data) {
                    $("#rack-name-input").val('');
                    loadRackList(warehouse_id);
                },
                error: rackAjaxError
            });
        });

        $(document).on('click', '.rack-delete-btn', function() {
            if(!confirm('Hapus rak ini?')) return;
            var rack_id = $(this).data('id');
            var warehouse_id = $("#rack-warehouse-id").val();
            $.ajax({
                type: 'POST',
                url: RACK_BASE_URL + '/' + rack_id,
                data: { _method: 'DELETE' },
                success: function(data) {
                    loadRackList(warehouse_id);
                },
                error: rackAjaxError
            });
        });

        function loadRackList(warehouse_id) {
            $.ajax({
                type: 'GET',
                url: RACK_LIST_URL + '/' + warehouse_id,
                success: function(data) {
                    var html = '';
                    $.each(data, function(i, rack) {
                        html += '<tr><td>' + $('<div>').text(rack.name).html() + '</td><td><button type="button" class="btn btn-link rack-delete-btn" data-id="' + rack.id + '"><i class="dripicons-trash"></i></button></td></tr>';
                    });
                    if(data.length == 0) {
                        html = '<tr><td colspan="2">Belum ada rak</td></tr>';
                    }
                    $("#rack-list-body").html(html);
                },
                error: rackAjaxError
            });
        }
  });

  $('#warehouse-table').DataTable( {
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
            {
                "orderable": false,
                'targets': [0, 6, 7, 8]
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
        'select': { style: 'multi',  selector: 'td:first-child'},
        'lengthMenu': [[10, 25, 50, -1], [10, 25, 50, "All"]],
        dom: '<"row"lfB>rtip',
        buttons: [
            {
                extend: 'pdf',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
                    rows: ':visible'
                },
            },
            {
                extend: 'excel',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
                    rows: ':visible'
                },
            },
            {
                extend: 'csv',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
                    rows: ':visible'
                },
            },
            {
                extend: 'print',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
                    rows: ':visible'
                },
            },
            {
                text: '<i title="delete" class="dripicons-cross"></i>',
                className: 'buttons-delete',
                action: function ( e, dt, node, config ) {
                    if(user_verified == '1') {
                        warehouse_id.length = 0;
                        $(':checkbox:checked').each(function(i){
                            if(i){
                                warehouse_id[i-1] = $(this).closest('tr').data('id');
                            }
                        });
                        if(warehouse_id.length && confirm("Are you sure want to delete?")) {
                            $.ajax({
                                type:'POST',
                                url:'warehouse/deletebyselection',
                                data:{
                                    warehouseIdArray: warehouse_id
                                },
                                success:function(data){
                                    alert(data);
                                }
                            });
                            dt.rows({ page: 'current', selected: true }).remove().draw(false);
                        }
                        else if(!warehouse_id.length)
                            alert('No warehouse is selected!');
                    }
                    else
                        alert('This feature is disable for demo!');
                }
            },
            {
                extend: 'colvis',
                columns: ':gt(0)'
            },
        ],
    } );

$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
});

$( "#select_all" ).on( "change", function() {
    if ($(this).is(':checked')) {
        $("tbody input[type='checkbox']").prop('checked', true);
    }
    else {
        $("tbody input[type='checkbox']").prop('checked', false);
    }
});

$("#export").on("click", function(e){
    e.preventDefault();
    var warehouse = [];
    $(':checkbox:checked').each(function(i){
      warehouse[i] = $(this).val();
    });
    $.ajax({
       type:'POST',
       url:'/exportwarehouse',
       data:{

            warehouseArray: warehouse
        },
       success:function(data){
        alert('Exported to CSV file successfully! Click Ok to download file');
        window.location.href = data;
       }
    });
});
</script>
@endpush
