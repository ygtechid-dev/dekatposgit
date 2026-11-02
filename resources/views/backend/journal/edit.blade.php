@extends('backend.layout.main') @section('content')
@if($errors->any())
<div class="alert alert-danger alert-dismissible text-center">
    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
</div>
@endif

<section>
    <div class="container-fluid">
        <div class="card">
            <div class="card-header mt-2">
                <h3 class="text-center">Edit Jurnal Manual</h3>
            </div>
            <div class="card-body">
                {!! Form::open(['route' => ['journals.update', $lims_journal_data->id], 'method' => 'put', 'id' => 'journal-form']) !!}
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Tanggal *</label>
                        <input type="date" name="journal_date" value="{{ old('journal_date', $lims_journal_data->journal_date) }}" required class="form-control">
                    </div>
                    <div class="col-md-8 form-group">
                        <label>Deskripsi</label>
                        <input type="text" name="description" value="{{ old('description', $lims_journal_data->description) }}" class="form-control">
                    </div>
                </div>

                <table class="table" id="journal-lines-table">
                    <thead>
                        <tr>
                            <th style="width:35%">Akun</th>
                            <th style="width:15%">Debit</th>
                            <th style="width:15%">Kredit</th>
                            <th style="width:25%">Catatan</th>
                            <th style="width:10%"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lims_journal_data->details as $detail)
                        <tr class="journal-line">
                            <td>
                                <select name="chart_of_account_id[]" class="selectpicker form-control" data-live-search="true" data-live-search-style="contains" data-size="10" title="- Pilih Akun -" required>
                                    <option value="">- Pilih Akun -</option>
                                    @foreach($lims_coa_all as $coa)
                                    <option value="{{ $coa->id }}" data-tokens="{{ $coa->code }} {{ $coa->name }}" @if($coa->id == $detail->chart_of_account_id) selected @endif>{{ $coa->code }} - {{ $coa->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input type="number" step="any" name="debit[]" class="form-control debit-input" value="{{ $detail->debit }}"></td>
                            <td><input type="number" step="any" name="credit[]" class="form-control credit-input" value="{{ $detail->credit }}"></td>
                            <td><input type="text" name="note[]" class="form-control" value="{{ $detail->note }}"></td>
                            <td><button type="button" class="btn btn-danger btn-sm remove-line"><i class="dripicons-trash"></i></button></td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th class="text-right">Total</th>
                            <th id="total-debit">0.00</th>
                            <th id="total-credit">0.00</th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                </table>

                <button type="button" id="add-line" class="btn btn-secondary btn-sm"><i class="dripicons-plus"></i> Tambah Baris</button>
                <div id="balance-warning" class="text-danger mt-2" style="display:none;">Total debit dan kredit harus sama.</div>

                <div class="form-group mt-3">
                    <button type="submit" class="btn btn-primary">{{trans('file.update')}}</button>
                    <a href="{{ route('journals.index') }}" class="btn btn-default">{{trans('file.cancel')}}</a>
                </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>
</section>

@endsection
@push('scripts')
<script type="text/javascript">
    $("ul#account").siblings('a').attr('aria-expanded','true');
    $("ul#account").addClass("show");
    $("ul#account #journal-list-menu").addClass("active");

    var journalDecimal = {{ (int) $general_setting->decimal }};

    $('.selectpicker').selectpicker({
        liveSearch: true,
        liveSearchStyle: 'contains'
    });

    function formatMoney(num) {
        return (num || 0).toLocaleString('id-ID', { minimumFractionDigits: journalDecimal, maximumFractionDigits: journalDecimal });
    }

    function lineTemplate() {
        var $tr = $('.journal-line').first().clone();
        $tr.find('input').val('');
        $tr.find('.bootstrap-select').each(function() {
            var $select = $(this).find('select').detach();
            $select.removeAttr('style').show();
            $(this).replaceWith($select);
        });
        $tr.find('select').val('');
        return $tr;
    }

    $('#add-line').on('click', function() {
        var $newRow = lineTemplate();
        $('#journal-lines-table tbody').append($newRow);
        $newRow.find('.selectpicker').selectpicker({
            liveSearch: true,
            liveSearchStyle: 'contains'
        });
        recalcTotals();
    });

    $(document).on('click', '.remove-line', function() {
        if ($('.journal-line').length > 2) {
            $(this).closest('tr').remove();
            recalcTotals();
        } else {
            alert('Minimal 2 baris diperlukan.');
        }
    });

    $(document).on('input', '.debit-input, .credit-input', recalcTotals);

    function recalcTotals() {
        var totalDebit = 0, totalCredit = 0;
        $('.debit-input').each(function() { totalDebit += parseFloat($(this).val()) || 0; });
        $('.credit-input').each(function() { totalCredit += parseFloat($(this).val()) || 0; });
        $('#total-debit').text(formatMoney(totalDebit));
        $('#total-credit').text(formatMoney(totalCredit));
        if (Math.abs(totalDebit - totalCredit) > 0.01 || totalDebit === 0) {
            $('#balance-warning').show();
        } else {
            $('#balance-warning').hide();
        }
    }
    recalcTotals();

    $('#journal-form').on('submit', function(e) {
        var totalDebit = 0, totalCredit = 0;
        $('.debit-input').each(function() { totalDebit += parseFloat($(this).val()) || 0; });
        $('.credit-input').each(function() { totalCredit += parseFloat($(this).val()) || 0; });
        if (Math.abs(totalDebit - totalCredit) > 0.01 || totalDebit === 0) {
            e.preventDefault();
            alert('Jurnal tidak seimbang: total debit harus sama dengan total kredit dan tidak boleh nol.');
        }
    });
</script>
@endpush
