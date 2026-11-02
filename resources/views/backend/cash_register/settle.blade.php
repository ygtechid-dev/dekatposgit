@extends('backend.index')
@section('title', __('file.Cash Register Settlement'))

@section('content')
<section class="content">
  <div class="row">
    <div class="col-sm-12">
      <div class="box" style="margin-bottom: 20px;">
        <div class="box-header with-border" style="display:flex; justify-content:space-between; align-items:center;">
          <h3 class="box-title">{{ trans('file.Cash Register Settlement') }}</h3>
          <a href="{{ route('cashRegister.index') }}" class="btn btn-default"><i class="fa fa-arrow-left"></i> {{ trans('file.Back to Register List') }}</a>
        </div>
        <div class="box-body">
          @if(session('message'))
            <div class="alert alert-success alert-dismissible">{{ session('message') }}<button type="button" class="close" data-dismiss="alert">&times;</button></div>
          @endif
          @if(session('error'))
            <div class="alert alert-danger alert-dismissible">{{ session('error') }}<button type="button" class="close" data-dismiss="alert">&times;</button></div>
          @endif
          <p style="color:#666; font-size:13px; margin-bottom:12px;">
            Daftar setoran kasir yang ditutup dan menunggu / sudah diproses oleh keuangan.
            Setoran yang <strong>direject</strong> tidak akan masuk laporan laba-rugi.
            Periode yang dipakai = tanggal register dibuka, bukan tanggal approval.
          </p>

          <div class="table-responsive">
            <table id="cash-register-settle-table" class="table table-bordered table-hover">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Kasir</th>
                  <th>Gudang</th>
                  <th>Dibuka</th>
                  <th>Ditutup</th>
                  <th class="text-right">Setoran</th>
                  <th>Status</th>
                  <th>Di-approve oleh</th>
                  <th class="text-right">Aksi</th>
                </tr>
              </thead>
              <tbody>
                @if(count($rows))
                  @foreach($rows as $i => $r)
                    @php
                      $reg = $r['register'];
                      $statusText = $reg->settle_status == 1 ? 'Di-approve' : ($reg->settle_status == 2 ? 'Ditolak' : 'Menunggu');
                      $statusColor = $reg->settle_status == 1 ? 'bg-green' : ($reg->settle_status == 2 ? 'bg-red' : 'bg-yellow');
                    @endphp
                    <tr>
                      <td>{{ $i + 1 }}</td>
                      <td>{{ $reg->user ? $reg->user->name : '-' }}</td>
                      <td>{{ $reg->warehouse ? $reg->warehouse->name : '-' }}</td>
                      <td>{{ $reg->created_at ? $reg->created_at->format('d M Y H:i') : '-' }}</td>
                      <td>{{ $reg->closed_at ? $reg->closed_at->format('d M Y H:i') : '-' }}</td>
                      <td class="text-right">{{ config('currency') }} {{ number_format($r['total_cash'], config('decimal')) }}</td>
                      <td><span class="label label-{{ $statusColor }}">{{ $statusText }}</span></td>
                      <td>
                        @if($reg->approved_by)
                          {{ $reg->approved_by }}<br>
                          <small style="color:#888">{{ $reg->approved_at ? $reg->approved_at->format('d M Y H:i') : '' }}</small>
                        @else
                          <small style="color:#999">-</small>
                        @endif
                      </td>
                      <td class="text-right">
                        @if($reg->settle_status == 0)
                          <a href="{{ route('cashRegister.approve', $reg->id) }}" class="btn btn-sm btn-success"
                             onclick="return confirm('Approve setoran ini? Angka akan masuk laba-rugi periode register: {{ $reg->created_at->format('d M Y') }}')">
                            <i class="fa fa-check"></i> Approve
                          </a>
                          <a href="{{ route('cashRegister.reject', $reg->id) }}" class="btn btn-sm btn-danger"
                             onclick="return confirm('Reject setoran ini? Angka TIDAK akan masuk laba-rugi.')">
                            <i class="fa fa-times"></i> Reject
                          </a>
                        @else
                          <span class="text-muted">Sudah diproses</span>
                        @endif
                      </td>
                    </tr>
                  @endforeach
                @else
                  <tr><td colspan="9" class="text-center" style="padding:24px;">Belum ada setoran kasir.</td></tr>
                @endif
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script type="text/javascript">
  $('#cash-register-settle-table').DataTable({
    "order": [[0, "asc"]],
    'language': {
      "lengthMenu": '_MENU_ {{trans("file.records per page")}}',
      "info":      '<small>{{trans("file.Showing")}} _START_ - _END_ dari _TOTAL_ Data</small>',
      "search":    '{{trans("file.Search")}}',
      "paginate": { "previous": '<i class="dripicons-chevron-left"></i>', "next": '<i class="dripicons-chevron-right"></i>' },
      "sInfoEmpty": "Tidak ada data"
    }
  });
</script>
@endpush
