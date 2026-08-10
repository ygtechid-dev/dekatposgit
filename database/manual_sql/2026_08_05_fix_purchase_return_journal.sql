-- =====================================================================
-- 2026-08-05 - Perbaikan jurnal pembelian & retur pembelian
--
-- Jalankan lewat phpMyAdmin / Adminer. Aman diulang berkali-kali:
-- setiap bagian melewatkan data yang sudah benar.
--
-- Isi:
--   BAGIAN 1 - pastikan akun 5410 & 5210 ada (penyebab jurnal pembelian
--              tidak seimbang: "debit 455000 vs kredit 450000")
--   BAGIAN 2 - pastikan akun Persediaan & Hutang per gudang ada
--   BAGIAN 3 - buat jurnal untuk retur pembelian lama yang belum terjurnal
--   BAGIAN 4 - pemeriksaan hasil
-- =====================================================================


-- ---------------------------------------------------------------------
-- BAGIAN 1: akun Diskon Pembelian (5410) & Biaya Kirim Pembelian (5210)
--
-- Kalau akun 5410 tidak ada, baris kredit "Diskon Pembelian" dibuang
-- diam-diam sementara sisi debit tetap memakai harga kotor, sehingga
-- jurnal jadi tidak seimbang persis sebesar nilai diskon.
-- ---------------------------------------------------------------------
INSERT INTO chart_of_accounts
    (code, name, type, normal_balance, parent_id, opening_balance, description, is_default, is_active, created_at, updated_at)
SELECT '5410', 'Diskon / Potongan Pembelian', 'expense', 'credit',
       (SELECT id FROM (SELECT id FROM chart_of_accounts WHERE code = '5200' LIMIT 1) p),
       0, 'Dibuat otomatis oleh perbaikan 2026-08-05.', 1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM (SELECT code FROM chart_of_accounts) c WHERE c.code = '5410');

INSERT INTO chart_of_accounts
    (code, name, type, normal_balance, parent_id, opening_balance, description, is_default, is_active, created_at, updated_at)
SELECT '5210', 'Biaya Kirim Pembelian', 'expense', 'debit',
       (SELECT id FROM (SELECT id FROM chart_of_accounts WHERE code = '5200' LIMIT 1) p),
       0, 'Dibuat otomatis oleh perbaikan 2026-08-05.', 1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM (SELECT code FROM chart_of_accounts) c WHERE c.code = '5210');


-- ---------------------------------------------------------------------
-- BAGIAN 2: akun Persediaan (1300.X) & Hutang (2100.X) per gudang
--
-- Jurnal retur pembelian sekarang memakai akun per gudang yang sama
-- dengan jurnal pembeliannya, jadi akunnya harus ada lebih dulu.
-- ---------------------------------------------------------------------
INSERT INTO chart_of_accounts
    (code, name, type, normal_balance, parent_id, opening_balance, description, is_default, is_active, created_at, updated_at)
SELECT CONCAT('1300.', w.id),
       CONCAT('Persediaan Barang Gudang ', w.name),
       'asset', 'debit',
       (SELECT id FROM (SELECT id FROM chart_of_accounts WHERE code = '1300' LIMIT 1) p),
       0, CONCAT('Auto-generated inventory account for warehouse: ', w.name), 0, 1, NOW(), NOW()
FROM warehouses w
WHERE w.is_active = 1
  AND NOT EXISTS (
        SELECT 1 FROM (SELECT code FROM chart_of_accounts) c
        WHERE c.code = CONCAT('1300.', w.id)
  );

INSERT INTO chart_of_accounts
    (code, name, type, normal_balance, parent_id, opening_balance, description, is_default, is_active, created_at, updated_at)
SELECT CONCAT('2100.', w.id),
       CONCAT('Hutang Gudang ', w.name),
       'liability', 'credit',
       (SELECT id FROM (SELECT id FROM chart_of_accounts WHERE code = '2100' LIMIT 1) p),
       0, CONCAT('Auto-generated payable account for warehouse: ', w.name), 0, 1, NOW(), NOW()
FROM warehouses w
WHERE w.is_active = 1
  AND NOT EXISTS (
        SELECT 1 FROM (SELECT code FROM chart_of_accounts) c
        WHERE c.code = CONCAT('2100.', w.id)
  );


-- ---------------------------------------------------------------------
-- BAGIAN 3: jurnal untuk retur pembelian lama yang belum terjurnal
--
-- Retur pembelian yang dibuat sebelum perbaikan ini gagal terjurnal
-- diam-diam (exception-nya ditelan `catch {}` kosong). Bagian ini
-- membuatkan jurnalnya:
--
--     Dr Hutang Gudang / Kas-Bank
--         Cr Persediaan Gudang
--
-- Retur yang sudah punya jurnal dilewati.
-- ---------------------------------------------------------------------

