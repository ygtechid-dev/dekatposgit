@extends('backend.layout.main')
@section('content')
<section class="forms">
    <div class="container-fluid">
        <h4 class="text-center">Laporan Stok Rak</h4>
    </div>
    <div class="container-fluid">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Gudang/Toko</label>
                            <select id="print-warehouse-id" class="form-control">
                                <option value="">- Pilih Gudang/Toko -</option>
                                @foreach(\App\Models\Warehouse::where('is_active', true)->get() as $warehouse)
                                <option value="{{$warehouse->id}}">{{$warehouse->name}} ({{$warehouse->type == 'toko' ? 'Toko' : 'Gudang'}})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Rak (opsional)</label>
                            {{-- Semua rak dirender langsung dari server (tanpa AJAX). Kalau JS
                                 jalan, daftar ini dipersempit sesuai gudang/toko yang dipilih;
                                 kalau JS gagal, rak tetap bisa dipilih manual. --}}
                            <select id="print-rack-id" class="form-control">
                                <option value="">- Semua Rak -</option>
                                @foreach($lims_rack_all as $rack)
                                <option value="{{$rack->id}}" data-warehouse="{{$rack->warehouse_id}}">{{$rack->name}}{{ $rack->warehouse ? ' - ' . $rack->warehouse->name : '' }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">{{ count($lims_rack_all) }} rak terdaftar</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <a href="#" id="print-rack-stock-btn" target="_blank" class="btn btn-info btn-block"><i class="fa fa-print"></i> Cetak Lembar Stok Opname</a>
                        </div>
                    </div>
                </div>
                <p class="italic"><small>Cetak daftar produk per rak beserta stok sistem, dengan kolom kosong untuk mencatat stok fisik secara tertulis sebelum di-input ke Penyesuaian.</small></p>
                <hr>
                {{ Form::open(['route' => 'report.rackStock', 'method' => 'get', 'id' => 'filter-form']) }}
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Rak Toko</label>
                            <select name="rak_toko" class="form-control">
                                <option value="">- Semua Rak Toko -</option>
                                @foreach($lims_rack_toko_list as $rack)
                                <option value="{{$rack->id}}" @selected($rak_toko_filter == $rack->id)>{{$rack->name}}{{ $rack->warehouse ? ' - ' . $rack->warehouse->name : '' }}</option>
                                @endforeach
                                @if(!count($lims_rack_toko_list))
                                <option value="" disabled>- Belum ada rak di gudang bertipe Toko -</option>
                                @endif
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Rak Gudang</label>
                            <select name="rak_gudang" class="form-control">
                                <option value="">- Semua Rak Gudang -</option>
                                @foreach($lims_rack_gudang_list as $rack)
                                <option value="{{$rack->id}}" @selected($rak_gudang_filter == $rack->id)>{{$rack->name}}{{ $rack->warehouse ? ' - ' . $rack->warehouse->name : '' }}</option>
                                @endforeach
                                @if(!count($lims_rack_gudang_list))
                                <option value="" disabled>- Belum ada rak di gudang -</option>
                                @endif
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary btn-block"><i class="dripicons-search"></i> Filter</button>
                        </div>
                    </div>
                </div>
                {{ Form::close() }}
            </div>
        </div>
    </div>
    <div class="table-responsive mb-4">
        <table id="report-table" class="table table-hover">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Barcode</th>
                    <th>Nama</th>
                    <th>Satuan</th>
                    <th>Rak Toko</th>
                    <th>Rak Gudang</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lims_product_data as $key=>$product)
                <tr>
                    <td>{{$key+1}}</td>
                    <td>{{$product->barcode}}</td>
                    <td>{{$product->nama}}</td>
                    <td>{{$product->satuan ?? '-'}}</td>
                    <td>{{$product->rak_toko ?? '-'}}</td>
                    <td>{{$product->rak_gudang ?? '-'}}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
@endsection

@push('scripts')
<script type="text/javascript">
    $("ul#report").siblings('a').attr('aria-expanded','true');
    $("ul#report").addClass("show");
    $("ul#report #rack-stock-report-menu").addClass("active");
    if ($.fn.selectpicker) { $('.selectpicker').selectpicker('refresh'); }

    var printRackStockBaseUrl = "{{url('report/rack_stock/print')}}";

    // Daftar seluruh rak, dikirim dari server. Dipakai untuk membangun ulang
    // isi dropdown rak setiap kali gudang/toko diganti.
    var allRackData = @json($lims_rack_all->map(function($r) {
        return ['id' => $r->id, 'name' => $r->name, 'warehouse_id' => $r->warehouse_id];
    })->values());

    function buildPrintRackOptions() {
        var warehouseId = $('#print-warehouse-id').val();
        var $rackSelect = $('#print-rack-id');
        var matched = [];

        // Kalau data rak kosong, biarkan opsi hasil render server apa adanya
        // supaya dropdown tidak malah jadi kosong.
        if (!allRackData || !allRackData.length) return;

        for (var i = 0; i < allRackData.length; i++) {
            if (warehouseId && String(allRackData[i].warehouse_id) === String(warehouseId)) {
                matched.push(allRackData[i]);
            }
        }

        var placeholder = !warehouseId
            ? '- Pilih gudang/toko dulu -'
            : (matched.length ? '- Semua Rak -' : '- Belum ada rak untuk gudang/toko ini -');

        $rackSelect.empty();
        $rackSelect.append($('<option>').val('').text(placeholder));
        for (var j = 0; j < matched.length; j++) {
            $rackSelect.append($('<option>').val(matched[j].id).text(matched[j].name));
        }
        $rackSelect.val('');
        if ($rackSelect.hasClass('selectpicker') && $.fn.selectpicker) {
            $rackSelect.selectpicker('refresh');
        }
    }

    $(document).on('change', '#print-warehouse-id', buildPrintRackOptions);
    $(function() { buildPrintRackOptions(); });

    $('#print-rack-stock-btn').on('click', function(e) {
        var warehouseId = $('#print-warehouse-id').val();
        if (!warehouseId) {
            e.preventDefault();
            alert('Pilih gudang/toko dulu.');
            return;
        }
        var rackId = $('#print-rack-id').val();
        var url = printRackStockBaseUrl + '?warehouse_id=' + warehouseId;
        if (rackId) url += '&rack_id=' + rackId;
        $(this).attr('href', url);
    });

    $('#report-table').DataTable( {
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
            {
                extend: 'excel',
                text: '<i title="export to excel" class="dripicons-document-new"></i>',
                exportOptions: { columns: ':visible' },
            },
            {
                extend: 'csv',
                text: '<i title="export to csv" class="fa fa-file-text-o"></i>',
                exportOptions: { columns: ':visible' },
            },
            {
                extend: 'print',
                text: '<i title="print" class="fa fa-print"></i>',
                exportOptions: { columns: ':visible' },
            }
        ],
    } );
</script>
@endpush
