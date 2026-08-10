@extends('backend.layout.main')
@section('content')
<section>
    <div class="container-fluid">
        <div id="rack-alert" class="alert alert-dismissible text-center" style="display:none;">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <span id="rack-alert-text"></span>
        </div>

        <div class="card">
            <div class="card-header">
                <h4>Rak</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Gudang / Toko <span class="text-danger">*</span></label>
                            <select id="rack-warehouse-id" class="form-control selectpicker" data-live-search="true">
                                <option value="">- Pilih Gudang / Toko -</option>
                                @foreach($lims_warehouse_list as $warehouse)
                                <option value="{{$warehouse->id}}" @selected(count($lims_warehouse_list) == 1)>{{$warehouse->name}} ({{ $warehouse->type == 'toko' ? 'Toko' : 'Gudang' }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="form-group">
                            <label>Nama Rak</label>
                            <input type="text" id="rack-name-input" class="form-control" placeholder="Contoh: A1, Rak Depan, Etalase 2" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <div>
                                <button type="button" id="rack-add-btn" class="btn btn-primary btn-block"><i class="dripicons-plus"></i> Tambah Rak</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th style="width:60px;">#</th>
                                <th>Nama Rak</th>
                                <th style="width:150px;">Jumlah Barang</th>
                                <th style="width:160px;">{{trans('file.action')}}</th>
                            </tr>
                        </thead>
                        <tbody id="rack-list-body">
                            <tr><td colspan="4" class="text-center">Pilih gudang / toko terlebih dahulu.</td></tr>
                        </tbody>
                    </table>
                </div>

                <p class="text-muted mb-0">
                    Setelah rak dibuat, tempatkan barangnya lewat
                    <a href="{{route('rack.planToko')}}">Plan Toko</a> /
                    <a href="{{route('rack.planGudang')}}">Plan Gudang</a>.
                </p>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script type="text/javascript">
$(document).ready(function() {

    // Semua URL dibangun dari route() (absolut). Versi lama memakai URL
    // relatif seperti 'racks/' + id yang salah arah begitu halaman ini
    // dibuka dari path bertingkat.
    var RACK_LIST_URL  = "{{ url('racks/warehouse') }}";
    var RACK_STORE_URL = "{{ route('rack.store') }}";
    var RACK_BASE_URL  = "{{ url('racks') }}";
    var CSRF = $('meta[name="csrf-token"]').attr('content');

    function showAlert(message, isError) {
        $('#rack-alert')
            .removeClass('alert-success alert-danger')
            .addClass(isError ? 'alert-danger' : 'alert-success');
        $('#rack-alert-text').text(message);
        $('#rack-alert').show();
        if (!isError) {
            setTimeout(function() { $('#rack-alert').fadeOut(); }, 4000);
        }
    }

    // Setiap panggilan AJAX di halaman ini punya handler error. Modal rak
    // yang lama diam saja kalau request-nya gagal (izin kurang, tabel racks
    // belum dibuat, sesi habis), sehingga terlihat seperti fitur "tambah
    // rak" tidak ada sama sekali.
    function ajaxError(xhr) {
        var msg = 'Gagal menghubungi server.';
        if (xhr.responseJSON && xhr.responseJSON.message) {
            msg = xhr.responseJSON.message;
        }
        else if (xhr.status === 419) {
            msg = 'Sesi sudah habis. Muat ulang halaman lalu coba lagi.';
        }
        else if (xhr.status === 403) {
            msg = 'Anda tidak punya izin untuk mengelola rak.';
        }
        else if (xhr.status === 500) {
            msg = 'Terjadi error di server. Pastikan tabel "racks" sudah dibuat di database.';
        }
        showAlert(msg, true);
    }

    function loadRackList() {
        var warehouse_id = $('#rack-warehouse-id').val();
        if (!warehouse_id) {
            $('#rack-list-body').html('<tr><td colspan="4" class="text-center">Pilih gudang / toko terlebih dahulu.</td></tr>');
            return;
        }
        $.ajax({
            url: RACK_LIST_URL + '/' + warehouse_id,
            type: 'GET',
            success: function(data) {
                if (!data || data.length === 0) {
                    $('#rack-list-body').html('<tr><td colspan="4" class="text-center">Belum ada rak di gudang / toko ini.</td></tr>');
                    return;
                }
                var html = '';
                $.each(data, function(i, rack) {
                    html += '<tr data-id="' + rack.id + '">'
                          + '<td>' + (i + 1) + '</td>'
                          + '<td class="rack-name">' + $('<div>').text(rack.name).html() + '</td>'
                          + '<td>' + (rack.product_count || 0) + '</td>'
                          + '<td>'
                          + '<button type="button" class="btn btn-sm btn-link rack-edit-btn" data-id="' + rack.id + '"><i class="dripicons-document-edit"></i> Ubah</button> '
                          + '<button type="button" class="btn btn-sm btn-link text-danger rack-delete-btn" data-id="' + rack.id + '"><i class="dripicons-trash"></i> Hapus</button>'
                          + '</td></tr>';
                });
                $('#rack-list-body').html(html);
            },
            error: ajaxError
        });
    }

    $(document).on('change', '#rack-warehouse-id', loadRackList);

    $(document).on('click', '#rack-add-btn', function() {
        var warehouse_id = $('#rack-warehouse-id').val();
        var name = $.trim($('#rack-name-input').val());
        if (!warehouse_id) {
            showAlert('Pilih gudang / toko terlebih dahulu.', true);
            return;
        }
        if (!name) {
            showAlert('Nama rak harus diisi.', true);
            return;
        }
        $.ajax({
            url: RACK_STORE_URL,
            type: 'POST',
            data: { _token: CSRF, warehouse_id: warehouse_id, name: name },
            success: function() {
                $('#rack-name-input').val('').focus();
                showAlert('Rak "' + name + '" berhasil dibuat.', false);
                loadRackList();
            },
            error: ajaxError
        });
    });

    // Enter di kolom nama = klik Tambah Rak.
    $(document).on('keypress', '#rack-name-input', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#rack-add-btn').click();
        }
    });

    $(document).on('click', '.rack-edit-btn', function() {
        var id = $(this).data('id');
        var current = $(this).closest('tr').find('.rack-name').text();
        var name = prompt('Nama rak baru:', current);
        if (name === null) return;
        name = $.trim(name);
        if (!name) {
            showAlert('Nama rak harus diisi.', true);
            return;
        }
        $.ajax({
            url: RACK_BASE_URL + '/' + id,
            type: 'POST',
            data: { _token: CSRF, _method: 'PUT', name: name },
            success: function() {
                showAlert('Nama rak berhasil diubah.', false);
                loadRackList();
            },
            error: ajaxError
        });
    });

    $(document).on('click', '.rack-delete-btn', function() {
        if (!confirm('Hapus rak ini?')) return;
        var id = $(this).data('id');
        $.ajax({
            url: RACK_BASE_URL + '/' + id,
            type: 'POST',
            data: { _token: CSRF, _method: 'DELETE' },
            success: function() {
                showAlert('Rak berhasil dihapus.', false);
                loadRackList();
            },
            error: ajaxError
        });
    });

    // Kalau cuma ada satu gudang/toko, langsung tampilkan raknya.
    loadRackList();
});
</script>
@endpush