-- 3a) header jurnal
INSERT INTO journals
    (journal_no, journal_date, description, reference_type, reference_id, is_manual, created_by, created_at, updated_at)
SELECT CONCAT('jr-', DATE_FORMAT(NOW(), '%Y%m%d'), '-bfrp-', rp.id),
       DATE(rp.created_at),
       CONCAT('Retur pembelian ', rp.reference_no),
       'return_purchase',
       rp.id,
       0,
       rp.user_id,
       NOW(), NOW()
FROM return_purchases rp
WHERE rp.grand_total > 0
  AND NOT EXISTS (
        SELECT 1 FROM (SELECT reference_type, reference_id FROM journals) j
        WHERE j.reference_type = 'return_purchase' AND j.reference_id = rp.id
  );

-- 3b) baris DEBIT - Hutang Gudang (retur mengurangi hutang ke supplier).
--     Kalau retur diuangkan lewat kas/bank, ganti akun ini secara manual
--     di menu Jurnal setelah SQL ini dijalankan.
INSERT INTO journal_details
    (journal_id, chart_of_account_id, debit, credit, note, created_at, updated_at)
SELECT j.id,
       COALESCE(
           (SELECT c.id FROM chart_of_accounts c WHERE c.code = CONCAT('2100.', rp.warehouse_id) LIMIT 1),
           (SELECT c.id FROM chart_of_accounts c WHERE c.code = '2100' LIMIT 1)
       ),
       rp.grand_total, 0,
       CONCAT('Retur pembelian ', rp.reference_no),
       NOW(), NOW()
FROM journals j
JOIN return_purchases rp ON rp.id = j.reference_id
WHERE j.reference_type = 'return_purchase'
  AND j.journal_no LIKE '%-bfrp-%'
  AND NOT EXISTS (
        SELECT 1 FROM (SELECT journal_id FROM journal_details) d WHERE d.journal_id = j.id
  );

-- 3c) baris KREDIT - Persediaan Gudang
INSERT INTO journal_details
    (journal_id, chart_of_account_id, debit, credit, note, created_at, updated_at)
SELECT j.id,
       COALESCE(
           (SELECT c.id FROM chart_of_accounts c WHERE c.code = CONCAT('1300.', rp.warehouse_id) LIMIT 1),
           (SELECT c.id FROM chart_of_accounts c WHERE c.code = '1300' LIMIT 1)
       ),
       0, rp.grand_total,
       CONCAT('Retur pembelian ', rp.reference_no),
       NOW(), NOW()
FROM journals j
JOIN return_purchases rp ON rp.id = j.reference_id
WHERE j.reference_type = 'return_purchase'
  AND j.journal_no LIKE '%-bfrp-%'
  AND (SELECT COUNT(*) FROM (SELECT journal_id FROM journal_details) d WHERE d.journal_id = j.id) = 1;


-- ---------------------------------------------------------------------
-- BAGIAN 4: pemeriksaan
-- ---------------------------------------------------------------------

-- 4a) Retur pembelian yang MASIH belum punya jurnal (harusnya 0 baris)
-- SELECT rp.id, rp.reference_no, rp.grand_total
-- FROM return_purchases rp
-- LEFT JOIN journals j ON j.reference_type = 'return_purchase' AND j.reference_id = rp.id
-- WHERE j.id IS NULL AND rp.grand_total > 0;

-- 4b) Jurnal yang tidak seimbang (harusnya 0 baris)
-- SELECT j.id, j.journal_no, j.reference_type, j.reference_id,
--        SUM(d.debit) AS total_debit, SUM(d.credit) AS total_kredit
-- FROM journals j
-- JOIN journal_details d ON d.journal_id = j.id
-- GROUP BY j.id, j.journal_no, j.reference_type, j.reference_id
-- HAVING ROUND(SUM(d.debit), 2) <> ROUND(SUM(d.credit), 2);


-- ---------------------------------------------------------------------
-- ROLLBACK (kalau backfill BAGIAN 3 ingin dibatalkan)
-- ---------------------------------------------------------------------
-- DELETE d FROM journal_details d
-- JOIN journals j ON j.id = d.journal_id
-- WHERE j.reference_type = 'return_purchase' AND j.journal_no LIKE '%-bfrp-%';
-- DELETE FROM journals
-- WHERE reference_type = 'return_purchase' AND journal_no LIKE '%-bfrp-%';
