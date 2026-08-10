-- ============================================================================
-- Revisi 2026-08-08
--   1. Approve setoran kasir gagal kalau ada selisih kasir
--   2. Struk POS Simple error "Attempt to read property company_name on null"
--
-- Aman dijalankan berulang kali (idempotent). Tidak butuh artisan.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. Akun "Selisih Kasir" (2300) harus ada.
--    Kalau akun ini belum ada, baris selisih hilang dari jurnal approve kasir
--    dan jurnalnya jadi timpang -> approve ditolak dengan "Jurnal tidak seimbang".
--    Kode PHP sekarang membuatnya otomatis, ini cuma jaring pengaman.
-- ---------------------------------------------------------------------------
-- Kolom `code` sudah UNIQUE, jadi INSERT IGNORE aman diulang.
INSERT IGNORE INTO `chart_of_accounts`
    (`code`, `name`, `type`, `normal_balance`, `parent_id`, `opening_balance`,
     `description`, `is_default`, `is_active`, `created_at`, `updated_at`)
VALUES
    ('2300', 'Selisih Kasir (Penyesuaian Stok)', 'liability', 'credit', NULL, 0,
     'Selisih stok fisik vs sistem hasil penyesuaian stok', 1, 1, NOW(), NOW());

-- ---------------------------------------------------------------------------
-- 1b. Pastikan ada minimal satu Biller.
--     Tabel `billers` yang kosong bikin sales.biller_id yatim -> halaman struk
--     mati "Attempt to read property company_name on null" dan retur gagal.
--     Nama diambil dari Pengaturan Umum; rapikan lagi lewat menu Biller.
-- ---------------------------------------------------------------------------
INSERT INTO `billers`
    (`name`, `company_name`, `email`, `phone_number`, `address`, `city`,
     `is_active`, `created_at`, `updated_at`)
SELECT
    COALESCE(NULLIF(g.`company_name`, ''), NULLIF(g.`site_title`, ''), 'Toko'),
    COALESCE(NULLIF(g.`company_name`, ''), NULLIF(g.`site_title`, ''), 'Toko'),
    COALESCE(NULLIF(g.`email`, ''), 'no-reply@localhost'),
    COALESCE(NULLIF(g.`phone`, ''), '-'),
    '-', '-', 1, NOW(), NOW()
FROM (SELECT * FROM `general_settings` ORDER BY `id` DESC LIMIT 1) g
WHERE (SELECT COUNT(*) FROM `billers`) = 0;

-- Kalau general_settings juga kosong, tetap buat satu biller minimal.
INSERT INTO `billers`
    (`name`, `company_name`, `email`, `phone_number`, `address`, `city`,
     `is_active`, `created_at`, `updated_at`)
SELECT 'Toko', 'Toko', 'no-reply@localhost', '-', '-', '-', 1, NOW(), NOW()
FROM DUAL
WHERE (SELECT COUNT(*) FROM `billers`) = 0;

-- ---------------------------------------------------------------------------
-- 2. Perbaiki transaksi penjualan yang biller_id-nya kosong atau menunjuk
--    biller yang sudah dihapus. Ini yang bikin halaman struk mati.
--    Diarahkan ke biller aktif dengan id terkecil.
-- ---------------------------------------------------------------------------
UPDATE `sales` s
LEFT JOIN `billers` b ON b.`id` = s.`biller_id`
SET s.`biller_id` = (
        SELECT `id` FROM `billers`
        ORDER BY (`is_active` = 1) DESC, `id` ASC
        LIMIT 1
    )
WHERE b.`id` IS NULL
  AND EXISTS (SELECT 1 FROM `billers` LIMIT 1);

-- Retur penjualan ikut dirapikan supaya konsisten.
UPDATE `returns` r
LEFT JOIN `billers` b ON b.`id` = r.`biller_id`
SET r.`biller_id` = (
        SELECT `id` FROM `billers`
        ORDER BY (`is_active` = 1) DESC, `id` ASC
        LIMIT 1
    )
WHERE b.`id` IS NULL
  AND EXISTS (SELECT 1 FROM `billers` LIMIT 1);

-- ---------------------------------------------------------------------------
-- 3. pos_settings.biller_id harus menunjuk biller yang benar-benar ada,
--    supaya POS Simple tidak mengirim biller_id yatim untuk transaksi baru.
-- ---------------------------------------------------------------------------
UPDATE `pos_settings` p
LEFT JOIN `billers` b ON b.`id` = p.`biller_id`
SET p.`biller_id` = (
        SELECT `id` FROM `billers`
        ORDER BY (`is_active` = 1) DESC, `id` ASC
        LIMIT 1
    )
WHERE b.`id` IS NULL
  AND EXISTS (SELECT 1 FROM `billers` LIMIT 1);

-- ---------------------------------------------------------------------------
-- Pemeriksaan: daftar setoran kasir yang statusnya approved tapi jurnalnya
-- belum ada. Approve ulang dari halaman Kasir untuk memposting jurnalnya.
-- ---------------------------------------------------------------------------
-- SELECT c.id, c.warehouse_id, c.deposit_amount, c.cash_difference, c.approved_at
-- FROM cash_registers c
-- LEFT JOIN journals j
--        ON j.reference_type = 'cash_register_sale' AND j.reference_id = c.id
-- WHERE c.approval_status = 'approved' AND j.id IS NULL;
