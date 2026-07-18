<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Lembar Stok Opname{{$lims_warehouse_data ? ' - ' . $lims_warehouse_data->name : ''}}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style type="text/css">
        * {
            font-family: Arial, Helvetica, sans-serif;
            box-sizing: border-box;
        }
        body { margin: 20px; color: #222; }
        .btn {
            padding: 8px 16px;
            text-decoration: none;
            border: none;
            display: inline-block;
            cursor: pointer;
            border-radius: 4px;
            background-color: #6449e7;
            color: #fff;
            font-size: 14px;
        }
        .toolbar { margin-bottom: 15px; }
        h2, h4 { margin: 0 0 4px 0; }
        .meta-row { margin-bottom: 2px; font-size: 13px; }
        .rack-heading {
            background-color: #eee;
            padding: 6px 10px;
            margin-top: 20px;
            font-weight: bold;
            border: 1px solid #ccc;
        }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #999; padding: 6px 8px; font-size: 13px; text-align: left; }
        th { background-color: #f5f5f5; }
        .text-center { text-align: center; }
        .blank-col { width: 110px; }
        .no-col { width: 35px; }
        .sign-block { margin-top: 40px; display: flex; justify-content: space-between; }
        .sign-box { text-align: center; width: 220px; }
        .sign-line { margin-top: 60px; border-top: 1px solid #333; }

        @media print {
            .toolbar { display: none; }
            body { margin: 0; }
            .rack-heading { break-inside: avoid; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; }
        }
    </style>
  </head>
  <body>
    <div class="toolbar">
        <button type="button" class="btn" onclick="window.print()">Cetak / Print</button>
    </div>

    <h2>Lembar Stok Opname</h2>
    <div class="meta-row"><strong>Gudang/Toko:</strong> {{$lims_warehouse_data->name ?? '-'}} ({{ isset($lims_warehouse_data) && $lims_warehouse_data->type == 'toko' ? 'Toko' : 'Gudang' }})</div>
    @if($rack_id && $lims_rack_list->firstWhere('id', (int) $rack_id))
    <div class="meta-row"><strong>Rak:</strong> {{$lims_rack_list->firstWhere('id', (int) $rack_id)->name}}</div>
    @endif
    <div class="meta-row"><strong>Tanggal Cetak:</strong> {{ date('d-m-Y H:i') }}</div>
    <div class="meta-row"><strong>Dihitung oleh:</strong> ______________________</div>

    @if(!$warehouse_id)
    <p><em>Pilih gudang/toko terlebih dahulu dari halaman Laporan Stok Rak.</em></p>
    @elseif($lims_product_data->isEmpty())
    <p><em>Tidak ada produk dengan stok di gudang/toko ini.</em></p>
    @else
        @foreach($lims_product_data as $rackName => $products)
        <div class="rack-heading">Rak: {{ $rackName }}</div>
        <table>
            <thead>
                <tr>
                    <th class="no-col">No</th>
                    <th>Barcode</th>
                    <th>Nama Produk</th>
                    <th>Satuan</th>
                    <th class="text-center">Stok Sistem</th>
                    <th class="text-center blank-col">Stok Fisik</th>
                    <th class="text-center blank-col">Selisih</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($products as $key => $product)
                <tr>
                    <td class="text-center">{{ $key + 1 }}</td>
                    <td>{{ $product->barcode }}</td>
                    <td>{{ $product->nama }}</td>
                    <td>{{ $product->satuan ?? '-' }}</td>
                    <td class="text-center">{{ $product->stok_sistem }}</td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endforeach
    @endif

    <div class="sign-block">
        <div class="sign-box">
            <div>Dihitung oleh</div>
            <div class="sign-line">&nbsp;</div>
        </div>
        <div class="sign-box">
            <div>Diperiksa oleh</div>
            <div class="sign-line">&nbsp;</div>
        </div>
        <div class="sign-box">
            <div>Disetujui oleh</div>
            <div class="sign-line">&nbsp;</div>
        </div>
    </div>
  </body>
</html>
