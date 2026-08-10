{{-- Layout top-head dipakai supaya POS Simple tampil layar penuh tanpa sidebar
     tetap, sama seperti halaman POS lama. Sidebar tetap di-include dan dibuka
     lewat tombol garis tiga di kiri atas. --}}
@extends('backend.layout.top-head')
@section('content')

{{-- Sidebar dibungkus <nav class="side-navbar shrink"> seperti di pos.blade.php.
     Partial sidebar hanya berisi <ul>-nya saja; tanpa pembungkus ini menunya
     tampil polos di atas halaman. "shrink" = tersembunyi, dibuka lewat #toggle-btn. --}}
<nav class="side-navbar shrink">
    <span class="brand-big">
        @if($general_setting->site_logo)
        <a href="{{url('/')}}"><img src="{{url('logo', $general_setting->site_logo)}}" width="115"></a>
        @else
        <a href="{{url('/')}}"><h1 class="d-inline">{{$general_setting->site_title}}</h1></a>
        @endif
    </span>
    @include('backend.layout.sidebar')
</nav>

<section class="forms pos-section">
<div class="container-fluid">

<style>
/* ================= POS Simple =================
   Warna mengikuti primary aplikasi (--theme-color dari custom-{theme}.css).
   Nilai cadangan #7c5cc4 dipakai kalau variabelnya tidak tersedia. */
.ps-wrap{padding:0 12px 95px}
/* area keranjang ikut tinggi layar supaya bar tombol tidak menutupi baris terakhir */
.ps-table-scroll{max-height:calc(100vh - 340px);min-height:180px;overflow-y:auto}
.ps-green{background:var(--theme-color,#7c5cc4)}
.ps-toolbar{display:flex;gap:8px;align-items:center;margin-bottom:10px;flex-wrap:wrap}
.ps-toolbar .ps-level{max-width:190px}
.ps-search-box{position:relative;flex:1 1 320px;min-width:260px}
#ps-search{border:1px solid #cfd6e4;height:38px}
#ps-search:focus{border-color:var(--theme-color,#7c5cc4);box-shadow:0 0 0 .15rem rgba(124,92,196,.2)}
#ps-suggest{position:absolute;z-index:1050;left:0;right:0;top:40px;background:#fff;border:1px solid #cfd6e4;
    border-radius:4px;box-shadow:0 6px 18px rgba(0,0,0,.12);max-height:320px;overflow:auto;display:none}
#ps-suggest .ps-sg{padding:7px 10px;cursor:pointer;border-bottom:1px solid #f0f2f7;font-size:13px;display:flex;gap:10px}
#ps-suggest .ps-sg:last-child{border-bottom:none}
#ps-suggest .ps-sg.active,#ps-suggest .ps-sg:hover{background:#f1edfa}
#ps-suggest .ps-sg .c{color:#8a90a2;min-width:110px}
#ps-suggest .ps-sg .n{flex:1;font-weight:600;color:#2f3542}
#ps-suggest .ps-sg .p{color:var(--theme-color,#7c5cc4);font-weight:700;text-align:right;min-width:90px}
#ps-suggest .ps-sg .s{color:#8a90a2;min-width:70px;text-align:right}
#ps-suggest .ps-empty{padding:10px;color:#8a90a2;font-size:13px}

.ps-table{width:100%;background:#fff;font-size:13px}
.ps-table thead th{background:var(--theme-color,#7c5cc4);color:#fff;border:none;font-size:12px;letter-spacing:.4px;padding:10px 8px;white-space:nowrap}
.ps-table tbody td{padding:6px 8px;vertical-align:middle;border-top:1px solid #eef0f5}
.ps-table tbody tr.ps-active > td{background:#e4e6fc}
.ps-table tbody tr.ps-active > td:first-child{box-shadow:inset 4px 0 0 0 var(--theme-color,#7c5cc4)}
.ps-table .num{text-align:right}
.ps-table input.ps-in{border:1px solid #dde2ec;border-radius:3px;padding:3px 6px;height:30px;width:100%;text-align:right;font-size:13px}
.ps-table input.ps-in:focus{border-color:var(--theme-color,#7c5cc4);outline:none}
.ps-table td.ps-qty{width:110px}
.ps-table td.ps-gross,.ps-table td.ps-disc{width:120px}
.ps-empty-row td{text-align:center;color:#9aa0b0;padding:40px 0 !important}

.ps-card{border:1px solid #e2e6ef;border-radius:6px;overflow:hidden;background:#fff}
.ps-card .hd{background:var(--theme-color,#7c5cc4);color:#fff;padding:10px 14px;font-weight:700}
.ps-card .bd{padding:12px 14px}
.ps-line{display:flex;align-items:center;justify-content:space-between;padding:7px 0;border-bottom:1px solid #f1f3f8}
.ps-line:last-child{border-bottom:none}
.ps-line .lbl{color:#5d6478;font-size:13px}
.ps-line .val{font-weight:700;color:#2f3542}
.ps-badge{background:#e4e6fc;color:#6244a6;border-radius:3px;padding:2px 8px;font-size:11px;font-weight:700}

.ps-actionbar{position:fixed;left:0;right:0;bottom:0;background:#fff;border-top:1px solid #e6e9f0;
    padding:9px 10px;display:flex;gap:8px;justify-content:center;flex-wrap:wrap;z-index:1000;
    box-shadow:0 -2px 10px rgba(34,41,47,.07)}
.ps-act{position:relative;min-width:104px;border:none;border-radius:4px;color:#fff;padding:10px 12px 12px;
    font-size:12px;font-weight:600;line-height:1.2;text-align:center}
.ps-act i{display:block;font-size:16px;margin-bottom:4px}
.ps-act .k{position:absolute;top:-8px;right:-6px;background:#3b4253;color:#fff;font-size:10px;font-weight:700;
    border-radius:3px;padding:1px 5px;box-shadow:0 1px 3px rgba(0,0,0,.2)}
.ps-act:disabled{opacity:.5}
/* palet tombol mengikuti warna yang sudah dipakai aplikasi */
.ps-b-save{background:var(--theme-color,#7c5cc4)}
.ps-b-del{background:#eb543a}
.ps-b-credit{background:#2196f3}
.ps-b-void{background:#ffc107;color:#5b4400}
.ps-b-void .k{background:#5b4400}
.ps-b-load{background:#6244a6}

/* keterangan tombol: sengaja tipis supaya tidak mencuri perhatian */
.ps-legend{font-size:11px;color:#a8adbd;margin:8px 2px 0;line-height:1.7}
.ps-legend b{font-weight:600;color:#8a90a2}
.ps-legend a{color:#a8adbd;text-decoration:underline}

/* panel kanan */
.ps-sum{font-size:12px}
.ps-sum .r{display:flex;justify-content:space-between;padding:4px 0}
.ps-sum .r .k{color:#7b8194}
.ps-sum .r .v{font-weight:600;color:#2f3542}
.ps-sum .r.disc .v{color:#d63031}
.ps-grand-box{border-top:2px solid #eef0f5;margin-top:6px;padding-top:10px;text-align:center}
.ps-grand-box .cap{color:#8a90a2;font-size:11px;letter-spacing:.5px}
.ps-grand-box .amt{font-size:32px;font-weight:800;color:var(--theme-color,#7c5cc4);line-height:1.15;word-break:break-all}
.ps-grand-box .clk{color:#b0b5c3;font-size:10px;margin-top:3px}

.ps-promo-list{max-height:230px;overflow:auto}
.ps-promo{display:flex;justify-content:space-between;align-items:flex-start;gap:6px;padding:6px 0;
    border-bottom:1px dashed #eef0f5;font-size:11px}
.ps-promo:last-child{border-bottom:none}
.ps-promo .nm{flex:1;color:#2f3542;font-weight:600;line-height:1.3}
.ps-promo .nm small{display:block;color:#a8adbd;font-weight:400}
.ps-promo .pr{text-align:right;white-space:nowrap}
.ps-promo .pr .old{color:#b0b5c3;text-decoration:line-through;font-size:10px;display:block}
.ps-promo .pr .new{color:#d63031;font-weight:700}
.ps-promo-empty{color:#a8adbd;font-size:11px;padding:6px 0}

/* modal tambah barang */
#ps-add-modal .ps-add-name{font-size:16px;font-weight:700;color:#2f3542;line-height:1.3}
#ps-add-modal .ps-add-code{color:#8a90a2;font-size:12px}
/* daftar satuan: sengaja bukan <select>, karena front.js mengubah semua <select>
   jadi bootstrap-select sehingga fokus & panah keyboard tidak bisa dikendalikan */
.ps-unit-list{border:1px solid #dde2ec;border-radius:4px;max-height:180px;overflow:auto;outline:none}
.ps-unit-list:focus{border-color:var(--theme-color,#7c5cc4);box-shadow:0 0 0 .15rem rgba(124,92,196,.2)}
.ps-unit-row{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:9px 10px;
    cursor:pointer;border-bottom:1px solid #f0f2f7;font-size:14px}
.ps-unit-row:last-child{border-bottom:none}
.ps-unit-row.active{background:#e4e6fc;font-weight:700}
.ps-unit-row .no{background:#eef0f7;border-radius:3px;padding:0 5px;font-size:10px;color:#6b7186;margin-right:6px}
.ps-unit-row .pr{color:var(--theme-color,#7c5cc4);font-weight:700;white-space:nowrap}
#ps-add-modal input[name="ps_qty"]{height:56px;font-size:28px;text-align:right;font-weight:700}
#ps-add-modal .ps-add-total{font-size:24px;font-weight:800;color:var(--theme-color,#7c5cc4)}
#ps-add-modal .step{font-size:11px;color:#a8adbd}
.ps-qc{margin:2px}
#ps-pay-modal .amt-big{font-size:30px;font-weight:800;color:var(--theme-color,#7c5cc4)}
#ps-pay-modal input[name="ps_received"]{font-size:24px;height:52px;text-align:right;font-weight:700}
.ps-change-pos{color:var(--theme-color,#7c5cc4)}.ps-change-neg{color:#d63031}
</style>

<div class="ps-wrap">
    <!-- ============ header layar penuh ============ -->
    <header>
        <nav class="navbar mb-2">
            <a id="toggle-btn" href="#" class="menu-btn"><i class="fa fa-bars"></i></a>
            <div class="navbar-header ml-3">
                <span class="brand-text"><h3 class="mb-0">POS <span>Simple</span></h3></span>
            </div>
            <ul class="nav-menu list-unstyled d-flex flex-row align-items-center ml-auto mb-0">
                <li class="nav-item mr-3 d-none d-md-block ps-clock-top"></li>
                <li class="nav-item"><a href="{{route('sale.pos')}}" data-toggle="tooltip" title="POS Lengkap"><i class="dripicons-swap"></i></a></li>
                <li class="nav-item ml-3"><a href="{{route('sales.printLastReciept')}}" data-toggle="tooltip" title="{{trans('file.Print Last Reciept')}}"><i class="dripicons-print"></i></a></li>
                <li class="nav-item ml-3"><a id="btnFullscreen" href="#" data-toggle="tooltip" title="Layar Penuh"><i class="dripicons-expand"></i></a></li>
                <li class="nav-item ml-3">
                    <a rel="nofollow" class="nav-link dropdown-item"><i class="dripicons-user"></i> <span>{{ucfirst(Auth::user()->name)}}</span> <i class="fa fa-angle-down"></i></a>
                    <ul class="right-sidebar">
                        <li><a href="{{route('user.profile', ['id' => Auth::id()])}}"><i class="dripicons-user"></i> {{trans('file.profile')}}</a></li>
                        <li>
                            <a href="{{ route('logout') }}"
                               onclick="event.preventDefault(); document.getElementById('ps-logout-form').submit();"><i class="dripicons-power"></i> {{trans('file.logout')}}</a>
                            <form id="ps-logout-form" action="{{ route('logout') }}" method="POST" style="display:none">@csrf</form>
                        </li>
                    </ul>
                </li>
            </ul>
        </nav>
    </header>

    @if(session()->has('message'))
    <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert">&times;</button>{{ session()->get('message') }}</div>
    @endif
    @if(session()->has('not_permitted'))
    <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert">&times;</button>{{ session()->get('not_permitted') }}</div>
    @endif

    <div class="row">
        <!-- ============ kiri: keranjang ============ -->
        <div class="col-lg-9 col-md-8">
            <div class="ps-toolbar">
                <select id="ps-price-level" class="form-control ps-level">
                    @foreach($price_levels as $lv => $label)
                    <option value="{{$lv}}">{{$label}}</option>
                    @endforeach
                </select>
                <div class="ps-search-box">
                    <input type="text" id="ps-search" class="form-control" autocomplete="off"
                           placeholder="Cari Barang by Kode/Nama  (F2)">
                    <div id="ps-suggest"></div>
                </div>
            </div>

            <div class="table-responsive ps-table-scroll">
                <table class="table ps-table mb-0">
                    <thead>
                        <tr>
                            <th style="width:130px">BARCODE</th>
                            <th>NAMA</th>
                            <th class="num" style="width:110px">QTY</th>
                            <th class="num" style="width:120px">GROSS</th>
                            <th class="num" style="width:120px">DISC</th>
                            <th class="num" style="width:110px">NETT</th>
                            <th class="num" style="width:120px">JUMLAH</th>
                            <th style="width:50px"></th>
                        </tr>
                    </thead>
                    <tbody id="ps-body"></tbody>
                </table>
            </div>

            <div class="ps-legend">
                <b>&uarr;&darr;</b> pilih &middot; <b>+ -</b> qty &middot; <b>Del</b> hapus &middot; <b>Enter</b> ubah satuan/qty &middot; <b>Esc</b> ke kolom cari
            </div>
        </div>

        <!-- ============ kanan: faktur ============ -->
        <div class="col-lg-3 col-md-4">
            <div class="ps-card">
                <div class="hd">
                    <i class="dripicons-document"></i> Faktur Penjualan
                    <a href="#" id="ps-register-btn" class="float-right d-none" style="color:#fff" title="Detail / Tutup Kasir"><i class="dripicons-briefcase"></i></a>
                </div>
                <div class="bd">
                    <div class="ps-line">
                        <span class="lbl"><i class="dripicons-user"></i> Kasir</span>
                        <span class="ps-badge">{{ strtoupper(Auth::user()->name) }}</span>
                    </div>
                    <div class="ps-line">
                        <span class="lbl">Gudang</span>
                        <span class="val" style="max-width:60%">
                            <select id="ps-warehouse" class="form-control form-control-sm">
                                @foreach($warehouse_list as $warehouse)
                                <option value="{{$warehouse->id}}" @if($warehouse->id == $default_warehouse_id) selected @endif>{{$warehouse->name}}</option>
                                @endforeach
                            </select>
                        </span>
                    </div>
                    <div class="ps-line">
                        <span class="lbl">Pelanggan</span>
                        <span class="val" style="max-width:60%">
                            <select id="ps-customer" class="form-control form-control-sm">
                                @foreach($customer_list as $customer)
                                <option value="{{$customer->id}}" @if($customer->id == $default_customer_id) selected @endif>{{$customer->name}}</option>
                                @endforeach
                            </select>
                        </span>
                    </div>
                </div>
            </div>

            <!-- total pembelanjaan -->
            <div class="ps-card mt-2">
                <div class="hd">Total Pembelanjaan</div>
                <div class="bd ps-sum">
                    <div class="r"><span class="k">Item</span><span class="v" id="ps-item-count">0</span></div>
                    <div class="r"><span class="k">Total Qty</span><span class="v" id="ps-qty-count">0</span></div>
                    <div class="r"><span class="k">Subtotal</span><span class="v" id="ps-subtotal">0</span></div>
                    <div class="r disc"><span class="k">Diskon</span><span class="v" id="ps-discount">0</span></div>
                    <div class="ps-grand-box">
                        <div class="cap">TOTAL BAYAR</div>
                        <div class="amt" id="ps-grand">0</div>
                        <div class="clk" id="ps-clock"></div>
                    </div>
                </div>
            </div>

            <!-- promosi -->
            <div class="ps-card mt-2">
                <div class="hd">Promosi Hari Ini</div>
                <div class="bd">
                    <div class="ps-promo-list" id="ps-promo-list">
                        <div class="ps-promo-empty">Memuat promo&hellip;</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ bar tombol ============ -->
    <div class="ps-actionbar">
        <button type="button" class="btn ps-act ps-b-save" id="ps-btn-save">
            <span class="k">F4</span><i class="dripicons-print"></i>Simpan
        </button>
        <button type="button" class="btn ps-act ps-b-del" id="ps-btn-clear">
            <span class="k">Alt+H</span><i class="dripicons-trash"></i>Hapus
        </button>
        <button type="button" class="btn ps-act ps-b-credit" id="ps-btn-credit">
            <span class="k">Alt+B</span><i class="dripicons-document"></i>Bayar Piutang
        </button>
        <button type="button" class="btn ps-act ps-b-void" id="ps-btn-void">
            <span class="k">Alt+V</span><i class="dripicons-clockwise"></i>Create Void
        </button>
        <button type="button" class="btn ps-act ps-b-load" id="ps-btn-load">
            <span class="k">Alt+L</span><i class="dripicons-download"></i>Load Void
        </button>
    </div>
</div>

<!-- ============ form tersembunyi: dikirim ke SaleController@store ============ -->
<form id="ps-form" action="{{ route('sales.store') }}" method="post" style="display:none">
    @csrf
    <input type="hidden" name="pos" value="1">
    <input type="hidden" name="draft" value="0">
    <input type="hidden" name="pos_url" value="pos-simple">
    <input type="hidden" name="sale_status" value="1">
    <input type="hidden" name="coupon_active" value="0">
    <input type="hidden" name="coupon_discount" value="0">
    <input type="hidden" name="used_points" value="0">
    <input type="hidden" name="order_tax_rate" value="0">
    <input type="hidden" name="order_tax" value="0">
    <input type="hidden" name="order_discount_type" value="Flat">
    <input type="hidden" name="order_discount_value" value="0">
    <input type="hidden" name="order_discount" value="0">
    <input type="hidden" name="shipping_cost" value="0">
    <input type="hidden" name="total_tax" value="0">
    <input type="hidden" name="sale_note" value="">
    <input type="hidden" name="staff_note" value="">
    <input type="hidden" name="payment_note" value="">
    <input type="hidden" name="payment_receiver" value="">
    <input type="hidden" name="currency_id" value="{{ $currency->id }}">
    <input type="hidden" name="exchange_rate" value="{{ $currency->exchange_rate }}">
    <input type="hidden" name="biller_id" value="{{ $default_biller_id }}">
    <div id="ps-form-dynamic"></div>
</form>

<!-- ============ modal tambah barang: satuan -> qty -> enter ============ -->
<div class="modal fade" id="ps-add-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document" style="max-width:420px">
        <div class="modal-content">
            <div class="modal-header ps-green" style="color:#fff">
                <h5 class="modal-title" id="ps-add-title">Tambah Barang</h5>
                <button type="button" class="close" data-dismiss="modal" style="color:#fff"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="ps-add-name" id="ps-add-name"></div>
                <div class="ps-add-code mb-3" id="ps-add-code"></div>

                <label class="mb-1">Satuan <span class="step">(&uarr;&darr; atau angka 1-9, Enter untuk lanjut)</span></label>
                <div class="ps-unit-list" id="ps-unit-list" tabindex="0"></div>

                <label class="mt-3 mb-1">Jumlah <span class="step">(Enter untuk simpan)</span></label>
                <input type="text" name="ps_qty" class="form-control" autocomplete="off" value="1">

                <div class="d-flex justify-content-between align-items-baseline mt-3">
                    <div>
                        <div class="step">Harga satuan</div>
                        <div id="ps-add-price" style="font-weight:600">0</div>
                    </div>
                    <div class="text-right">
                        <div class="step">Sisa stok <span id="ps-add-stock">0</span></div>
                        <div class="ps-add-total" id="ps-add-total">0</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal (Esc)</button>
                <button type="button" class="btn btn-success" id="ps-add-submit">Masukkan (Enter)</button>
            </div>
        </div>
    </div>
</div>

<!-- ============ modal bayar ============ -->
<div class="modal fade" id="ps-pay-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header ps-green" style="color:#fff">
                <h5 class="modal-title">Pembayaran Tunai</h5>
                <button type="button" class="close" data-dismiss="modal" style="color:#fff"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-6">
                        <div class="text-muted small">Total Tagihan</div>
                        <div class="amt-big" id="ps-pay-total">0</div>
                    </div>
                    <div class="col-6 text-right">
                        <div class="text-muted small">Kembalian</div>
                        <div class="amt-big ps-change-pos" id="ps-pay-change">0</div>
                    </div>
                </div>
                <hr>
                <label class="mb-1">Uang Diterima <span class="text-muted small">(F4 = uang pas)</span></label>
                <input type="text" name="ps_received" class="form-control" autocomplete="off">
                <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary ps-qc" data-qc="pas">Uang Pas</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary ps-qc" data-qc="5000">5.000</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary ps-qc" data-qc="10000">10.000</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary ps-qc" data-qc="20000">20.000</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary ps-qc" data-qc="50000">50.000</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary ps-qc" data-qc="100000">100.000</button>
                    <button type="button" class="btn btn-sm btn-outline-danger ps-qc" data-qc="0">Clear</button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal (Esc)</button>
                <button type="button" class="btn btn-success" id="ps-pay-submit">Simpan &amp; Cetak (Enter)</button>
            </div>
        </div>
    </div>
</div>

<!-- ============ modal awal kasir (wajib sebelum bisa jualan) ============ -->
<div id="ps-register-modal" tabindex="-1" role="dialog" class="modal fade text-left" data-backdrop="static" data-keyboard="false">
    <div role="document" class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('cashRegister.store') }}" method="post">
                @csrf
                <div class="modal-header ps-green" style="color:#fff">
                    <h5 class="modal-title">Modal Awal Kasir</h5>
                </div>
                <div class="modal-body">
                    <p class="italic"><small>Isi modal uang kembalian di laci sebelum mulai berjualan.</small></p>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>{{trans('file.Warehouse')}} *</label>
                            @if(\Auth::user()->role_id > 2)
                            {{-- Gudang kasir ditetapkan admin, tidak boleh dipilih sendiri. --}}
                            @php $assigned_warehouse = $warehouse_list->firstWhere('id', \Auth::user()->warehouse_id); @endphp
                            <input type="hidden" name="warehouse_id" value="{{ $assigned_warehouse->id ?? '' }}">
                            <input type="text" class="form-control" value="{{ $assigned_warehouse->name ?? 'Belum ditetapkan admin' }}" readonly>
                            @else
                            <select required name="warehouse_id" class="form-control">
                                @foreach($warehouse_list as $warehouse)
                                <option value="{{$warehouse->id}}" @if($warehouse->id == $default_warehouse_id) selected @endif>{{$warehouse->name}}</option>
                                @endforeach
                            </select>
                            @endif
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Persediaan Uang Kembalian *</label>
                            <input type="number" step="any" name="cash_in_hand" required class="form-control" autofocus>
                        </div>
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-success">{{trans('file.submit')}}</button>
                            <a href="{{ route('cashRegister.index') }}" class="btn btn-link">Halaman Kasir</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============ modal detail / tutup kasir ============ -->
<div id="ps-register-details-modal" tabindex="-1" role="dialog" class="modal fade text-left">
    <div role="document" class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header ps-green" style="color:#fff">
                <h5 class="modal-title">{{trans('file.Cash Register Details')}}</h5>
                <button type="button" data-dismiss="modal" class="close" style="color:#fff"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                @if(\Auth::user()->role_id <= 2)
                <p>{{trans('file.Please review the transaction and payments.')}}</p>
                @else
                <p>Hitung seluruh uang di laci kasir, lalu masukkan jumlahnya apa adanya. Rincian penjualan hanya bisa dilihat oleh admin saat approval.</p>
                @endif
                <table class="table table-sm">
                    <tbody>
                        <tr><td>Modal Kembalian:</td><td id="ps-cash-in-hand" class="text-right">0</td></tr>
                        @if(\Auth::user()->role_id <= 2)
                        <tr><td>{{trans('file.Total Sale Amount')}}:</td><td id="ps-total-sale" class="text-right"></td></tr>
                        <tr><td>{{trans('file.Total Payment')}}:</td><td id="ps-total-payment" class="text-right"></td></tr>
                        @if(in_array("cash", $options))
                        <tr><td>{{trans('file.Cash Payment')}}:</td><td id="ps-cash-payment" class="text-right"></td></tr>
                        <tr><td>Pembayaran Cash Piutang:</td><td id="ps-due-payment" class="text-right"></td></tr>
                        @endif
                        <tr><td>{{trans('file.Total Sale Return')}}:</td><td id="ps-total-return" class="text-right"></td></tr>
                        <tr><td>{{trans('file.Total Expense')}}:</td><td id="ps-total-expense" class="text-right"></td></tr>
                        <tr><td><strong>Kas Sistem:</strong></td><td id="ps-expected-cash" class="text-right"></td></tr>
                        @endif
                    </tbody>
                </table>
                <form action="{{route('cashRegister.close')}}" method="POST">
                    @csrf
                    <input type="hidden" name="cash_register_id">
                    <div class="form-group">
                        <label>{{ \Auth::user()->role_id <= 2 ? 'Uang Fisik Dihitung *' : 'Uang Fisik Dihitung / Setor Kasir *' }}</label>
                        <input type="number" step="any" name="closing_cash_amount" required class="form-control">
                    </div>
                    @if(\Auth::user()->role_id <= 2)
                    <div class="form-group">
                        <label>Setor Kasir</label>
                        <input type="number" step="any" name="deposit_amount" class="form-control">
                    </div>
                    @endif
                    <div class="form-group">
                        <label>Catatan Tutup Kasir</label>
                        <textarea name="closing_note" class="form-control" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-success">Tutup &amp; Setor Kasir</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ============ modal load void ============ -->
<div class="modal fade" id="ps-void-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header ps-green" style="color:#fff">
                <h5 class="modal-title">Transaksi Void (Tergantung)</h5>
                <button type="button" class="close" data-dismiss="modal" style="color:#fff"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div id="ps-void-list"></div>
            </div>
        </div>
    </div>
</div>

</div><!-- /.container-fluid -->
</section>
@endsection

@push('scripts')
<script type="text/javascript">
/* =====================================================================
   POS SIMPLE
   Halaman ini berdiri sendiri; POS lama (backend/sale/pos.blade.php)
   tidak dipakai dan tidak diubah. Penyimpanan transaksi tetap lewat
   SaleController@store (route sales.store) dengan flag pos=1.
   ===================================================================== */
(function($) {
    'use strict';

    var DEC = {{ (int) $general_setting->decimal }};
    var URL_SEARCH = "{{ route('sale.posSimple.search') }}";
    var VOID_KEY = 'pos_simple_void_v1';

    /* ---------------- util angka ---------------- */
    function fmt(n) {
        n = parseFloat(n);
        if (isNaN(n)) n = 0;
        var parts = n.toFixed(DEC).split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return parts.join(DEC > 0 ? ',' : '');
    }
    /* "12.500,50" / "12500" -> 12500.5 */
    function num(v) {
        if (v === null || v === undefined) return 0;
        if (typeof v === 'number') return v;
        var s = ('' + v).replace(/[^\d.,\-]/g, '');
        if (s === '' || s === '-') return 0;
        if (s.indexOf(',') > -1) s = s.replace(/\./g, '').replace(',', '.');
        else {
            var p = s.split('.');
            if (p.length > 1) {
                var last = p.pop();
                s = (p.length > 1 || last.length === 3) ? p.join('') + last : p.join('') + '.' + last;
            }
        }
        var f = parseFloat(s);
        return isNaN(f) ? 0 : f;
    }

    /* front.js menjalankan $('select').selectpicker() untuk seluruh halaman, jadi
       setiap kali nilai select diubah lewat JS labelnya harus disegarkan. */
    function refreshPicker($el) {
        try { if ($el.data('selectpicker')) $el.selectpicker('refresh'); } catch (e) { }
    }

    /* ---------------- state ---------------- */
    var cart = [];        /* {id, code, name, unit, gross, disc, qty, stock} */
    var sel = -1;         /* baris terpilih */
    var busy = false;     /* cegah submit ganda */

    var $body = $('#ps-body');
    var $search = $('#ps-search');
    var $suggest = $('#ps-suggest');

    /* ---------------- render ---------------- */
    function rowNett(it) { return Math.max(0, num(it.gross) - num(it.disc)); }
    function rowTotal(it) { return rowNett(it) * num(it.qty); }

    function grandTotal() {
        var t = 0;
        for (var i = 0; i < cart.length; i++) t += rowTotal(cart[i]);
        return t;
    }

    /* sisa stok baris ini dalam satuan yang dipilih (stok disimpan dalam satuan dasar) */
    function stockInUnit(it) {
        var f = num(it.stock_factor) || 1;
        return num(it.stock) / f;
    }
    function isOverStock(it) {
        return num(it.qty) > stockInUnit(it) + 0.0001;
    }

    function render() {
        if (!cart.length) {
            $body.html('<tr class="ps-empty-row"><td colspan="8">Belum ada barang. Tekan <b>F2</b> lalu scan / ketik nama barang.</td></tr>');
        } else {
            var html = '';
            for (var i = 0; i < cart.length; i++) {
                var it = cart[i];
                var unitLabel = (it.unit && it.unit !== 'n/a') ? it.unit : '';
                html += '<tr data-i="' + i + '"' + (i === sel ? ' class="ps-active"' : '') + '>' +
                    '<td>' + it.code + '</td>' +
                    '<td><strong>' + it.name + '</strong>' +
                        '<div style="font-size:11px" class="' + (isOverStock(it) ? 'text-danger font-weight-bold' : 'text-muted') + '">' +
                        (unitLabel ? '<span class="badge badge-light border">' + unitLabel + '</span> ' : '') +
                        'sisa ' + fmt(stockInUnit(it)) + '</div></td>' +
                    '<td class="ps-qty"><input type="text" class="ps-in ps-f-qty" value="' + it.qty + '"></td>' +
                    '<td class="ps-gross"><input type="text" class="ps-in ps-f-gross" value="' + fmt(it.gross) + '"></td>' +
                    '<td class="ps-disc"><input type="text" class="ps-in ps-f-disc" value="' + fmt(it.disc) + '"></td>' +
                    '<td class="num">' + fmt(rowNett(it)) + '</td>' +
                    '<td class="num"><strong>' + fmt(rowTotal(it)) + '</strong></td>' +
                    '<td class="text-center"><button type="button" class="btn btn-sm btn-danger ps-del"><i class="dripicons-cross"></i></button></td>' +
                    '</tr>';
            }
            $body.html(html);
        }
        var qty = 0, subtotal = 0, discount = 0;
        for (var j = 0; j < cart.length; j++) {
            qty += num(cart[j].qty);
            subtotal += num(cart[j].gross) * num(cart[j].qty);
            discount += num(cart[j].disc) * num(cart[j].qty);
        }
        $('#ps-item-count').text(cart.length);
        $('#ps-qty-count').text(fmt(qty));
        $('#ps-subtotal').text(fmt(subtotal));
        $('#ps-discount').text(discount > 0 ? '-' + fmt(discount) : fmt(0));
        $('#ps-grand').text(fmt(grandTotal()));
        saveLocal();
    }

    function selectRow(i) {
        if (!cart.length) { sel = -1; return; }
        sel = Math.max(0, Math.min(i, cart.length - 1));
        $body.find('tr').removeClass('ps-active');
        var $tr = $body.find('tr[data-i="' + sel + '"]').addClass('ps-active');
        if ($tr.length && $tr.get(0).scrollIntoView) {
            try { $tr.get(0).scrollIntoView({ block: 'nearest' }); } catch (e) { }
        }
    }

    /* ---------------- alur tambah barang: satuan -> qty -> Enter ----------------
       Scan / pilih barang tidak langsung masuk keranjang. Modal kecil muncul,
       kasir pilih satuan (pcs / pak / dus), tekan Enter, isi jumlah, Enter lagi;
       barang masuk keranjang dan fokus balik ke kolom scan. */
    var addCtx = null;   /* {product, editIndex} */
    var unitIndex = 0;

    var $addModal = $('#ps-add-modal');
    var $unitList = $('#ps-unit-list');
    var $addQty = $addModal.find('input[name="ps_qty"]');

    /* harga bertingkat mode "Jumlah": ambil tier tertinggi yang qty-nya terpenuhi */
    function tierPrice(unit, qty) {
        if (!unit || !unit.tiers || !unit.tiers.length) return null;
        var price = null;
        for (var i = 0; i < unit.tiers.length; i++) {
            if (qty >= num(unit.tiers[i].min_qty)) price = num(unit.tiers[i].price);
        }
        return price;
    }

    function currentAddUnit() {
        if (!addCtx) return null;
        return addCtx.product.units[unitIndex] || null;
    }

    function setUnit(i) {
        if (!addCtx) return;
        var n = addCtx.product.units.length;
        if (!n) return;
        unitIndex = Math.max(0, Math.min(i, n - 1));
        $unitList.find('.ps-unit-row').removeClass('active')
            .eq(unitIndex).addClass('active');
        var el = $unitList.find('.ps-unit-row').get(unitIndex);
        if (el && el.scrollIntoView) { try { el.scrollIntoView({ block: 'nearest' }); } catch (e) { } }
        refreshAddModal();
    }

    function refreshAddModal() {
        var unit = currentAddUnit();
        if (!unit) return;
        var qty = num($addQty.val());
        var price = tierPrice(unit, qty);
        if (price === null) price = num(unit.price);
        var factor = num(unit.stock_factor) || 1;
        var sisa = num(addCtx.product.stock) / factor;

        $('#ps-add-price').text(fmt(price) + ' / ' + unit.name);
        $('#ps-add-stock').text(fmt(sisa) + ' ' + (unit.name === 'n/a' ? '' : unit.name));
        $('#ps-add-total').text(fmt(price * qty));
        $('#ps-add-stock').closest('div').toggleClass('text-danger', qty > sisa + 0.0001);
    }

    function openAddModal(product, editIndex) {
        addCtx = { product: product, editIndex: (typeof editIndex === 'number' ? editIndex : -1) };

        $('#ps-add-title').text(addCtx.editIndex > -1 ? 'Ubah Barang' : 'Tambah Barang');
        $('#ps-add-name').text(product.name);
        $('#ps-add-code').text(product.code);

        var html = '';
        for (var i = 0; i < product.units.length; i++) {
            html += '<div class="ps-unit-row" data-i="' + i + '">' +
                '<span><span class="no">' + (i + 1) + '</span>' + product.units[i].name + '</span>' +
                '<span class="pr">' + fmt(product.units[i].price) + '</span>' +
                '</div>';
        }
        $unitList.html(html);

        var startUnit = 0, startQty = 1;
        if (addCtx.editIndex > -1) {
            var it = cart[addCtx.editIndex];
            startQty = num(it.qty);
            for (var j = 0; j < product.units.length; j++) {
                if (product.units[j].name === it.unit) { startUnit = j; break; }
            }
        }
        unitIndex = startUnit;
        $addQty.val(startQty);
        setUnit(startUnit);
        $addModal.modal('show');
    }

    /* satuan cuma satu -> lompat langsung ke kolom jumlah */
    $addModal.on('shown.bs.modal', function () {
        if (addCtx && addCtx.product.units.length > 1) $unitList.focus();
        else $addQty.focus().select();
    });

    $unitList.on('keydown', function (e) {
        if (e.key === 'ArrowDown') { e.preventDefault(); setUnit(unitIndex + 1); return; }
        if (e.key === 'ArrowUp') { e.preventDefault(); setUnit(unitIndex - 1); return; }
        if (e.key === 'Enter') { e.preventDefault(); e.stopPropagation(); $addQty.focus().select(); return; }
        if (/^[1-9]$/.test(e.key)) { e.preventDefault(); setUnit(parseInt(e.key, 10) - 1); return; }
    });

    $unitList.on('click', '.ps-unit-row', function () {
        setUnit(parseInt($(this).data('i'), 10));
        $addQty.focus().select();
    });

    $addModal.on('hidden.bs.modal', function () {
        addCtx = null;
        setTimeout(focusSearch, 50);
    });

    $addQty.on('input', refreshAddModal);

    /* Enter di jumlah -> simpan; panah tetap bisa ganti satuan tanpa pindah fokus */
    $addQty.on('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); e.stopPropagation(); commitAdd(); return; }
        if (e.key === 'ArrowDown') { e.preventDefault(); setUnit(unitIndex + 1); return; }
        if (e.key === 'ArrowUp') { e.preventDefault(); setUnit(unitIndex - 1); return; }
    });
    $('#ps-add-submit').on('click', commitAdd);

    function commitAdd() {
        if (!addCtx) return;
        var unit = currentAddUnit();
        if (!unit) return;
        var qty = num($addQty.val());
        if (qty <= 0) { alert('Jumlah harus lebih dari 0.'); $addQty.focus().select(); return; }

        var price = tierPrice(unit, qty);
        if (price === null) price = num(unit.price);

        var p = addCtx.product;
        var idx = addCtx.editIndex;

        if (idx > -1) {
            cart[idx].unit = unit.name;
            cart[idx].stock_factor = num(unit.stock_factor) || 1;
            cart[idx].gross = price;
            cart[idx].qty = qty;
        } else {
            /* barang + satuan yang sama -> jumlahnya ditambahkan, bukan baris baru */
            idx = -1;
            for (var i = 0; i < cart.length; i++) {
                if (cart[i].code === p.code && cart[i].unit === unit.name) { idx = i; break; }
            }
            if (idx > -1) {
                cart[idx].qty = num(cart[idx].qty) + qty;
                cart[idx].units = p.units;
                var re = tierPrice(unit, cart[idx].qty);
                cart[idx].gross = (re !== null) ? re : num(unit.price);
            } else {
                cart.unshift({
                    id: p.id, code: p.code, name: p.name,
                    unit: unit.name, stock_factor: num(unit.stock_factor) || 1,
                    units: p.units, stock: num(p.stock),
                    gross: price, disc: 0, qty: qty
                });
                idx = 0;
            }
        }

        $addModal.modal('hide');
        render();
        selectRow(idx);
    }

    /* Enter di baris keranjang -> buka lagi modal satuan & jumlah untuk baris itu */
    function editSelectedRow() {
        if (sel < 0 || !cart[sel]) return;
        var it = cart[sel];
        if (it.units && it.units.length) {
            openAddModal({
                id: it.id, code: it.code, name: it.name,
                units: it.units, stock: it.stock
            }, sel);
            return;
        }
        /* keranjang hasil localStorage lama belum menyimpan daftar satuan */
        var keep = sel;
        doSearch(it.code, true, function (list) {
            if (list.length === 1) openAddModal(list[0], keep);
            else $body.find('tr[data-i="' + keep + '"] .ps-f-qty').focus().select();
        });
    }

    /* Satuan yang sedang dipakai baris keranjang, lengkap dengan daftar tier-nya.
       Dipakai supaya perubahan qty di keranjang ikut memakai harga bertingkat,
       bukan cuma saat barang pertama kali dimasukkan lewat modal. */
    function unitOfRow(it) {
        if (!it || !it.units || !it.units.length) return null;
        for (var i = 0; i < it.units.length; i++) {
            if (it.units[i].name === it.unit) return it.units[i];
        }
        return null;
    }

    /* Hitung ulang harga baris mengikuti qty terbaru (harga bertingkat mode "Jumlah"). */
    function applyTierPrice(it) {
        var unit = unitOfRow(it);
        if (!unit) return;
        var price = tierPrice(unit, num(it.qty));
        if (price === null) price = num(unit.price);
        it.gross = price;
        if (num(it.disc) > num(it.gross)) it.disc = num(it.gross);
    }

    function changeQty(delta) {
        if (sel < 0 || !cart[sel]) return;
        var q = num(cart[sel].qty) + delta;
        if (q < 1) q = 1;
        cart[sel].qty = q;
        applyTierPrice(cart[sel]);
        var keep = sel; render(); selectRow(keep);
    }

    function removeRow(i) {
        if (i < 0 || !cart[i]) return;
        cart.splice(i, 1);
        var keep = Math.min(i, cart.length - 1);
        render(); selectRow(keep);
    }

    function clearCart(ask) {
        if (!cart.length) return;
        if (ask && !confirm('Kosongkan seluruh keranjang?')) return;
        cart = []; sel = -1; render(); focusSearch();
    }

    /* ---------------- simpan sementara di browser ---------------- */
    function saveLocal() {
        try { localStorage.setItem('pos_simple_cart', JSON.stringify(cart)); } catch (e) { }
    }
    function loadLocal() {
        try {
            var raw = localStorage.getItem('pos_simple_cart');
            if (raw) cart = JSON.parse(raw) || [];
        } catch (e) { cart = []; }
    }

    /* ---------------- pencarian produk ---------------- */
    var timer = null, sgIndex = -1, sgData = [];

    function focusSearch() {
        sel = -1;
        $body.find('tr').removeClass('ps-active');
        $search.focus().select();
    }

    function hideSuggest() { $suggest.hide(); sgIndex = -1; sgData = []; }

    function drawSuggest(list) {
        sgData = list; sgIndex = list.length ? 0 : -1;
        if (!list.length) {
            $suggest.html('<div class="ps-empty">Barang tidak ditemukan.</div>').show();
            return;
        }
        var html = '';
        for (var i = 0; i < list.length; i++) {
            html += '<div class="ps-sg' + (i === 0 ? ' active' : '') + '" data-i="' + i + '">' +
                '<span class="c">' + list[i].code + '</span>' +
                '<span class="n">' + list[i].name + '</span>' +
                '<span class="s">' + fmt(list[i].stock) + '</span>' +
                '<span class="p">' + fmt(list[i].price) + '</span>' +
                '</div>';
        }
        $suggest.html(html).show();
    }

    function moveSuggest(d) {
        if (!sgData.length) return;
        sgIndex = Math.max(0, Math.min(sgIndex + d, sgData.length - 1));
        $suggest.find('.ps-sg').removeClass('active')
            .filter('[data-i="' + sgIndex + '"]').addClass('active');
    }

    function doSearch(q, exact, cb) {
        $.get(URL_SEARCH, {
            q: q,
            warehouse_id: $('#ps-warehouse').val(),
            customer_id: $('#ps-customer').val(),
            level: $('#ps-price-level').val(),
            exact: exact ? 1 : 0
        }, function (data) { cb(data || []); }).fail(function () { cb([]); });
    }

    /* barang dipilih dari hasil cari -> lanjut ke pemilihan satuan & jumlah */
    function pickProduct(p) {
        $search.val('');
        hideSuggest();
        openAddModal(p);
    }

    $search.on('input', function () {
        var q = $.trim($(this).val());
        clearTimeout(timer);
        if (q.length < 2) { hideSuggest(); return; }
        timer = setTimeout(function () {
            doSearch(q, false, drawSuggest);
        }, 250);
    });

    $search.on('keydown', function (e) {
        if (e.key === 'ArrowDown' && !e.ctrlKey) { e.preventDefault(); moveSuggest(1); return; }
        if (e.key === 'ArrowUp' && !e.ctrlKey) { e.preventDefault(); moveSuggest(-1); return; }
        if (e.key === 'Enter') {
            e.preventDefault();
            var q = $.trim($(this).val());
            if (!q) return;
            if ($suggest.is(':visible') && sgIndex > -1 && sgData[sgIndex]) {
                pickProduct(sgData[sgIndex]);
                return;
            }
            /* tidak ada dropdown -> anggap barcode, cari kode persis */
            clearTimeout(timer);
            doSearch(q, true, function (list) {
                if (list.length === 1) pickProduct(list[0]);
                else doSearch(q, false, drawSuggest);
            });
        }
    });

    $suggest.on('click', '.ps-sg', function () {
        var i = parseInt($(this).data('i'), 10);
        if (sgData[i]) pickProduct(sgData[i]);
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('.ps-search-box').length) hideSuggest();
    });

    /* Ganti level harga / gudang tidak boleh mencampur harga & stok dua sumber,
       jadi keranjang dikosongkan dulu. Kalau kasir batal, pilihan dikembalikan. */
    $('#ps-price-level, #ps-warehouse').each(function () {
        $(this).data('prev', $(this).val());
    }).on('change', function () {
        if (!cart.length) { $(this).data('prev', $(this).val()); return; }
        if (!confirm('Mengganti pilihan ini akan mengosongkan keranjang. Lanjutkan?')) {
            $(this).val($(this).data('prev'));
            return;
        }
        $(this).data('prev', $(this).val());
        clearCart(false);
    });

    /* ganti gudang -> kasir gudang itu harus dicek ulang, promo ikut disegarkan */
    $('#ps-warehouse').on('change', function () {
        checkCashRegister($(this).val());
        loadPromos();
    });

    /* ---------------- edit inline ---------------- */
    $body.on('change blur', '.ps-f-qty, .ps-f-gross, .ps-f-disc', function () {
        var i = parseInt($(this).closest('tr').data('i'), 10);
        if (isNaN(i) || !cart[i]) return;
        var v = num($(this).val());
        if ($(this).hasClass('ps-f-qty')) {
            cart[i].qty = v > 0 ? v : 1;
            /* qty diketik manual -> harga ikut naik/turun sesuai tier harga banyak */
            applyTierPrice(cart[i]);
        }
        else if ($(this).hasClass('ps-f-gross')) cart[i].gross = v;
        else cart[i].disc = (v < 0 ? 0 : v);
        if (cart[i].disc > cart[i].gross) cart[i].disc = cart[i].gross;
        render(); selectRow(i);
    });

    $body.on('keydown', '.ps-in', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); $(this).blur(); focusSearch(); }
    });

    $body.on('click', '.ps-del', function (e) {
        e.stopPropagation();
        removeRow(parseInt($(this).closest('tr').data('i'), 10));
    });

    $body.on('click', 'tr', function () {
        var i = parseInt($(this).data('i'), 10);
        if (!isNaN(i)) selectRow(i);
    });

    /* ---------------- void (parkir transaksi di browser) ---------------- */
    function voidList() {
        try { return JSON.parse(localStorage.getItem(VOID_KEY)) || []; } catch (e) { return []; }
    }
    function voidSave(list) {
        try { localStorage.setItem(VOID_KEY, JSON.stringify(list)); } catch (e) { }
    }
    function createVoid() {
        if (!cart.length) { alert('Keranjang masih kosong.'); return; }
        var label = prompt('Nama / catatan transaksi void:', 'Void ' + new Date().toLocaleTimeString('id-ID'));
        if (label === null) return;
        var list = voidList();
        list.unshift({ t: Date.now(), label: label || 'Void', total: grandTotal(), items: cart });
        if (list.length > 30) list = list.slice(0, 30);
        voidSave(list);
        clearCart(false);
        alert('Transaksi disimpan sebagai void.');
    }
    function openVoid() {
        var list = voidList();
        var html = '';
        if (!list.length) {
            html = '<p class="text-muted mb-0">Belum ada transaksi void.</p>';
        } else {
            html = '<table class="table table-sm mb-0"><thead><tr><th>Waktu</th><th>Catatan</th><th class="text-right">Item</th><th class="text-right">Total</th><th></th></tr></thead><tbody>';
            for (var i = 0; i < list.length; i++) {
                html += '<tr><td>' + new Date(list[i].t).toLocaleString('id-ID') + '</td>' +
                    '<td>' + list[i].label + '</td>' +
                    '<td class="text-right">' + list[i].items.length + '</td>' +
                    '<td class="text-right">' + fmt(list[i].total) + '</td>' +
                    '<td class="text-right">' +
                        '<button type="button" class="btn btn-sm btn-success ps-void-load" data-i="' + i + '">Muat</button> ' +
                        '<button type="button" class="btn btn-sm btn-danger ps-void-del" data-i="' + i + '"><i class="dripicons-cross"></i></button>' +
                    '</td></tr>';
            }
            html += '</tbody></table>';
        }
        $('#ps-void-list').html(html);
        $('#ps-void-modal').modal('show');
    }
    $(document).on('click', '.ps-void-load', function () {
        var list = voidList(), i = parseInt($(this).data('i'), 10);
        if (!list[i]) return;
        if (cart.length && !confirm('Keranjang sekarang akan diganti. Lanjutkan?')) return;
        cart = list[i].items || [];
        list.splice(i, 1); voidSave(list);
        $('#ps-void-modal').modal('hide');
        render(); selectRow(0);
    });
    $(document).on('click', '.ps-void-del', function () {
        var list = voidList(), i = parseInt($(this).data('i'), 10);
        list.splice(i, 1); voidSave(list);
        openVoid();
    });

    /* ---------------- simpan transaksi ---------------- */
    function buildForm(paidById, payingAmount, paidAmount) {
        var h = '';
        function add(name, value) {
            h += '<input type="hidden" name="' + name + '" value="' + ('' + value).replace(/"/g, '&quot;') + '">';
        }
        var totalQty = 0, totalPrice = 0, totalDisc = 0;

        for (var i = 0; i < cart.length; i++) {
            var it = cart[i];
            var nett = rowNett(it), q = num(it.qty), sub = nett * q;
            totalQty += q; totalPrice += sub; totalDisc += num(it.disc) * q;

            add('product_id[]', it.id);
            add('product_code[]', it.code);
            add('product_batch_id[]', '');
            add('imei_number[]', '');
            add('qty[]', q);
            add('sale_unit[]', it.unit);
            add('net_unit_price[]', nett.toFixed(2));
            add('discount[]', (num(it.disc) * q).toFixed(2));
            add('tax_rate[]', '0');
            add('tax[]', '0.00');
            add('subtotal[]', sub.toFixed(2));
        }

        add('warehouse_id', $('#ps-warehouse').val());
        add('customer_id', $('#ps-customer').val());
        add('item', cart.length);
        add('total_qty', totalQty);
        add('total_discount', totalDisc.toFixed(2));
        add('total_price', totalPrice.toFixed(2));
        add('grand_total', totalPrice.toFixed(2));
        add('paid_by_id', paidById);
        add('paying_amount', payingAmount.toFixed(2));
        add('paid_amount', paidAmount.toFixed(2));

        $('#ps-form-dynamic').html(h);
    }

    function validate() {
        if (busy) return false;
        if (!cart.length) { alert('Keranjang masih kosong.'); return false; }
        if (!$('#ps-warehouse').val()) { alert('Gudang belum dipilih.'); return false; }
        if (!registerReady) {
            alert('Kasir belum dibuka. Isi Modal Awal Kasir terlebih dahulu.');
            checkCashRegister($('#ps-warehouse').val());
            return false;
        }
        if (!$('#ps-customer').val()) { alert('Pelanggan belum dipilih.'); return false; }

        /* peringatan stok minus (tidak memblokir, sesuai perilaku POS lama) */
        var over = [];
        for (var i = 0; i < cart.length; i++) {
            if (isOverStock(cart[i])) over.push(cart[i].name + ' (' + cart[i].unit + ')');
        }
        if (over.length && !confirm('Qty melebihi stok gudang untuk:\n- ' + over.join('\n- ') + '\n\nTetap lanjutkan?')) {
            return false;
        }
        return true;
    }

    function submitSale(paidById, payingAmount, paidAmount) {
        busy = true;
        $('.ps-act, #ps-pay-submit').prop('disabled', true);
        buildForm(paidById, payingAmount, paidAmount);
        try { localStorage.removeItem('pos_simple_cart'); } catch (e) { }
        $('#ps-form').get(0).submit();
    }

    /* --- bayar tunai --- */
    function openPay() {
        if (!validate()) return;
        var total = grandTotal();
        $('#ps-pay-total').text(fmt(total));
        $('#ps-pay-modal input[name="ps_received"]').val(fmt(total));
        updateChange();
        $('#ps-pay-modal').modal('show');
    }

    function updateChange() {
        var total = grandTotal();
        var received = num($('#ps-pay-modal input[name="ps_received"]').val());
        var change = received - total;
        $('#ps-pay-change').text(fmt(change))
            .toggleClass('ps-change-neg', change < 0)
            .toggleClass('ps-change-pos', change >= 0);
    }

    $('#ps-pay-modal').on('input', 'input[name="ps_received"]', updateChange);

    $('#ps-pay-modal').on('click', '.ps-qc', function () {
        var $in = $('#ps-pay-modal input[name="ps_received"]');
        var v = $(this).data('qc');
        if (v === 'pas') $in.val(fmt(grandTotal()));
        else if (num(v) === 0) $in.val('');
        else $in.val(fmt(num($in.val()) + num(v)));
        updateChange();
    });

    $('#ps-pay-modal').on('shown.bs.modal', function () {
        $('#ps-pay-modal input[name="ps_received"]').focus().select();
    });

    $('#ps-pay-submit').on('click', function () {
        var total = grandTotal();
        var received = num($('#ps-pay-modal input[name="ps_received"]').val());
        if (received < total) { alert('Uang diterima kurang dari total tagihan.'); return; }
        submitSale(1, received, total);
    });

    /* --- bayar piutang --- */
    function payCredit() {
        if (!validate()) return;
        if (!confirm('Simpan sebagai PIUTANG (belum dibayar) sebesar ' + fmt(grandTotal()) + ' ?')) return;
        submitSale(8, 0, 0);
    }

    /* ---------------- tombol ---------------- */
    $('#ps-btn-save').on('click', openPay);
    $('#ps-btn-clear').on('click', function () { clearCart(true); });
    $('#ps-btn-credit').on('click', payCredit);
    $('#ps-btn-void').on('click', createVoid);
    $('#ps-btn-load').on('click', openVoid);

    /* ---------------- shortcut keyboard ---------------- */
    function isTyping(el) {
        if (!el) return false;
        var t = (el.tagName || '').toUpperCase();
        return t === 'INPUT' || t === 'TEXTAREA' || t === 'SELECT' || el.isContentEditable === true;
    }
    function modalOpen() { return $('.modal.show, .modal.in').length > 0; }

    $(document).on('keydown.posSimple', function (e) {
        var key = e.key;
        if (!key) return;

        /* --- saat modal terbuka --- */
        if (modalOpen()) {
            if ($('#ps-pay-modal').hasClass('show') || $('#ps-pay-modal').hasClass('in')) {
                if (key === 'F4') {
                    e.preventDefault();
                    $('#ps-pay-modal input[name="ps_received"]').val(fmt(grandTotal()));
                    updateChange();
                    return;
                }
                if (key === 'Enter') { e.preventDefault(); $('#ps-pay-submit').get(0).click(); return; }
            }
            return;
        }

        /* --- Ctrl + panah: pilih baris tanpa keluar dari kolom cari --- */
        if (e.ctrlKey && !e.altKey && (key === 'ArrowUp' || key === 'ArrowDown')) {
            e.preventDefault();
            selectRow(sel < 0 ? 0 : sel + (key === 'ArrowDown' ? 1 : -1));
            return;
        }

        /* --- tombol aksi --- */
        var done = true;
        if (!e.ctrlKey && !e.altKey && !e.metaKey && key === 'F2') focusSearch();
        else if (!e.ctrlKey && !e.altKey && !e.metaKey && key === 'F4') openPay();
        else if (e.altKey && !e.ctrlKey) {
            var k = (key + '').toLowerCase();
            if (k === 's') focusSearch();
            else if (k === 'h') clearCart(true);
            else if (k === 'b') payCredit();
            else if (k === 'v') createVoid();
            else if (k === 'l') openVoid();
            else done = false;
        }
        else done = false;
        if (done) { e.preventDefault(); e.stopPropagation(); return; }

        /* --- Esc: keluar dari kolom cari --- */
        if (key === 'Escape') {
            if ($suggest.is(':visible')) { hideSuggest(); return; }
            if (e.target && e.target.id === 'ps-search') {
                $search.blur();
                if (cart.length && sel < 0) selectRow(0);
                return;
            }
        }

        if (isTyping(e.target)) return;

        /* --- navigasi keranjang --- */
        if (key === 'ArrowUp') { e.preventDefault(); selectRow(sel < 0 ? cart.length - 1 : sel - 1); return; }
        if (key === 'ArrowDown') { e.preventDefault(); selectRow(sel < 0 ? 0 : sel + 1); return; }
        if (key === '+' || key === '=' || key === 'Add') { e.preventDefault(); changeQty(1); return; }
        if (key === '-' || key === '_' || key === 'Subtract') { e.preventDefault(); changeQty(-1); return; }
        if (key === 'Delete') { e.preventDefault(); removeRow(sel); return; }
        if (key === 'Enter') {
            e.preventDefault();
            editSelectedRow();
            return;
        }
        /* ketikan biasa / barcode scanner -> lempar ke kolom cari */
        if (key.length === 1 && !e.ctrlKey && !e.altKey && !e.metaKey) {
            if (document.activeElement !== $search.get(0)) $search.focus();
        }
    });

    /* ---------------- jam ---------------- */
    function tick() {
        var d = new Date();
        var s = d.toLocaleString('id-ID', {
            day: '2-digit', month: 'short', year: 'numeric',
            hour: '2-digit', minute: '2-digit', second: '2-digit'
        });
        $('#ps-clock').text(s);
        $('.ps-clock-top').text(s);
    }
    setInterval(tick, 1000); tick();

    /* ---------------- kasir (cash register) ----------------
       Perilaku sama dengan POS lama: kalau belum ada kasir yang dibuka untuk
       gudang ini, modal "Modal Awal Kasir" dipaksa muncul dan tidak bisa
       ditutup sampai kasir dibuka. */
    var URL_CR_CHECK = "{{ url('cash-register/check-availability') }}";
    var URL_CR_DETAIL = "{{ url('cash-register/showDetails') }}";
    var URL_CR_INDEX = "{{ route('cashRegister.index') }}";
    var registerReady = false;

    function checkCashRegister(warehouseId) {
        if (!warehouseId) {
            $('#ps-register-btn').addClass('d-none');
            alert('Gudang/toko POS belum ditentukan. Hubungi admin untuk menetapkan gudang bertipe Toko.');
            return;
        }
        $.ajax({
            url: URL_CR_CHECK + '/' + warehouseId,
            type: 'GET',
            success: function (data) {
                /* kasir sedang terbuka, tapi di gudang lain -> ikuti gudang kasir */
                if (data.status === 'open_other_warehouse') {
                    $('#ps-warehouse').val(data.warehouse_id).data('prev', data.warehouse_id);
                    refreshPicker($('#ps-warehouse'));
                    $('#ps-register-btn').removeClass('d-none');
                    registerReady = true;
                    return;
                }
                if (data.status === 'available') {
                    registerReady = false;
                    $('#ps-register-btn').addClass('d-none');
                    $('#ps-register-modal select[name="warehouse_id"]').val(warehouseId);
                    $('#ps-register-modal').modal({ backdrop: 'static', keyboard: false, show: true });
                } else if (data.status === 'open') {
                    registerReady = true;
                    $('#ps-register-btn').removeClass('d-none');
                } else {
                    registerReady = false;
                    $('#ps-register-btn').addClass('d-none');
                    alert(data.message || 'Kasir belum bisa dibuka. Selesaikan setoran kasir terlebih dahulu.');
                    window.location.href = URL_CR_INDEX;
                }
            }
        });
    }

    $('#ps-register-btn').on('click', function (e) {
        e.preventDefault();
        var $m = $('#ps-register-details-modal');
        $.ajax({
            url: URL_CR_DETAIL + '/' + $('#ps-warehouse').val(),
            type: 'GET',
            success: function (data) {
                $('#ps-cash-in-hand').text(fmt(data['cash_in_hand']));
                $m.find('input[name="cash_register_id"]').val(data['id']);
                /* jangan prefill: setoran harus hasil hitungan uang fisik */
                $m.find('input[name="closing_cash_amount"]').val('');
                $m.find('input[name="deposit_amount"]').val('');
                if (data['restricted']) return;
                $('#ps-total-sale').text(fmt(data['total_sale_amount']));
                $('#ps-total-payment').text(fmt(data['total_payment']));
                $('#ps-cash-payment').text(fmt(data['cash_payment']));
                $('#ps-due-payment').text(fmt(data['due_payment']));
                $('#ps-total-return').text(fmt(data['total_sale_return']));
                $('#ps-total-expense').text(fmt(data['total_expense']));
                $('#ps-expected-cash').text(fmt(data['expected_cash_amount']));
            }
        });
        $m.modal('show');
    });

    /* ---------------- promosi hari ini ---------------- */
    var URL_PROMOS = "{{ route('sale.posSimple.promos') }}";

    function loadPromos() {
        $.get(URL_PROMOS, { warehouse_id: $('#ps-warehouse').val() }, function (list) {
            list = list || [];
            if (!list.length) {
                $('#ps-promo-list').html('<div class="ps-promo-empty">Tidak ada barang promo hari ini.</div>');
                return;
            }
            var html = '';
            for (var i = 0; i < list.length; i++) {
                var p = list[i];
                html += '<div class="ps-promo">' +
                    '<span class="nm">' + p.name + '<small>' + p.code +
                        (p.last_date ? ' &middot; s/d ' + p.last_date : '') + '</small></span>' +
                    '<span class="pr"><span class="old">' + fmt(p.price) + '</span>' +
                        '<span class="new">' + fmt(p.promo) + '</span></span>' +
                    '</div>';
            }
            $('#ps-promo-list').html(html);
        }).fail(function () {
            $('#ps-promo-list').html('<div class="ps-promo-empty">Daftar promo gagal dimuat.</div>');
        });
    }

    /* ---------------- init ---------------- */
    loadLocal();
    render();
    checkCashRegister($('#ps-warehouse').val());
    loadPromos();
    setTimeout(focusSearch, 300);

})(window.jQuery);
</script>
@endpush
