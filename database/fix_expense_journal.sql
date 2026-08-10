-- =====================================================================
-- SalePro - Perbaikan Jurnal Otomatis Pengeluaran (Expense)
-- Tanggal: 2026-08-03
--
-- Pengganti `php artisan migrate` untuk server yang tidak bisa akses
-- artisan/SSH. Jalankan seluruh file ini di phpMyAdmin (tab SQL) atau:
--     mysql -u USER -p NAMA_DATABASE < fix_expense_journal.sql
--
-- AMAN DIJALANKAN BERULANG (idempotent): kolom/tabel/akun yang sudah ada
-- akan dilewati, pengeluaran yang sudah punya jurnal tidak diduplikasi.
--
-- BACKUP DATABASE DULU SEBELUM MENJALANKAN.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 0. Helper: ADD COLUMN hanya kalau kolomnya belum ada
--    (MySQL tidak punya "ADD COLUMN IF NOT EXISTS")
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS salepro_add_column_if_missing;
DELIMITER $$
CREATE PROCEDURE salepro_add_column_if_missing(
    IN p_table  VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_ddl    VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = p_table
          AND COLUMN_NAME  = p_column
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN ', p_ddl);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;


-- ---------------------------------------------------------------------
-- 1. Tabel inti modul akuntansi
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chart_of_accounts` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`            VARCHAR(191) NOT NULL,
  `name`            VARCHAR(191) NOT NULL,
  `type`            VARCHAR(191) NOT NULL,
  `normal_balance`  VARCHAR(191) NOT NULL,
  `parent_id`       INT NULL DEFAULT NULL,
  `opening_balance` DOUBLE NOT NULL DEFAULT 0,
  `description`     TEXT NULL,
  `is_default`      TINYINT(1) NOT NULL DEFAULT 0,
  `is_cash_account` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`      TIMESTAMP NULL DEFAULT NULL,
  `updated_at`      TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chart_of_accounts_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `journals` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `journal_no`     VARCHAR(191) NOT NULL,
  `journal_date`   DATE NOT NULL,
  `description`    TEXT NULL,
  `reference_type` VARCHAR(191) NULL DEFAULT NULL,
  `reference_id`   INT NULL DEFAULT NULL,
  `is_manual`      TINYINT(1) NOT NULL DEFAULT 0,
  `created_by`     INT NULL DEFAULT NULL,
  `created_at`     TIMESTAMP NULL DEFAULT NULL,
  `updated_at`     TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `journals_reference_index` (`reference_type`, `reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `journal_details` (
  `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `journal_id`           INT NOT NULL,
  `chart_of_account_id`  INT NOT NULL,
  `debit`                DOUBLE NOT NULL DEFAULT 0,
  `credit`               DOUBLE NOT NULL DEFAULT 0,
  `note`                 TEXT NULL,
  `created_at`           TIMESTAMP NULL DEFAULT NULL,
  `updated_at`           TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `journal_details_journal_id_index` (`journal_id`),
  KEY `journal_details_coa_id_index` (`chart_of_account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ---------------------------------------------------------------------
-- 2. Kolom tambahan (dilewati kalau sudah ada)
-- ---------------------------------------------------------------------
CALL salepro_add_column_if_missing('chart_of_accounts', 'is_cash_account',
    '`is_cash_account` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_default`');

CALL salepro_add_column_if_missing('accounts', 'chart_of_account_id',
    '`chart_of_account_id` INT NULL DEFAULT NULL AFTER `id`');

-- INI PENYEBAB ERROR "Unknown column 'chart_of_account_id'" SAAT SIMPAN KATEGORI BIAYA
CALL salepro_add_column_if_missing('expense_categories', 'chart_of_account_id',
    '`chart_of_account_id` INT NULL DEFAULT NULL AFTER `name`');


-- ---------------------------------------------------------------------
-- 3. Seed Bagan Akun standar (INSERT IGNORE -> kode yang sudah ada dilewati)
--    Tanpa akun 5200 & 1101, jurnal otomatis pengeluaran tidak bisa jalan.
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `chart_of_accounts`
  (`code`,`name`,`type`,`normal_balance`,`parent_id`,`opening_balance`,`description`,`is_default`,`is_cash_account`,`is_active`,`created_at`,`updated_at`)
VALUES
  ('1000','ASET','asset','debit',NULL,0,NULL,1,0,1,NOW(),NOW()),
  ('1100','Kas & Bank','asset','debit',NULL,0,NULL,1,0,1,NOW(),NOW()),
  ('1101','Kas','asset','debit',NULL,0,NULL,1,0,1,NOW(),NOW()),
  ('1200','Piutang Usaha','asset','debit',NULL,0,NULL,1,0,1,NOW(),NOW()),
  ('1300','Persediaan Barang Dagang','asset','debit',NULL,0,NULL,1,0,1,NOW(),NOW()),
  ('1400','Titipan / Deposit ke Supplier','asset','debit',NULL,0,NULL,1,0,1,NOW(),NOW()),
  ('2000','KEWAJIBAN','liability','credit',NULL,0,NULL,1,0,1,NOW(),NOW()),
  ('2100','Hutang Usaha','liability','credit',NULL,0,NULL,1,0,1,NOW(),NOW()),
  ('2200','Titipan / Deposit Pelanggan','liability','credit',NULL,0,NULL,1,0,1,NOW(),NOW()),
  ('2300','Selisih Kasir (Penyesuaian Stok)','liability','credit',NULL,0,NULL,1,0,1,NOW(),NOW()),
  ('3000','MODAL','equity','credit',NULL,0,NULL,1,0,1,NOW(),NOW()),
  ('3100','Modal Pemilik','equity','credit',NULL,0,NULL,1,0,1,NOW(),NOW()),
  ('3200','Laba Ditahan','equity','credit',NULL,0,NULL,1,0,1,NOW(),NOW()),
  ('4000','PENDAPATAN','revenue','credit',NULL,0,NULL,1,0,1,NOW(),NOW()),
  ('4100','Penjualan','revenue','credit',NULL,0,NULL,1,0,1,NOW(),NOW()),
  ('4200','Retur Penjualan','revenue','debit',NULL,0,NULL,1,0,1,NOW(),NOW()),
  ('4300','Diskon Penjualan','revenue','debit',NULL,0,NULL,1,0,1,NOW(),NOW()),
  ('4900','Pendapatan Lain-lain','revenue','credit',NULL,0,NULL,1,0,1,NOW(),NOW()),
  ('5000','BEBAN','expense','debit',NULL,0,NULL,1,0,1,NOW(),NOW()),
  ('5100','Harga Pokok Penjualan (HPP)','expense','debit',NULL,0,NULL,1,0,1,NOW(),NOW()),
  ('5200','Beban Operasional','expense','debit',NULL,0,NULL,1,0,1,NOW(),NOW()),
  ('5210','Biaya Kirim Pembelian','expense','debit',NULL,0,NULL,1,0,1,NOW(),NOW()),
  ('5300','Beban Gaji','expense','debit',NULL,0,NULL,1,0,1,NOW(),NOW()),
  ('5400','Retur Pembelian','expense','credit',NULL,0,NULL,1,0,1,NOW(),NOW()),
  ('5410','Diskon / Potongan Pembelian','expense','credit',NULL,0,NULL,1,0,1,NOW(),NOW()),
  ('5900','Beban Lain-lain','expense','debit',NULL,0,NULL,1,0,1,NOW(),NOW());


-- ---------------------------------------------------------------------
-- 4. Buatkan sub-akun Kas untuk setiap akun kas/bank di tabel `accounts`
--    yang belum punya pasangan COA, lalu tautkan.
-- ---------------------------------------------------------------------
INSERT INTO `chart_of_accounts`
  (`code`,`name`,`type`,`normal_balance`,`parent_id`,`opening_balance`,`description`,`is_default`,`is_cash_account`,`is_active`,`created_at`,`updated_at`)
SELECT
  CONCAT('1101.', a.id),
  CONCAT(a.name, ' (', a.account_no, ')'),
  'asset', 'debit',
  (SELECT id FROM `chart_of_accounts` WHERE code = '1101' LIMIT 1),
  COALESCE(a.initial_balance, 0),
  CONCAT('Auto-generated for account: ', a.name),
  0, 1, 1, NOW(), NOW()
FROM `accounts` a
WHERE a.chart_of_account_id IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM `chart_of_accounts` c WHERE c.code = CONCAT('1101.', a.id)
  );

UPDATE `accounts` a
JOIN `chart_of_accounts` c ON c.code = CONCAT('1101.', a.id)
SET a.chart_of_account_id = c.id
WHERE a.chart_of_account_id IS NULL;

UPDATE `chart_of_accounts`
SET `is_cash_account` = 1
WHERE `id` IN (SELECT `chart_of_account_id` FROM `accounts` WHERE `chart_of_account_id` IS NOT NULL);


-- ---------------------------------------------------------------------
-- 5. Default setiap Kategori Biaya ke "Beban Operasional" (5200)
--    Bisa diubah per kategori lewat menu Kategori Biaya.
-- ---------------------------------------------------------------------
UPDATE `expense_categories`
SET `chart_of_account_id` = (SELECT id FROM `chart_of_accounts` WHERE code = '5200' LIMIT 1)
WHERE `chart_of_account_id` IS NULL;


-- ---------------------------------------------------------------------
-- 6. BACKFILL: buat jurnal untuk pengeluaran lama yang belum punya jurnal
--    Dr Akun Beban kategori (fallback 5200)  /  Cr Kas-Bank (fallback 1101)
-- ---------------------------------------------------------------------

-- 6a. Header jurnal
INSERT INTO `journals`
  (`journal_no`,`journal_date`,`description`,`reference_type`,`reference_id`,`is_manual`,`created_by`,`created_at`,`updated_at`)
SELECT
  CONCAT('jr-bf-e', e.id),
  DATE(e.created_at),
  CONCAT('Beban ', e.reference_no),
  'expense', e.id, 0, e.user_id, NOW(), NOW()
FROM `expenses` e
WHERE e.amount > 0
  AND NOT EXISTS (
      SELECT 1 FROM `journals` j
      WHERE j.reference_type = 'expense' AND j.reference_id = e.id
  );

-- 6b. Baris debit + kredit (derived table supaya MySQL boleh baca journal_details)
INSERT INTO `journal_details`
  (`journal_id`,`chart_of_account_id`,`debit`,`credit`,`note`,`created_at`,`updated_at`)
SELECT x.journal_id, x.coa_id, x.debit, x.credit, NULL, NOW(), NOW()
FROM (
    -- DEBIT: akun beban milik kategori, fallback 5200
    SELECT
      j.id AS journal_id,
      COALESCE(ec.chart_of_account_id, (SELECT id FROM `chart_of_accounts` WHERE code = '5200' LIMIT 1)) AS coa_id,
      e.amount AS debit,
      0 AS credit
    FROM `journals` j
    JOIN `expenses` e ON e.id = j.reference_id
    LEFT JOIN `expense_categories` ec ON ec.id = e.expense_category_id
    WHERE j.reference_type = 'expense'
      AND NOT EXISTS (SELECT 1 FROM `journal_details` d WHERE d.journal_id = j.id)

    UNION ALL

    -- KREDIT: akun kas/bank yang dipakai bayar, fallback 1101
    SELECT
      j.id AS journal_id,
      COALESCE(a.chart_of_account_id, (SELECT id FROM `chart_of_accounts` WHERE code = '1101' LIMIT 1)) AS coa_id,
      0 AS debit,
      e.amount AS credit
    FROM `journals` j
    JOIN `expenses` e ON e.id = j.reference_id
    LEFT JOIN `accounts` a ON a.id = e.account_id
    WHERE j.reference_type = 'expense'
      AND NOT EXISTS (SELECT 1 FROM `journal_details` d WHERE d.journal_id = j.id)
) AS x;


-- ---------------------------------------------------------------------
-- 7. Tandai migration sebagai sudah jalan, supaya `php artisan migrate`
--    di kemudian hari TIDAK mengulang dan bikin error "column exists".
-- ---------------------------------------------------------------------
INSERT INTO `migrations` (`migration`, `batch`)
SELECT m.name, (SELECT COALESCE(MAX(batch), 0) + 1 FROM `migrations`)
FROM (
    SELECT '2026_07_11_090000_create_chart_of_accounts_table' AS name UNION ALL
    SELECT '2026_07_11_090100_create_journals_table' UNION ALL
    SELECT '2026_07_11_090200_create_journal_details_table' UNION ALL
    SELECT '2026_07_11_090300_add_chart_of_account_id_to_accounts_table' UNION ALL
    SELECT '2026_07_14_090000_add_chart_of_account_id_to_expense_categories_table' UNION ALL
    SELECT '2026_07_14_090100_add_is_cash_account_to_chart_of_accounts_table' UNION ALL
    SELECT '2026_07_14_120000_add_selisih_kasir_account' UNION ALL
    SELECT '2026_07_16_090000_add_purchase_discount_and_shipping_accounts'
) m
WHERE NOT EXISTS (
    SELECT 1 FROM `migrations` mm WHERE mm.migration = m.name
);


-- ---------------------------------------------------------------------
-- 8. Bersihkan helper
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS salepro_add_column_if_missing;


-- ---------------------------------------------------------------------
-- 9. VERIFIKASI (jalankan terpisah untuk mengecek hasil)
-- ---------------------------------------------------------------------
-- SELECT COUNT(*) AS pengeluaran_tanpa_jurnal
-- FROM expenses e
-- WHERE NOT EXISTS (SELECT 1 FROM journals j WHERE j.reference_type='expense' AND j.reference_id=e.id);
--
-- SELECT j.journal_no, j.journal_date, j.description,
--        SUM(d.debit) AS total_debit, SUM(d.credit) AS total_kredit
-- FROM journals j JOIN journal_details d ON d.journal_id = j.id
-- WHERE j.reference_type = 'expense'
-- GROUP BY j.id ORDER BY j.id DESC LIMIT 20;
