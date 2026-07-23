@extends('backend.layout.main')
@section('content')
<section class="forms">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h4>{{trans('file.Update Adjustment')}}</h4>
                    </div>
                    <div class="card-body">
                        <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                        @error('rack_id')
                        <div class="alert alert-danger">{{$message}}</div>
                        @enderror
                        {!! Form::open(['route' => ['qty_adjustment.update', $lims_adjustment_data->id], 'method' => 'put', 'files' => true, 'id' => 'adjustment-form']) !!}
                        <div class="row">
                            <div class="col-md-12">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{trans('file.reference')}}</label>
                                            <p><strong>{{$lims_adjustment_data->reference_no}}</strong></p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{trans('file.Warehouse')}} *</label>
                                            <select required id="warehouse_id" name="warehouse_id" class="selectpicker form-control" data-live-search="true" data-live-search-style="begins" title="Select warehouse...">
                                                @foreach($lims_warehouse_list as $warehouse)
                                                <option value="{{$warehouse->id}}">{{$warehouse->name}}</option>
                                                @endforeach
                                            </select>
                                            <input type="hidden" name="warehouse_id_hidden" value="{{$lims_adjustment_data->warehouse_id}}">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{trans('file.Attach Document')}}</label>
                                            <input type="file" name="document" class="form-control" >
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-12">
                                        <label>{{trans('file.Select Product')}}</label>
                                        <div class="search-box input-group">
                                            <button type="button" class="btn btn-secondary btn-lg"><i class="fa fa-barcode"></i></button>
                                            <input type="text" name="product_code_name" id="lims_productcodeSearch" placeholder="Please type product code and select..." class="form-control" />
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-5">
                                    <div class="col-md-12">
                                        <h5>{{trans('file.Order Table')}} *</h5>
                                        <div class="table-responsive mt-3">
                                            <table id="myTable" class="table table-hover order-list">
                                                <thead>
                                                    <tr>
                                                        <th>{{trans('file.name')}}</th>
                                                        <th>{{trans('file.Code')}}</th>
                                                        <th>Rak</th>
                                                        <th>Stok Sistem</th>
                                                        <th>Stok Fisik</th>
                                                        <th>Selisih</th>
                                                        <th><i class="dripicons-trash"></i></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                	@foreach($lims_product_adjustment_data as $product_adjustment_data)
                                                	<tr>
                                                	<?php
                                                	   $product = DB::table('products')->find($product_adjustment_data->product_id);
                                                       if($product_adjustment_data->variant_id) {
                                                            $product_variant = \App\Models\ProductVariant::select('id', 'item_code')->FindExactProduct($product_adjustment_data->product_id, $product_adjustment_data->variant_id)->first();
                                                            $product->code = $product_variant->item_code;
                                                            $product_variant_id = $product_variant->id;
                                                       }
                                                       else
                                                            $product_variant_id = null;
                                                       $physical_baseline = $product->qty;
                                                       $system_baseline = $product_adjustment_data->action == '-' ? ($product->qty + $product_adjustment_data->qty) : ($product->qty - $product_adjustment_data->qty);
                                                       $difference_baseline = $physical_baseline - $system_baseline;
                                                	?>
                                                	<td>{{$product->name}}</td>
                                                	<td>{{$product->code}}</td>
                                                	<td>{{ $product_adjustment_data->rack ? $product_adjustment_data->rack->name : '-' }}<input type="hidden" name="rack_id[]" value="{{$product_adjustment_data->rack_id}}"/></td>
                                                	<td class="stock-system-cell">{{$system_baseline}}</td>
                                                	<td><input type="number" class="form-control stock-fisik" value="{{$physical_baseline}}" required step="any" /></td>
                                                	<td class="difference-cell">{{$difference_baseline}}</td>
                                                	<td><button type="button" class="ibtnDel btn btn-md btn-danger">{{trans("file.delete")}}</button>
                                                	<input type="hidden" name="product_code[]" class="product-code" value="{{$product->code}}" />
                                                	<input type="hidden" class="product-id" name="product_id[]" value="{{$product->id}}" />
                                                    <input type="hidden" name="product_variant_id[]" value="{{$product_variant_id}}" />
                                                    <input type="hidden" class="stock-system-val" value="{{$system_baseline}}" />
                                                    <input type="hidden" class="qty-hidden" name="qty[]" value="{{$product_adjustment_data->qty}}" />
                                                    <input type="hidden" class="action-hidden" name="action[]" value="{{$product_adjustment_data->action}}" />
                                                    <input type="hidden" class="unit-cost-hidden" name="unit_cost[]" value="{{$product_adjustment_data->unit_cost}}" />
                                                	</td>
                                                	@endforeach
                                                	</tr>
                                                </tbody>
                                                <tfoot class="tfoot active">
                                                    <th colspan="5">{{trans('file.Total')}}</th>
                                                    <th id="total-qty">0</th>
                                                    <th><i class="dripicons-trash"></i></th>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <input type="hidden" name="total_qty" />
                                            <input type="hidden" name="item" />
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label>{{trans('file.Note')}}</label>
                                            <textarea rows="5" class="form-control" name="note">{{$lims_adjustment_data->note}}</textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <input type="submit" value="{{trans('file.submit')}}" class="btn btn-primary" id="submit-button">
                                </div>
                            </div>
                        </div>
                        {!! Form::close() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection

@push('scripts')
<script type="text/javascript">
    $("ul#product").siblings('a').attr('aria-expanded','true');
    $("ul#product").addClass("show");
// array data depend on warehouse
var lims_product_array = [];
var product_code = [];
var product_name = [];
var product_qty = [];
var unit_cost = [];
var product_rack_id = [];
var product_rack_name = [];
var lims_rack_array = [];
var rackBaseUrl = "{{url('racks/warehouse')}}";

var exist_code = [];

var rownumber = $('table.order-list tbody tr:last').index();

for(rowindex  =0; rowindex <= rownumber; rowindex++){
    exist_code.push($('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('td:nth-child(2)').text());
}

	$('.selectpicker').selectpicker({
	    style: 'btn-link',
	});
	//assigning value
	$('select[name="warehouse_id"]').val($('input[name="warehouse_id_hidden"]').val());
	$('.selectpicker').selectpicker('refresh');
	calculateTotal();

	// lims_rack_array dipakai hanya sebagai penanda "gudang/toko ini pakai
	// rak atau tidak". Baris yang sudah ada (dari server) selalu menampilkan
	// rak yang sudah tersimpan (read-only) - lihat blade loop di atas.
	if($('#warehouse_id').val()) {
	    $('#lims_productcodeSearch').prop('disabled', true).attr('placeholder', 'Memuat daftar rak...');
	    $.get(rackBaseUrl + '/' + $('#warehouse_id').val(), function(data) {
	        lims_rack_array = data;
	    }).always(function() {
	        $('#lims_productcodeSearch').prop('disabled', false).attr('placeholder', 'Please type product code and select...');
	    });
	}

	$('#lims_productcodeSearch').on('input', function(){
	    var warehouse_id = $('#warehouse_id').val();
	    temp_data = $('#lims_productcodeSearch').val();

	    if(!warehouse_id){
	        $('#lims_productcodeSearch').val(temp_data.substring(0, temp_data.length - 1));
	        alert('Please select Warehouse!');
	    }
	});

	var id = $('#warehouse_id').val();
    $.get('../getproduct/' + id, function(data) {
        lims_product_array = [];
        product_code = data[0];
        product_name = data[1];
        product_qty = data[2];
        unit_cost = data[3];
        product_rack_id = data[4] || [];
        product_rack_name = data[5] || [];
        $.each(product_code, function(index) {
            lims_product_array.push(product_code[index] + ' (' + product_name[index] + ')'+ '|' + unit_cost[index]);
        });
    });

	var lims_productcodeSearch = $('#lims_productcodeSearch');

	lims_productcodeSearch.autocomplete({
	    source: function(request, response) {
	        var matcher = new RegExp(".?" + $.ui.autocomplete.escapeRegex(request.term), "i");
	        response($.grep(lims_product_array, function(item) {
	            return matcher.test(item);
	        }));
	    },
        response: function(event, ui) {
            if (ui.content.length == 1) {
                var data = ui.content[0].value;
                $(this).autocomplete( "close" );
                productSearch(data);
            };
        },
	    select: function(event, ui) {
	        var data = ui.item.value;
            productSearch(data);
	    }
	});

	$('select[name="warehouse_id"]').on('change', function() {
	    var id = $('#warehouse_id').val();
	    $.get('../getproduct/' + id, function(data) {
	        lims_product_array = [];
	        product_code = data[0];
	        product_name = data[1];
	        product_qty = data[2];
	        unit_cost = data[3];
	        product_rack_id = data[4] || [];
	        product_rack_name = data[5] || [];
            $.each(product_code, function(index) {
                lims_product_array.push(product_code[index] + ' (' + product_name[index] + ')' + '|' + unit_cost[index]);
            });
	    });
	    if(id) {
	        lims_rack_array = [];
	        $('#lims_productcodeSearch').prop('disabled', true).attr('placeholder', 'Memuat daftar rak...');
	        $.get(rackBaseUrl + '/' + id, function(data) {
	            lims_rack_array = data;
	        }).always(function() {
	            $('#lims_productcodeSearch').prop('disabled', false).attr('placeholder', 'Please type product code and select...');
	        });
	    }
	    else {
	        lims_rack_array = [];
	    }
	});

	$("#myTable").on('input', '.stock-fisik', function() {
	    updateRowDifference($(this).closest('tr'));
	    calculateTotal();
	});

	$("table.order-list tbody").on("click", ".ibtnDel", function(event) {
	    rowindex = $(this).closest('tr').index();
	    $(this).closest("tr").remove();
	    calculateTotal();
	});

    $(window).keydown(function(e){
        if (e.which == 13) {
            var $targ = $(e.target);
            if (!$targ.is("textarea") && !$targ.is(":button,:submit")) {
                var focusNext = false;
                $(this).find(":input:visible:not([disabled],[readonly]), a").each(function(){
                    if (this === e.target) {
                        focusNext = true;
                    }
                    else if (focusNext){
                        $(this).focus();
                        return false;
                    }
                });
                return false;
            }
        }
    });

    $('#adjustment-form').on('submit',function(e){
        var rownumber = $('table.order-list tbody tr:last').index();
        if (rownumber < 0) {
            alert("Please insert product to order table!")
            e.preventDefault();
            return;
        }
        // Rak setiap baris sudah otomatis terisi dari data plan saat barang
        // ditambahkan (lihat productSearch()) - barang tanpa rak sudah
        // ditolak sebelum sempat masuk ke tabel.
    });

    function productSearch(data){
        $.ajax({
            type: 'GET',
            url: '../lims_product_search',
            data: {
                data: data
            },
            success: function(data) {
                var flag = 1;
                $(".product-code").each(function(i) {
                    if ($(this).val() == data[1]) {
                        var $row = $(this).closest('tr');
                        var current = parseFloat($row.find('.stock-fisik').val()) || 0;
                        $row.find('.stock-fisik').val(current + 1);
                        updateRowDifference($row);
                        flag = 0;
                    }
                });
                $("input[name='product_code_name']").val('');
                if(flag){
                    var pos = product_code.indexOf(data[1]);
                    var system_qty = (pos > -1) ? product_qty[pos] : 0;
                    // Rak ikut otomatis dari plan yang sudah ada (Plan Toko/Plan
                    // Gudang) - sama seperti gudang, bukan dipilih bebas di sini.
                    if (lims_rack_array.length && (pos === -1 || !product_rack_id[pos])) {
                        alert('Barang ini belum di-plan ke rak manapun. Plan dulu lewat menu Plan Toko/Plan Gudang sebelum bisa disesuaikan.');
                        return;
                    }
                    var rackId = (pos > -1 && product_rack_id[pos]) ? product_rack_id[pos] : '';
                    var rackName = (pos > -1 && product_rack_name[pos]) ? product_rack_name[pos] : '-';

                    var newRow = $("<tr>");
                    var cols = '';
                    cols += '<td>' + data[0] + '</td>';
                    cols += '<td>' + data[1] + '</td>';
                    cols += '<td>' + rackName + '<input type="hidden" name="rack_id[]" value="' + rackId + '"/></td>';
                    cols += '<td class="stock-system-cell">' + system_qty + '</td>';
                    cols += '<td><input type="number" class="form-control stock-fisik" value="' + system_qty + '" required step="any" /></td>';
                    cols += '<td class="difference-cell">0</td>';
                    cols += '<td><button type="button" class="ibtnDel btn btn-md btn-danger">{{trans("file.delete")}}</button></td>';
                    cols += '<input type="hidden" class="product-code" name="product_code[]" value="' + data[1] + '"/>';
                    cols += '<input type="hidden" class="product-id" name="product_id[]" value="' + data[2] + '"/>';
                    cols += '<input type="hidden" name="product_variant_id[]" value="' + data[3] + '"/>';
                    cols += '<input type="hidden" class="stock-system-val" value="' + system_qty + '"/>';
                    cols += '<input type="hidden" class="qty-hidden" name="qty[]" value="0"/>';
                    cols += '<input type="hidden" class="action-hidden" name="action[]" value="+"/>';
                    cols += '<input type="hidden" class="unit-cost-hidden" name="unit_cost[]" value="' + data[4] + '"/>';

                    newRow.append(cols);
                    $("table.order-list tbody").append(newRow);
                    $('.selectpicker').selectpicker('refresh');
                    calculateTotal();
                }
            }
        });
    }

	function updateRowDifference($row) {
	    var system_qty = parseFloat($row.find('.stock-system-val').val()) || 0;
	    var physical_qty = parseFloat($row.find('.stock-fisik').val());
	    if (isNaN(physical_qty)) physical_qty = 0;
	    var diff = physical_qty - system_qty;
	    $row.find('.difference-cell').text(diff);
	    $row.find('.qty-hidden').val(Math.abs(diff));
	    $row.find('.action-hidden').val(diff < 0 ? '-' : '+');
	}

	function calculateTotal() {
	    var total_qty = 0;
	    $(".difference-cell").each(function() {
	        var val = parseFloat($(this).text());
	        if (!isNaN(val)) total_qty += val;
	    });
	    $("#total-qty").text(total_qty);
	    $('input[name="total_qty"]').val(total_qty);
	    $('input[name="item"]').val($('table.order-list tbody tr').length);
	}
</script>
@endpush
