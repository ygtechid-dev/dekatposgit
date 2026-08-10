-- =============================================================================
-- Perbaikan data: total transaksi yang mengecil 1000x
-- Tanggal   : 2026-08-03
-- Penyebab  : halaman Edit merender angka sebagai "50.000,00" ke DOM, lalu JS
--             menghitung ulang dengan parseFloat("50.000,00") = 50 (bukan
--             50000). Nilai yang mengecil itu ikut tersimpan ke kolom
--             grand_total / total_cost / total_price.
-- Sumber kebenaran: tabel detail per baris produk (product_purchases /
--             product_sales) TIDAK ikut rusak, jadi total dihitung ulang
--             dari sana.
--
-- Script ini IDEMPOTENT: aman dijalankan berkali-kali. Baris yang totalnya
-- sudah benar tidak akan tersentuh (selisih <= 0.01 dianggap benar).
--
-- CARA PAKAI (phpMyAdmin / Adminer):
--   1. BACKUP DATABASE DULU.
--   2. Jalankan LANGKAH 1 -> lihat baris mana yang terdampak.
--   3. Jalankan LANGKAH 2 (pembelian) dan/atau LANGKAH 3 (penjualan).
--   4. Baca LANGKAH 4 soal jurnal akuntansi.
--   5. Jalankan LANGKAH 5 untuk memastikan sudah bersih.
-- =============================================================================


-- -----------------------------------------------------------------------------
-- LANGKAH 1 - DIAGNOSA (hanya membaca, tidak mengubah apa pun)
-- -----------------------------------------------------------------------------

-- 1a. Pembelian yang grand_total-nya tidak cocok dengan detail itemnya
SELECT
    p.id,
    p.reference_no,
    DATE(p.created_at)                                    AS tanggal,
    p.total_cost                                          AS total_cost_tersimpan,
    d.sum_total                                           AS total_cost_seharusnya,
    p.grand_total                                         AS grand_total_tersimpan,
    ROUND(d.sum_total
          + ROUND((d.sum_total - IFNULL(p.order_discount,0)) * IFNULL(p.order_tax_rate,0) / 100, 2)
          + IFNULL(p.shipping_cost,0)
          - IFNULL(p.order_discount,0), 2)                AS grand_total_seharusnya,
    p.paid_amount,
    (SELECT COUNT(*) FROM journals j
      WHERE j.reference_type = 'purchase' AND j.reference_id = p.id) AS jumlah_jurnal
FROM purchases p
JOIN (
    SELECT purchase_id,
           SUM(total)    AS sum_total,
           SUM(tax)      AS sum_tax,
           SUM(discount) AS sum_discount
    FROM product_purchases
    GROUP BY purchase_id
) d ON d.purchase_id = p.id
WHERE ABS(p.grand_total - (
        d.sum_total
        + ROUND((d.sum_total - IFNULL(p.order_discount,0)) * IFNULL(p.order_tax_rate,0) / 100, 2)
        + IFNULL(p.shipping_cost,0)
        - IFNULL(p.order_discount,0)
      )) > 0.01
ORDER BY p.id DESC;

-- 1b. Penjualan yang grand_total-nya tidak cocok dengan detail itemnya
SELECT
    s.id,
    s.reference_no,
    DATE(s.created_at)                                    AS tanggal,
    s.total_price                                         AS total_price_tersimpan,
    d.sum_total                                           AS total_price_seharusnya,
    s.grand_total                                         AS grand_total_tersimpan,
    ROUND(d.sum_total
          + ROUND((d.sum_total - IFNULL(s.order_discount,0)) * IFNULL(s.order_tax_rate,0) / 100, 2)
          + IFNULL(s.shipping_cost,0)
          - IFNULL(s.order_discount,0)
          - IFNULL(s.coupon_discount,0), 2)               AS grand_total_seharusnya,
    s.paid_amount
