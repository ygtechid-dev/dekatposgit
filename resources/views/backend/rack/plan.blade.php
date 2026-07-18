@extends('backend.layout.main')
@section('content')
<section>
    <div class="container-fluid">
        <div id="plan-alert" class="alert alert-success alert-dismissible text-center" style="display:none;">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <span id="plan-alert-text"></span>
        </div>

        <div class="card">
            <div class="card-header">
                <h4>{{$page_title}}</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    @if(count($lims_warehouse_list) > 1)
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>{{trans('file.Warehouse')}}</label>
                            <select id="warehouse_id" class="form-control">
                                <option value="">- Pilih -</option>
                                @foreach($lims_warehouse_list as $warehouse)
                                <option value="{{$warehouse->id}}">{{$warehouse->name}}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @else
                    <input type="hidden" id="warehouse_id" value="{{ count($lims_warehouse_list) ? $lims_warehouse_list[0]->id : '' }}">
                    @endif
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Pilih Rak</label>
                            <select id="rack_id" class="form-control">
                                <option value="">- Pilih Rak -</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Cari Barang</label>
                            <input type="text" id="lims_productcodeSearch" placeholder="Cari barang by kode/nama..." class="form-control" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <div>
                                <button type="button" id="add-btn" class="btn btn-success"><i class="dripicons-plus"></i> Add</button>
                                <button type="button" id="delete-selected-btn" class="btn btn-danger"><i class="dripicons-trash"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
                @if(count($lims_warehouse_list) == 0)
                <div class="alert alert-warning">Belum ada gudang dengan tipe ini. Tambahkan dulu di menu Warehouse (Settings &gt; Warehouse).</div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h4>{{$page_title}}</h4>
                <button type="button" id="save-btn" class="btn btn-primary"><i class="dripicons-checkmark"></i> Save</button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="plan-table">
                        <thead>
                            <tr>
                                <th class="text-center"><input type="checkbox" id="check-all"></th>
                                <th>ID</th>
                                <th>Barcode</th>
                                <th>Nama</th>
                                <th>Harga</th>
                                <th>Stok</th>
                                <th>Rak</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="plan-table-body">
                            <tr class="empty-row"><td colspan="8" class="text-center">Ga ono datane bro..</td></tr>
                        </tbody>
                    </table>
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
    $("ul#{{ $warehouse_type == 'toko' ? 'plan-toko-menu' : 'plan-gudang-menu' }}").addClass("active");

    var lims_product_array = [];
    var product_code = [];
    var product_name = [];
    var product_qty = [];
    var unit_cost = [];
    var lims_rack_array = [];
    var rackBaseUrl = "{{url('racks/warehouse')}}";
    var getProductBaseUrl = "{{url('rak/getproduct')}}";
    var planDataBaseUrl = "{{url('rak/plan-data')}}";
    var planAssignUrl = "{{url('rak/plan-assign')}}";
    var planUpdateBaseUrl = "{{url('rak/plan-update')}}";
    var planRemoveBaseUrl = "{{url('rak/plan-remove')}}";
    var csrfToken = "{{csrf_token()}}";

    function showMessage(text, type) {
        var $alert = $('#plan-alert');
        $alert.removeClass('alert-success alert-danger').addClass(type == 'error' ? 'alert-danger' : 'alert-success');
        $('#plan-alert-text').text(text);
        $alert.stop(true, true).fadeIn();
        setTimeout(function() { $alert.fadeOut(); }, 3000);
    }

    function populateRackSelect($select, selected) {
        var current = selected !== undefined ? selected : $select.val();
        var html = '<option value="">- Pilih Rak -</option>';
        $.each(lims_rack_array, function(i, rack) {
            html += '<option value="' + rack.id + '">' + rack.name + '</option>';
        });
        $select.html(html);
        if (current) $select.val(current);
    }

    function loadRacks(warehouse_id, callback) {
        if (!warehouse_id) {
            lims_rack_array = [];
            populateRackSelect($('#rack_id'));
            if (callback) callback();
            return;
        }
        $.get(rackBaseUrl + '/' + warehouse_id, function(data) {
            lims_rack_array = data;
            populateRackSelect($('#rack_id'));
            if (!data || !data.length) {
                showMessage('Belum ada rak untuk gudang/toko ini. Tambahkan dulu lewat menu Warehouse > Kelola Rak.', 'error');
            }
            if (callback) callback();
        }).fail(function(xhr) {
            lims_rack_array = [];
            populateRackSelect($('#rack_id'));
            showMessage('Gagal memuat daftar rak (' + xhr.status + '). Coba muat ulang halaman atau hubungi admin.', 'error');
            if (callback) callback();
        });
    }

    function loadProductPool(warehouse_id) {
        if (!warehouse_id) {
            lims_product_array = [];
            return;
        }
        $.get(getProductBaseUrl + '/' + warehouse_id, function(data) {
            lims_product_array = [];
            product_code = data[0];
            product_name = data[1];
            product_qty = data[2];
            unit_cost = data[3];
            $.each(product_code, function(index) {
                lims_product_array.push(product_code[index] + ' (' + product_name[index] + ')' + '|' + unit_cost[index]);
            });
        }).fail(function(xhr) {
            showMessage('Gagal memuat daftar produk (' + xhr.status + ').', 'error');
        });
    }

    function formatMoney(val) {
        val = parseFloat(val) || 0;
        return val.toLocaleString('id-ID');
    }

    function renderRow(row) {
        var $tr = $('<tr>').attr('data-id', row.id);
        var cols = '';
        cols += '<td class="text-center"><input type="checkbox" class="row-check"></td>';
        cols += '<td>' + row.id + '</td>';
        cols += '<td>' + row.barcode + '</td>';
        cols += '<td>' + row.nama + '</td>';
        cols += '<td>' + formatMoney(row.harga) + '</td>';
        cols += '<td>' + row.stok + '</td>';
        cols += '<td><select class="form-control row-rack-select"></select></td>';
        cols += '<td><button type="button" class="btn btn-sm btn-danger row-delete-btn"><i class="dripicons-trash"></i></button></td>';
        $tr.html(cols);
        populateRackSelect($tr.find('.row-rack-select'), row.rack_id);
        return $tr;
    }

    function loadPlanList(warehouse_id) {
        if (!warehouse_id) {
            $('#plan-table-body').html('<tr class="empty-row"><td colspan="8" class="text-center">Ga ono datane bro..</td></tr>');
            return;
        }
        $.get(planDataBaseUrl + '/' + warehouse_id, function(data) {
            var $body = $('#plan-table-body');
            $body.empty();
            if (!data.length) {
                $body.html('<tr class="empty-row"><td colspan="8" class="text-center">Ga ono datane bro..</td></tr>');
                return;
            }
            $.each(data, function(i, row) {
                $body.append(renderRow(row));
            });
        }).fail(function(xhr) {
            showMessage('Gagal memuat data plan rak (' + xhr.status + ').', 'error');
        });
    }

    $('#warehouse_id').on('change', function() {
        var id = $(this).val();
        loadRacks(id);
        loadProductPool(id);
        loadPlanList(id);
    });

    // auto-load when there's only a single warehouse (hidden input case)
    if ($('#warehouse_id').val()) {
        var initId = $('#warehouse_id').val();
        loadRacks(initId);
        loadProductPool(initId);
        loadPlanList(initId);
    }

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
                $(this).autocomplete("close");
                addProduct(data);
            }
        },
        select: function(event, ui) {
            addProduct(ui.item.value);
        }
    });

    $('#add-btn').on('click', function() {
        var typed = lims_productcodeSearch.val();
        if (!typed) {
            showMessage('Cari & pilih barang dulu.', 'error');
            return;
        }
        var match = $.grep(lims_product_array, function(item) {
            return item === typed || item.indexOf(typed) === 0;
        });
        if (match.length) {
            addProduct(match[0]);
        }
        else {
            showMessage('Barang tidak ditemukan, pilih dari saran pencarian.', 'error');
        }
    });

    function addProduct(data) {
        var warehouse_id = $('#warehouse_id').val();
        var rack_id = $('#rack_id').val();
        if (!warehouse_id) {
            showMessage('Pilih gudang/toko dulu.', 'error');
            return;
        }
        if (!rack_id) {
            showMessage('Pilih rak dulu.', 'error');
            return;
        }
        $.ajax({
            type: 'GET',
            url: "{{url('rak/lims_product_search')}}",
            data: { data: data },
            success: function(product) {
                $.ajax({
                    type: 'POST',
                    url: planAssignUrl,
                    data: {
                        _token: csrfToken,
                        product_id: product[2],
                        product_code: product[1],
                        warehouse_id: warehouse_id,
                        rack_id: rack_id
                    },
                    success: function(row) {
                        $('.empty-row').remove();
                        $('#plan-table-body').prepend(renderRow(row));
                        lims_productcodeSearch.val('');
                        showMessage('Alhamdulillah data berhasil ditambah.', 'success');
                    },
                    error: function() {
                        showMessage('Gagal menambah data.', 'error');
                    }
                });
            }
        });
    }

    $('#plan-table-body').on('click', '.row-delete-btn', function() {
        if (!confirm('Hapus barang ini dari rak?')) return;
        var $tr = $(this).closest('tr');
        var id = $tr.data('id');
        $.ajax({
            type: 'DELETE',
            url: planRemoveBaseUrl + '/' + id,
            data: { _token: csrfToken },
            success: function() {
                $tr.remove();
                if (!$('#plan-table-body tr').length) {
                    $('#plan-table-body').html('<tr class="empty-row"><td colspan="8" class="text-center">Ga ono datane bro..</td></tr>');
                }
                showMessage('Data berhasil dihapus.', 'success');
            }
        });
    });

    $('#delete-selected-btn').on('click', function() {
        var $rows = $('.row-check:checked').closest('tr');
        if (!$rows.length) {
            showMessage('Pilih baris yang mau dihapus dulu.', 'error');
            return;
        }
        if (!confirm('Hapus ' + $rows.length + ' barang dari rak?')) return;
        $rows.each(function() {
            var $tr = $(this);
            var id = $tr.data('id');
            $.ajax({
                type: 'DELETE',
                url: planRemoveBaseUrl + '/' + id,
                data: { _token: csrfToken },
                success: function() {
                    $tr.remove();
                    if (!$('#plan-table-body tr').length) {
                        $('#plan-table-body').html('<tr class="empty-row"><td colspan="8" class="text-center">Ga ono datane bro..</td></tr>');
                    }
                }
            });
        });
        showMessage('Data terpilih berhasil dihapus.', 'success');
    });

    $('#check-all').on('change', function() {
        $('.row-check').prop('checked', $(this).is(':checked'));
    });

    $('#plan-table-body').on('change', '.row-rack-select', function() {
        $(this).closest('tr').attr('data-dirty', '1');
    });

    $('#save-btn').on('click', function() {
        var $dirtyRows = $('#plan-table-body tr[data-dirty="1"]');
        if (!$dirtyRows.length) {
            showMessage('Alhamdulillah data berhasil disimpan.', 'success');
            return;
        }
        var pending = $dirtyRows.length;
        $dirtyRows.each(function() {
            var $tr = $(this);
            var id = $tr.data('id');
            var rack_id = $tr.find('.row-rack-select').val();
            $.ajax({
                type: 'PUT',
                url: planUpdateBaseUrl + '/' + id,
                data: { _token: csrfToken, rack_id: rack_id },
                success: function() {
                    $tr.removeAttr('data-dirty');
                    pending--;
                    if (pending === 0) {
                        showMessage('Alhamdulillah data berhasil disimpan.', 'success');
                    }
                }
            });
        });
    });
</script>
@endpush
