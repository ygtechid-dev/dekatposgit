-- =====================================================================
-- SalePro - Perbaikan Jurnal Pembelian: diskon per item dipecah
-- Tanggal: 2026-08-03
--
-- Menyesuaikan jurnal pembelian LAMA agar mengikuti aturan baru:
--   Dr Persediaan Gudang        = harga KOTOR (sebelum potongan)
--   Cr Diskon/Potongan Pembelian = diskon nota + diskon per item
--   Cr Hutang Gudang            = grand_total (tidak berubah)
--
-- Sebelumnya diskon yang diisi di BARIS PRODUK langsung mengurangi nilai
-- Persediaan dan tidak pernah muncul sebagai baris tersendiri.
-- Diskon di level nota (order_discount) sudah benar dan tidak disentuh.
--
-- Jalankan SETELAH fix_expense_journal.sql dan setelah upload
-- PurchaseController.php yang baru.
--
-- AMAN DIJALANKAN BERULANG: jurnal yang sudah diperbaiki dicatat di tabel
-- `salepro_journal_backfill_log` dan dilewati pada eksekusi berikutnya.
--
-- BACKUP DATABASE DULU.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1. Catatan jurnal yang sudah pernah diperbaiki (pencegah dobel)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `salepro_journal_backfill_log` (
  `journal_id` INT NOT NULL,
  `task`       VARCHAR(64) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`journal_id`, `task`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ---------------------------------------------------------------------
-- 2. Kumpulkan total diskon per item untuk tiap jurnal pembelian
--    yang belum pernah diperbaiki
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `salepro_tmp_line_discount`;
CREATE TABLE `salepro_tmp_line_discount` (
  `journal_id`    INT NOT NULL,
  `purchase_id`   INT NOT NULL,
  `line_discount` DOUBLE NOT NULL,
  PRIMARY KEY (`journal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `salepro_tmp_line_discount` (`journal_id`, `purchase_id`, `line_discount`)
SELECT j.id, p.id, SUM(pp.discount)
FROM `purchases` p
JOIN `journals` j
     ON j.reference_type = 'purchase' AND j.reference_id = p.id
JOIN `product_purchases` pp
     ON pp.purchase_id = p.id
WHERE NOT EXISTS (
        SELECT 1 FROM `salepro_journal_backfill_log` l
        WHERE l.journal_id = j.id AND l.task = 'purchase_line_discount'
      )
GROUP BY j.id, p.id
HAVING SUM(pp.discount) > 0;


-- ---------------------------------------------------------------------
-- 3. Naikkan baris debit Persediaan ke harga kotor
--    (akun 1300 atau sub-akunnya 1300.<id gudang>)
-- ---------------------------------------------------------------------
UPDATE `journal_details` d
JOIN `salepro_tmp_line_discount` t ON t.journal_id = d.journal_id
JOIN `chart_of_accounts` c ON c.id = d.chart_of_account_id
SET d.debit = d.debit + t.line_discount,
    d.updated_at = NOW()
WHERE d.debit > 0
  AND (c.code = '1300' OR c.code LIKE '1300.%');


-- ---------------------------------------------------------------------
-- 4a. Tambah ke baris Diskon Pembelian (5410) yang SUDAH ada
--     -> harus dijalankan SEBELUM langkah 4b
-- ---------------------------------------------------------------------
UPDATE `journal_details` d
JOIN `salepro_tmp_line_discount` t ON t.journal_id = d.journal_id
JOIN `chart_of_accounts` c ON c.id = d.chart_of_account_id
SET d.credit = d.credit + t.line_discount,
    d.updated_at = NOW()
WHERE c.code = '5410';

-- 4b. Buat baris Diskon Pembelian untuk jurnal yang belum punya
INSERT INTO `journal_details`
  (`journal_id`,`chart_of_account_id`,`debit`,`credit`,`note`,`created_at`,`updated_at`)
SELECT x.journal_id, x.coa_id, 0, x.line_discount,
       'Diskon per item (penyesuaian)', NOW(), NOW()
FROM (
    SELECT t.journal_id,
           (SELECT id FROM `chart_of_accounts` WHERE code = '5410' LIMIT 1) AS coa_id,
           t.line_discount
    FROM `salepro_tmp_line_discount` t
    WHERE NOT EXISTS (
        SELECT 1
        FROM `journal_details` d
        JOIN `chart_of_accounts` c ON c.id = d.chart_of_account_id
        WHERE d.journal_id = t.journal_id AND c.code = '5410'
    )
) AS x
WHERE x.coa_id IS NOT NULL;


-- ---------------------------------------------------------------------
-- 5. Tandai selesai + bersihkan tabel bantu
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `salepro_journal_backfill_log` (`journal_id`, `task`, `created_at`)
SELECT t.journal_id, 'purchase_line_discount', NOW()
FROM `salepro_tmp_line_discount` t;

DROP TABLE IF EXISTS `salepro_tmp_line_discount`;


-- ---------------------------------------------------------------------
-- 6. VERIFIKASI - jalankan terpisah.
--    Kolom `selisih` HARUS 0 untuk semua baris.
-- ---------------------------------------------------------------------
-- SELECT j.id, j.journal_no, j.description,
--        SUM(d.debit)  AS total_debit,
--        SUM(d.credit) AS total_kredit,
--        ROUND(SUM(d.debit) - SUM(d.credit), 2) AS selisih
-- FROM journals j
-- JOIN journal_details d ON d.journal_id = j.id
-- WHERE j.reference_type = 'purchase'
-- GROUP BY j.id
-- HAVING selisih <> 0;
--
-- Rincian satu nota pembelian:
-- SELECT c.code, c.name, d.debit, d.credit
-- FROM journals j
-- JOIN journal_details d ON d.journal_id = j.id
-- JOIN chart_of_accounts c ON c.id = d.chart_of_account_id
-- WHERE j.reference_type = 'purchase' AND j.reference_id = <ID_PEMBELIAN>;