FROM sales s
JOIN (
    SELECT sale_id,
           SUM(total)    AS sum_total,
           SUM(tax)      AS sum_tax,
           SUM(discount) AS sum_discount
    FROM product_sales
    GROUP BY sale_id
) d ON d.sale_id = s.id
WHERE ABS(s.grand_total - (
        d.sum_total
        + ROUND((d.sum_total - IFNULL(s.order_discount,0)) * IFNULL(s.order_tax_rate,0) / 100, 2)
        + IFNULL(s.shipping_cost,0)
        - IFNULL(s.order_discount,0)
        - IFNULL(s.coupon_discount,0)
      )) > 0.01
ORDER BY s.id DESC;


-- -----------------------------------------------------------------------------
-- LANGKAH 2 - PERBAIKI PEMBELIAN
-- -----------------------------------------------------------------------------

UPDATE purchases p
JOIN (
    SELECT purchase_id,
           SUM(total)    AS sum_total,
           SUM(tax)      AS sum_tax,
           SUM(discount) AS sum_discount
    FROM product_purchases
    GROUP BY purchase_id
) d ON d.purchase_id = p.id
SET p.total_cost     = d.sum_total,
    p.total_tax      = d.sum_tax,
    p.total_discount = d.sum_discount,
    p.order_tax      = ROUND((d.sum_total - IFNULL(p.order_discount,0)) * IFNULL(p.order_tax_rate,0) / 100, 2),
    p.grand_total    = ROUND(d.sum_total
                             + ROUND((d.sum_total - IFNULL(p.order_discount,0)) * IFNULL(p.order_tax_rate,0) / 100, 2)
                             + IFNULL(p.shipping_cost,0)
                             - IFNULL(p.order_discount,0), 2)
WHERE ABS(p.grand_total - (
        d.sum_total
        + ROUND((d.sum_total - IFNULL(p.order_discount,0)) * IFNULL(p.order_tax_rate,0) / 100, 2)
        + IFNULL(p.shipping_cost,0)
        - IFNULL(p.order_discount,0)
      )) > 0.01;

-- Selaraskan status pembayaran dengan grand_total yang sudah benar.
-- Konvensi aplikasi (PurchaseController): 1 = belum lunas, 2 = lunas.
UPDATE purchases p
SET p.payment_status = CASE
        WHEN ABS(p.grand_total - IFNULL(p.paid_amount,0)) > 0.01 THEN 1
        ELSE 2
    END
WHERE p.payment_status <> CASE
        WHEN ABS(p.grand_total - IFNULL(p.paid_amount,0)) > 0.01 THEN 1
        ELSE 2
    END;


-- -----------------------------------------------------------------------------
-- LANGKAH 3 - PERBAIKI PENJUALAN
-- -----------------------------------------------------------------------------

UPDATE sales s
JOIN (
    SELECT sale_id,
           SUM(total)    AS sum_total,
           SUM(tax)      AS sum_tax,
           SUM(discount) AS sum_discount
    FROM product_sales
    GROUP BY sale_id
) d ON d.sale_id = s.id
SET s.total_price    = d.sum_total,
    s.total_tax      = d.sum_tax,
    s.total_discount = d.sum_discount,
    s.order_tax      = ROUND((d.sum_total - IFNULL(s.order_discount,0)) * IFNULL(s.order_tax_rate,0) / 100, 2),
    s.grand_total    = ROUND(d.sum_total
                             + ROUND((d.sum_total - IFNULL(s.order_discount,0)) * IFNULL(s.order_tax_rate,0) / 100, 2)
                             + IFNULL(s.shipping_cost,0)
                             - IFNULL(s.order_discount,0)
                             - IFNULL(s.coupon_discount,0), 2)
WHERE ABS(s.grand_total - (
        d.sum_total
        + ROUND((d.sum_total - IFNULL(s.order_discount,0)) * IFNULL(s.order_tax_rate,0) / 100, 2)
        + IFNULL(s.shipping_cost,0)
        - IFNULL(s.order_discount,0)
        - IFNULL(s.coupon_discount,0)
      )) > 0.01;


