@extends('backend.layout.main')
@section('content')
<section class="forms">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h4>{{trans('file.Add Adjustment')}}</h4>
                    </div>
                    <div class="card-body">
                        <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                        @error('rack_id')
                        <div class="alert alert-danger">{{$message}}</div>
                        @enderror
                        {!! Form::open(['route' => 'qty_adjustment.store', 'method' => 'post', 'files' => true, 'id' => 'adjustment-form']) !!}
                        <div class="row">
                            <div class="col-md-12">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{trans('file.Warehouse')}} *</label>
                                            <select required id="warehouse_id" name="warehouse_id" class="selectpicker form-control" data-live-search="true" data-live-search-style="begins" title="Select warehouse...">
                                                @foreach($lims_warehouse_list as $warehouse)
                                                <option value="{{$warehouse->id}}">{{$warehouse->name}}</option>
                                                @endforeach
                                            </select>
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
                                        <div class="search-box input-group position-relative">
                                            <button type="button" class="btn btn-secondary btn-lg"><i class="fa fa-barcode"></i></button>
                                            <input type="text" name="product_code_name" id="lims_productcodeSearch" list="adjustment-product-datalist" placeholder="Please type product code and select..." class="form-control" />
                                            <datalist id="adjustment-product-datalist"></datalist>
                                            <div id="adjustment-product-suggestions" class="list-group position-absolute w-100" style="top:100%; left:0; z-index:99999; display:none;"></div>
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
                                            <textarea rows="5" class="form-control" name="note"></textarea>
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
    $("ul#product #adjustment-create-menu").addClass("active");
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
    var adjustmentDraftKey = 'dekatpos:draft:' + location.pathname;

	$('.selectpicker').selectpicker({
	    style: 'btn-link',
	});

	var lims_productcodeSearch = $('#lims_productcodeSearch');
    var $adjustmentSuggestions = $('#adjustment-product-suggestions');
    var $adjustmentDataList = $('#adjustment-product-datalist');
    var currentAdjustmentSuggestions = [];
    var adjustmentSuggestionUrl = "{{url('qty_adjustment/product_suggestions')}}";


    var activeWarehouseRequest = 0;

	$('select[name="warehouse_id"]').on('change', function() {
	    var id = $(this).val();
        lims_product_array = [];
        product_code = [];
        product_name = [];
        product_qty = [];
        unit_cost = [];
        product_rack_id = [];
        product_rack_name = [];
        currentAdjustmentSuggestions = [];
        $('#adjustment-product-datalist').empty();
        $('#adjustment-product-suggestions').hide().empty();
        lims_productcodeSearch.val('');

        // Baris yang sudah terlanjur ditambahkan memakai stok sistem gudang/toko
        // sebelumnya - kalau gudang diganti, baris itu harus dibuang supaya
        // stok awal toko lain tidak ikut terbawa.
        if ($("table.order-list tbody tr").length) {
            $("table.order-list tbody").empty();
            calculateTotal();
        }

        var requestToken = ++activeWarehouseRequest;
	    $.get("{{url('qty_adjustment/getproduct')}}/" + id, function(data) {
	        if (requestToken !== activeWarehouseRequest) return; // respon basi
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
	        // lims_rack_array dipakai hanya sebagai penanda "gudang/toko ini
	        // pakai rak atau tidak" - daftar barang yang dipakai user (pool
	        // pencarian) sudah difilter di server (getProduct) supaya hanya
	        // barang yang sudah di-plan ke rak yang muncul. Tunggu dulu supaya
	        // tidak ada race condition dengan pool produk yang juga sedang dimuat.
	        lims_rack_array = [];
	        lims_productcodeSearch.prop('disabled', true).attr('placeholder', 'Memuat daftar rak...');
	        $.get(rackBaseUrl + '/' + id, function(data) {
	            if (requestToken !== activeWarehouseRequest) return; // respon basi
	            lims_rack_array = data;
	        }).always(function() {
	            lims_productcodeSearch.prop('disabled', false).attr('placeholder', 'Please type product code and select...');
	        });
	    }
	    else {
	        lims_rack_array = [];
	    }
	});

    function localAdjustmentSuggestions(term) {
        var matcher = new RegExp($.ui.autocomplete.escapeRegex(term), "i");
        return $.grep(lims_product_array, function(item) {
            return matcher.test(item);
        }).slice(0, 20);
    }

    function renderAdjustmentSuggestions(items) {
        currentAdjustmentSuggestions = items || [];
        $adjustmentDataList.empty();
        $.each(currentAdjustmentSuggestions, function(i, item) {
            $adjustmentDataList.append('<option value="' + $('<div/>').text(item).html() + '">' + item.replace('|', ' - ') + '</option>');
        });
        if (!items || !items.length) {
            $adjustmentSuggestions.hide().empty();
            return;
        }

        var html = '';
        $.each(items, function(i, item) {
            html += '<button type="button" class="list-group-item list-group-item-action adjustment-suggestion-item" data-value="' + $('<div/>').text(item).html() + '">' + item.replace('|', ' - ') + '</button>';
        });
        $adjustmentSuggestions.html(html).show();
    }

    lims_productcodeSearch.on('input', function() {
        var term = $(this).val();
        if (term.length < 1) {
            $adjustmentSuggestions.hide().empty();
            return;
        }
        $.get(adjustmentSuggestionUrl, {
            term: term,
            warehouse_id: $('select[name="warehouse_id"]').val()
        }, function(items) {
            renderAdjustmentSuggestions(items && items.length ? items : localAdjustmentSuggestions(term));
        }).fail(function() {
            renderAdjustmentSuggestions(localAdjustmentSuggestions(term));
        });
    });

    lims_productcodeSearch.on('change', function() {
        var selected = $(this).val();
        if (currentAdjustmentSuggestions.indexOf(selected) !== -1) {
            $(this).val('');
            $adjustmentSuggestions.hide().empty();
            productSearch(selected);
        }
    });

    lims_productcodeSearch.on('keydown', function(e) {
        if (e.key === 'Enter' && currentAdjustmentSuggestions.length) {
            e.preventDefault();
            var selected = currentAdjustmentSuggestions[0];
            $(this).val('');
            $adjustmentSuggestions.hide().empty();
            productSearch(selected);
        }
    });

    $adjustmentSuggestions.on('mousedown', '.adjustment-suggestion-item', function(e) {
        e.preventDefault();
        var data = $(this).data('value');
        lims_productcodeSearch.val('');
        $adjustmentSuggestions.hide().empty();
        productSearch(data);
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('.search-box').length) {
            $adjustmentSuggestions.hide();
        }
    });

	lims_productcodeSearch.autocomplete({
	    source: function(request, response) {
            $.get(adjustmentSuggestionUrl, {
                term: request.term,
                warehouse_id: $('select[name="warehouse_id"]').val()
            }, function(items) {
                response(items && items.length ? items : localAdjustmentSuggestions(request.term));
            }).fail(function() {
                response(localAdjustmentSuggestions(request.term));
            });
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

    if ($('select[name="warehouse_id"]').val()) {
        $('select[name="warehouse_id"]').trigger('change');
    }

    function saveAdjustmentDraft() {
        try {
            localStorage.setItem(adjustmentDraftKey, JSON.stringify({
                warehouse_id: $('select[name="warehouse_id"]').val(),
                note: $('textarea[name="note"]').val(),
                tbody: $('table.order-list tbody').html(),
                total_qty: $('input[name="total_qty"]').val(),
                item: $('input[name="item"]').val()
            }));
        } catch (e) {}
    }

    function restoreAdjustmentDraft() {
        try {
            var raw = localStorage.getItem(adjustmentDraftKey);
            if (!raw) return;
            var draft = JSON.parse(raw);
            if (draft.warehouse_id) {
                $('select[name="warehouse_id"]').val(draft.warehouse_id).selectpicker('refresh');
            }
            if (draft.note) $('textarea[name="note"]').val(draft.note);
            if (draft.tbody) $('table.order-list tbody').html(draft.tbody);
            calculateTotal();
        } catch (e) {}
    }

    setTimeout(restoreAdjustmentDraft, 350);
    $('#adjustment-form').on('input change', 'input, select, textarea', saveAdjustmentDraft);
    $('table.order-list tbody').on('input change', 'input, select', saveAdjustmentDraft);

	$("#myTable").on('input', '.stock-fisik', function() {
	    updateRowDifference($(this).closest('tr'));
	    calculateTotal();
        saveAdjustmentDraft();
	});

    $("#myTable").on('keydown', '.stock-fisik', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            updateRowDifference($(this).closest('tr'));
            calculateTotal();
            saveAdjustmentDraft();
            lims_productcodeSearch.focus().select();
        }
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
        localStorage.removeItem(adjustmentDraftKey);
	});

    function rackSelectHtml(selectedRackId) {
        var html = '<select class="form-control rack-select" name="rack_id[]">';
        html += '<option value="">- Pilih Rak -</option>';
        $.each(lims_rack_array, function(i, rack) {
            var selected = selectedRackId && selectedRackId == rack.id ? ' selected' : '';
            html += '<option value="' + rack.id + '"' + selected + '>' + rack.name + '</option>';
        });
        html += '</select>';
        return html;
    }

	function productSearch(data){
	    var requestWarehouseId = $('select[name="warehouse_id"]').val();
	    if (!requestWarehouseId) {
	        alert('Pilih gudang/toko dulu.');
	        return;
	    }
		$.ajax({
            type: 'GET',
            url: "{{route('product_adjustment.search')}}",
            data: {
                data: data,
                warehouse_id: requestWarehouseId
            },
            success: function(data) {
                // Abaikan respon yang datang setelah gudang/toko diganti,
                // supaya stok toko lama tidak ikut terbawa.
                if (String($('select[name="warehouse_id"]').val()) !== String(requestWarehouseId)) {
                    return;
                }
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
                    // Stok sistem SELALU dari server untuk gudang/toko yang
                    // dipilih (data[5]); array product_qty hanya cadangan.
                    var system_qty = (typeof data[5] !== 'undefined' && data[5] !== null)
                        ? parseFloat(data[5])
                        : ((pos > -1) ? parseFloat(product_qty[pos]) : 0);
                    if (isNaN(system_qty)) system_qty = 0;

                    // Rak ikut otomatis dari plan yang sudah ada (Plan Toko/Plan
                    // Gudang) - sama seperti gudang, bukan dipilih bebas di sini.
                    var rackId = data[6] ? data[6] : ((pos > -1 && product_rack_id[pos]) ? product_rack_id[pos] : '');
                    var rackName = data[7] ? data[7] : ((pos > -1 && product_rack_name[pos]) ? product_rack_name[pos] : '-');

                    // Barang yang belum di-plan ke rak sudah difilter di server
                    // (lihat AdjustmentController::getProduct), jadi kalau gudang
                    // ini pakai rak tapi barangnya belum punya rak di gudang ini, tolak.
                    if (lims_rack_array.length && !rackId) {
                        alert('Barang ini belum di-plan ke rak manapun di gudang/toko ini. Plan dulu lewat menu Plan Toko/Plan Gudang sebelum bisa disesuaikan.');
                        return;
                    }

                    var newRow = $("<tr>");
                    var cols = '';
                    cols += '<td>' + data[0] + '</td>';
                    cols += '<td>' + data[1] + '</td>';
                    cols += '<td>' + (lims_rack_array.length ? rackSelectHtml(rackId) : rackName + '<input type="hidden" name="rack_id[]" value="' + rackId + '"/>') + '</td>';
                    cols += '<td class="stock-system-cell">' + system_qty + '</td>';
                    cols += '<td><input type="number" class="form-control stock-fisik" value="' + system_qty + '" required step="any" /></td>';
                    cols += '<td class="difference-cell">0</td>';
                    cols += '<td><button type="button" class="ibtnDel btn btn-md btn-danger">{{trans("file.delete")}}</button></td>';
                    cols += '<input type="hidden" class="product-code" name="product_code[]" value="' + data[1] + '"/>';
                    cols += '<input type="hidden" class="product-id" name="product_id[]" value="' + data[2] + '"/>';
                    cols += '<input type="hidden" class="stock-system-val" value="' + system_qty + '"/>';
                    cols += '<input type="hidden" class="qty-hidden" name="qty[]" value="0"/>';
                    cols += '<input type="hidden" class="action-hidden" name="action[]" value="+"/>';
                    cols += '<input type="hidden" class="unit-cost-hidden" name="unit_cost[]" value="' + data[4] + '"/>';

                    newRow.append(cols);
                    $("table.order-list tbody").append(newRow);
                    calculateTotal();
                    saveAdjustmentDraft();
                    newRow.find('.stock-fisik').focus().select();
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
	        var val = parseLocalizedNumber($(this).text());
	        if (!isNaN(val)) total_qty += val;
	    });
	    $("#total-qty").text(total_qty);
	    $('input[name="total_qty"]').val(total_qty);
	    $('input[name="item"]').val($('table.order-list tbody tr').length);
	}
</script>
@endpush
