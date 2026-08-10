<?php

namespace App\Traits;

/**
 * Jaring pengaman untuk angka uang yang masuk dari form.
 *
 * Halaman transaksi (pembelian, penjualan, retur, quotation, transfer)
 * merender angka ke DOM dengan format tampilan Indonesia, mis. "50.000,00".
 * Kalau JS gagal/terlambat menormalkan sebelum submit — atau form ter-submit
 * lewat Enter, bukan lewat tombol Simpan — nilai itu sampai ke PHP apa adanya.
 * (float)"50.000,00" di PHP menghasilkan 50, bukan 50000, sehingga grand_total
 * tersimpan 1000x lebih kecil dari nilai sebenarnya.
 *
 * Trait ini menormalkan angka tersebut di sisi server supaya perbaikan tidak
 * bergantung pada JS saja.
 */
trait MoneyInput
{
    /**
     * Kolom uang/angka yang lazim dikirim halaman transaksi.
     */
    protected static $moneyInputFields = [
        'total_discount', 'total_tax', 'total_cost', 'total_price',
        'order_tax', 'order_discount', 'shipping_cost', 'coupon_discount',
        'grand_total', 'paid_amount', 'amount', 'paying_amount',
        'balance', 'change',
    ];

    /**
     * Kolom uang yang dikirim sebagai array (satu nilai per baris produk).
     */
    protected static $moneyInputArrayFields = [
        'net_unit_cost', 'net_unit_price', 'unit_cost', 'unit_price',
        'discount', 'tax', 'subtotal', 'total',
    ];

    /**
     * "50.000,00" -> 50000.0 | "8.500" -> 8500.0 | "8.50" -> 8.5 | "50000.00" -> 50000.0
     */
    public static function normalizeMoney($value)
    {
        if ($value === null || $value === '' || is_array($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        $clean = preg_replace('/[^\d.,\-]/', '', trim((string) $value));
        if ($clean === '' || $clean === '-' || strtolower(trim((string) $value)) === 'nan') {
            return 0;
        }

        if (strpos($clean, ',') !== false) {
            // Ada koma -> koma pasti desimal, titik pasti pemisah ribuan.
            $clean = str_replace(',', '.', str_replace('.', '', $clean));
        }
        elseif (strpos($clean, '.') !== false) {
            $parts = explode('.', $clean);
            $last = array_pop($parts);
            // "8.500" / "1.234.567" -> ribuan. "8.5" / "8.50" -> desimal.
            if (count($parts) > 1 || strlen($last) === 3) {
                $clean = implode('', $parts) . $last;
            }
            else {
                $clean = implode('', $parts) . '.' . $last;
            }
        }

        return is_numeric($clean) ? (float) $clean : 0;
    }

    /**
     * Normalisasi seluruh kolom uang pada payload transaksi.
     *
     * @param  array      $data
     * @param  array|null $extraFields kolom tambahan khusus modul tertentu
     * @return array
     */
    protected function normalizeMoneyInput(array $data, array $extraFields = [])
    {
        foreach (array_merge(self::$moneyInputFields, $extraFields) as $key) {
            if (isset($data[$key]) && !is_array($data[$key])) {
                $data[$key] = self::normalizeMoney($data[$key]);
            }
        }

        foreach (self::$moneyInputArrayFields as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                $data[$key] = array_map([self::class, 'normalizeMoney'], $data[$key]);
            }
        }

        return $data;
    }
}