-- -----------------------------------------------------------------------------
-- LANGKAH 4 - JURNAL AKUNTANSI (baca dulu, jangan asal jalankan)
-- -----------------------------------------------------------------------------
--
-- Jurnal pembelian dibuat dari grand_total. Kalau grand_total sudah salah
-- waktu jurnal dipos, ada dua kemungkinan:
--
--   (a) Jurnal GAGAL dipos (paling sering) karena debit != kredit -> tidak ada
--       baris di tabel journals. Kolom "jumlah_jurnal" di LANGKAH 1a = 0.
--       Solusi: buka pembelian itu -> Simpan ulang tanpa mengubah apa pun.
--       Jurnal akan terbentuk dengan angka yang sudah benar.
--
--   (b) Jurnal TERLANJUR dipos dengan angka salah -> "jumlah_jurnal" >= 1.
--       Solusi: hapus jurnal lamanya lebih dulu (query di bawah), baru simpan
--       ulang pembeliannya. PENTING: aplikasi TIDAK menghapus jurnal lama saat
--       pembelian disimpan ulang, jadi kalau tidak dihapus akan jadi dobel.
--
-- Cek jurnal pembelian yang nilainya tidak cocok dengan grand_total sekarang:
SELECT j.id           AS journal_id,
       j.journal_no,
       j.reference_id AS purchase_id,
       p.reference_no,
       SUM(jd.debit)  AS total_debit,
       p.grand_total
FROM journals j
JOIN journal_details jd ON jd.journal_id = j.id
JOIN purchases p        ON p.id = j.reference_id
WHERE j.reference_type = 'purchase'
GROUP BY j.id, j.journal_no, j.reference_id, p.reference_no, p.grand_total
HAVING ABS(SUM(jd.debit) - (p.grand_total + IFNULL(p.order_discount,0))) > 1
ORDER BY j.id DESC;

-- Hapus jurnal yang salah untuk SATU pembelian (ganti <PURCHASE_ID>),
-- lalu simpan ulang pembelian tersebut lewat aplikasi.
-- DELETE jd FROM journal_details jd
--   JOIN journals j ON j.id = jd.journal_id
--  WHERE j.reference_type = 'purchase' AND j.reference_id = <PURCHASE_ID>;
-- DELETE FROM journals
--  WHERE reference_type = 'purchase' AND reference_id = <PURCHASE_ID>;


-- -----------------------------------------------------------------------------
-- LANGKAH 5 - VERIFIKASI (harus mengembalikan 0 baris)
-- -----------------------------------------------------------------------------

SELECT 'pembelian_masih_salah' AS cek, COUNT(*) AS jumlah
FROM purchases p
JOIN (SELECT purchase_id, SUM(total) AS sum_total
        FROM product_purchases GROUP BY purchase_id) d ON d.purchase_id = p.id
WHERE ABS(p.grand_total - (
        d.sum_total
        + ROUND((d.sum_total - IFNULL(p.order_discount,0)) * IFNULL(p.order_tax_rate,0) / 100, 2)
        + IFNULL(p.shipping_cost,0)
        - IFNULL(p.order_discount,0))) > 0.01
UNION ALL
SELECT 'penjualan_masih_salah', COUNT(*)
FROM sales s
JOIN (SELECT sale_id, SUM(total) AS sum_total
        FROM product_sales GROUP BY sale_id) d ON d.sale_id = s.id
WHERE ABS(s.grand_total - (
        d.sum_total
        + ROUND((d.sum_total - IFNULL(s.order_discount,0)) * IFNULL(s.order_tax_rate,0) / 100, 2)
        + IFNULL(s.shipping_cost,0)
        - IFNULL(s.order_discount,0)
        - IFNULL(s.coupon_discount,0))) > 0.01;
