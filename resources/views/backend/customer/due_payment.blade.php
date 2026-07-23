@extends('backend.layout.main')
@section('content')
@if(session()->has('message'))
    <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('message') }}</div>
@endif
@if(session()->has('not_permitted'))
    <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
@endif

<section>
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <h4>Pembayaran Piutang</h4>
                <p class="text-muted mb-0">Daftar pelanggan yang masih punya sisa piutang (belum lunas) dari transaksi kredit.</p>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="piutang-table">
                        <thead>
                            <tr>
                                <th>Nama Pelanggan</th>
                                <th>No. HP</th>
                                <th>Sisa Piutang</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($lims_customer_due as $customer)
                            <tr>
                                <td>{{$customer->name}}</td>
                                <td>{{$customer->phone_number}}</td>
                                <td>Rp {{number_format($customer->total_due, 2, ',', '.')}}</td>
                                <td>
                                    <button type="button" class="btn btn-primary btn-sm bayar-piutang-btn"
                                        data-id="{{$customer->id}}"
                                        data-name="{{$customer->name}}"
                                        data-due="{{$customer->total_due}}">
                                        <i class="dripicons-wallet"></i> Bayar Piutang
                                    </button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center">Alhamdulillah, tidak ada pelanggan yang punya piutang saat ini.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<div id="bayarPiutangModal" tabindex="-1" role="dialog" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog">
      <div class="modal-content">
        {!! Form::open(['route' => 'customer.clearDue', 'method' => 'post']) !!}
        <div class="modal-header">
          <h5 class="modal-title">Bayar Piutang - <span id="piutang-customer-name"></span></h5>
          <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="customer_id" id="piutang-customer-id">

            <p>Sisa piutang saat ini: <strong id="piutang-total-due"></strong></p>

            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr>
                            <th>No. Invoice</th>
                            <th>Tanggal</th>
                            <th>Total</th>
                            <th>Sudah Dibayar</th>
                            <th>Sisa</th>
                        </tr>
                    </thead>
                    <tbody id="piutang-invoice-list">
                        <tr><td colspan="5" class="text-center">Memuat data invoice...</td></tr>
                    </tbody>
                </table>
            </div>
            <small class="text-muted d-block mb-3">Pembayaran akan otomatis dialokasikan ke invoice paling lama dulu (FIFO) sampai jumlah yang dibayar habis.</small>

            <div class="form-group">
                <label>Jumlah Bayar *</label>
                <input type="number" name="amount" step="any" min="0.01" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Catatan</label>
                <textarea name="note" rows="3" class="form-control"></textarea>
            </div>
            <input type="submit" value="Bayar" class="btn btn-primary" id="submit-button">
        </div>
        {!! Form::close() !!}
      </div>
    </div>
</div>

@endsection

@push('scripts')
<script type="text/javascript">
    var dueInvoicesBaseUrl = "{{url('customers/piutang')}}";

    function formatRupiahDisplay(number) {
        number = parseFloat(number) || 0;
        var parts = number.toFixed(2).split(".");
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        return 'Rp ' + parts.join(',');
    }

    $(document).on('click', '.bayar-piutang-btn', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        var due = $(this).data('due');

        $('#piutang-customer-id').val(id);
        $('#piutang-customer-name').text(name);
        $('#piutang-total-due').text(formatRupiahDisplay(due));
        $('input[name="amount"]').val('');
        $('textarea[name="note"]').val('');
        $('#piutang-invoice-list').html('<tr><td colspan="5" class="text-center">Memuat data invoice...</td></tr>');

        $.get(dueInvoicesBaseUrl + '/' + id + '/invoices', function(data) {
            if (!data.length) {
                $('#piutang-invoice-list').html('<tr><td colspan="5" class="text-center">Tidak ada invoice.</td></tr>');
                return;
            }
            var rows = '';
            $.each(data, function(i, inv) {
                rows += '<tr>' +
                    '<td>' + inv.reference_no + '</td>' +
                    '<td>' + inv.date + '</td>' +
                    '<td>' + formatRupiahDisplay(inv.grand_total) + '</td>' +
                    '<td>' + formatRupiahDisplay(inv.paid_amount) + '</td>' +
                    '<td>' + formatRupiahDisplay(inv.due) + '</td>' +
                    '</tr>';
            });
            $('#piutang-invoice-list').html(rows);
        }).fail(function() {
            $('#piutang-invoice-list').html('<tr><td colspan="5" class="text-center">Gagal memuat data invoice.</td></tr>');
        });

        $('#bayarPiutangModal').modal('show');
    });

    $(function() {
        if ($.fn.DataTable) {
            $('#piutang-table').DataTable({
                paging: true,
                searching: true,
                info: false,
                order: []
            });
        }
    });
</script>
@endpush
