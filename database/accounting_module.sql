-- =====================================================================
-- SalePro Accounting Module — manual SQL (setara dengan 4 file migration)
-- Jalankan SEKALI SAJA di database SalePro kamu (phpMyAdmin / Adminer / mysql CLI).
-- Ganti prefix tabel jika perlu (default: tanpa prefix).
-- =====================================================================

START TRANSACTION;

-- ---------------------------------------------------------------------
-- 1) chart_of_accounts
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chart_of_accounts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `type` VARCHAR(255) NOT NULL,
  `normal_balance` VARCHAR(255) NOT NULL,
  `parent_id` INT NULL DEFAULT NULL,
  `opening_balance` DOUBLE NOT NULL DEFAULT 0,
  `description` TEXT NULL,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chart_of_accounts_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `chart_of_accounts`
  (`code`, `name`, `type`, `normal_balance`, `parent_id`, `opening_balance`, `description`, `is_default`, `is_active`, `created_at`, `updated_at`)
VALUES
  ('1000', 'ASET', 'asset', 'debit', NULL, 0, NULL, 1, 1, NOW(), NOW()),
  ('1100', 'Kas & Bank', 'asset', 'debit', NULL, 0, NULL, 1, 1, NOW(), NOW()),
  ('1101', 'Kas', 'asset', 'debit', NULL, 0, NULL, 1, 1, NOW(), NOW()),
  ('1200', 'Piutang Usaha', 'asset', 'debit', NULL, 0, NULL, 1, 1, NOW(), NOW()),
  ('1300', 'Persediaan Barang Dagang', 'asset', 'debit', NULL, 0, NULL, 1, 1, NOW(), NOW()),
  ('1400', 'Titipan / Deposit ke Supplier', 'asset', 'debit', NULL, 0, NULL, 1, 1, NOW(), NOW()),
  ('2000', 'KEWAJIBAN', 'liability', 'credit', NULL, 0, NULL, 1, 1, NOW(), NOW()),
  ('2100', 'Hutang Usaha', 'liability', 'credit', NULL, 0, NULL, 1, 1, NOW(), NOW()),
  ('2200', 'Titipan / Deposit Pelanggan', 'liability', 'credit', NULL, 0, NULL, 1, 1, NOW(), NOW()),
  ('3000', 'MODAL', 'equity', 'credit', NULL, 0, NULL, 1, 1, NOW(), NOW()),
  ('3100', 'Modal Pemilik', 'equity', 'credit', NULL, 0, NULL, 1, 1, NOW(), NOW()),
  ('3200', 'Laba Ditahan', 'equity', 'credit', NULL, 0, NULL, 1, 1, NOW(), NOW()),
  ('4000', 'PENDAPATAN', 'revenue', 'credit', NULL, 0, NULL, 1, 1, NOW(), NOW()),
  ('4100', 'Penjualan', 'revenue', 'credit', NULL, 0, NULL, 1, 1, NOW(), NOW()),
  ('4200', 'Retur Penjualan', 'revenue', 'debit', NULL, 0, NULL, 1, 1, NOW(), NOW()),
  ('4300', 'Diskon Penjualan', 'revenue', 'debit', NULL, 0, NULL, 1, 1, NOW(), NOW()),
  ('4900', 'Pendapatan Lain-lain', 'revenue', 'credit', NULL, 0, NULL, 1, 1, NOW(), NOW()),
  ('5000', 'BEBAN', 'expense', 'debit', NULL, 0, NULL, 1, 1, NOW(), NOW()),
  ('5100', 'Harga Pokok Penjualan (HPP)', 'expense', 'debit', NULL, 0, NULL, 1, 1, NOW(), NOW()),
  ('5200', 'Beban Operasional', 'expense', 'debit', NULL, 0, NULL, 1, 1, NOW(), NOW()),
  ('5300', 'Beban Gaji', 'expense', 'debit', NULL, 0, NULL, 1, 1, NOW(), NOW()),
  ('5400', 'Retur Pembelian', 'expense', 'credit', NULL, 0, NULL, 1, 1, NOW(), NOW()),
  ('5900', 'Beban Lain-lain', 'expense', 'debit', NULL, 0, NULL, 1, 1, NOW(), NOW());

-- ---------------------------------------------------------------------
-- 2) journals (jurnal umum, header)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `journals` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `journal_no` VARCHAR(255) NOT NULL,
  `journal_date` DATE NOT NULL,
  `description` TEXT NULL,
  `reference_type` VARCHAR(255) NULL,
  `reference_id` INT NULL DEFAULT NULL,
  `is_manual` TINYINT(1) NOT NULL DEFAULT 0,
  `created_by` INT NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 3) journal_details (baris debit/kredit)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `journal_details` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `journal_id` INT NOT NULL,
  `chart_of_account_id` INT NOT NULL,
  `debit` DOUBLE NOT NULL DEFAULT 0,
  `credit` DOUBLE NOT NULL DEFAULT 0,
  `note` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 4) accounts.chart_of_account_id + auto-link setiap akun kas/bank yang
--    sudah ada ke Chart of Account anak dari "Kas" (1101)
-- ---------------------------------------------------------------------
ALTER TABLE `accounts` ADD COLUMN `chart_of_account_id` INT NULL DEFAULT NULL AFTER `id`;

-- tabel bantu sementara: 1 baris per akun kas/bank yang sudah ada,
-- dengan kode COA anak yang dijamin unik (kompatibel MySQL 5.7 & 8.x)
CREATE TEMPORARY TABLE `tmp_account_coa` (
  `account_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `account_no` VARCHAR(255) NOT NULL,
  `initial_balance` DOUBLE NULL,
  `gen_code` VARCHAR(255) NOT NULL
);

SET @rn := 0;
INSERT INTO `tmp_account_coa` (`account_id`, `name`, `account_no`, `initial_balance`, `gen_code`)
SELECT `id`, `name`, `account_no`, `initial_balance`, CONCAT('1101.', (@rn := @rn + 1))
FROM `accounts`
ORDER BY `id`;

INSERT INTO `chart_of_accounts`
  (`code`, `name`, `type`, `normal_balance`, `parent_id`, `opening_balance`, `description`, `is_default`, `is_active`, `created_at`, `updated_at`)
SELECT
  t.`gen_code`,
  CONCAT(t.`name`, ' (', t.`account_no`, ')'),
  'asset',
  'debit',
  (SELECT id FROM `chart_of_accounts` WHERE `code` = '1101'),
  IFNULL(t.`initial_balance`, 0),
  CONCAT('Auto-generated for account: ', t.`name`),
  0,
  1,
  NOW(),
  NOW()
FROM `tmp_account_coa` t;

UPDATE `accounts` a
JOIN `tmp_account_coa` t ON t.`account_id` = a.`id`
JOIN `chart_of_accounts` c ON c.`code` = t.`gen_code`
SET a.`chart_of_account_id` = c.`id`;

DROP TEMPORARY TABLE `tmp_account_coa`;

COMMIT;

-- ---------------------------------------------------------------------
-- OPSIONAL: supaya `php artisan migrate` di masa depan tidak mencoba
-- membuat ulang ke-4 tabel di atas dan gagal karena sudah ada, catat
-- migration ini sebagai "sudah dijalankan". Cek dulu batch terakhir:
--   SELECT MAX(batch) FROM migrations;
-- lalu ganti :BATCH di bawah dengan (hasil query + 1), baru jalankan:
-- ---------------------------------------------------------------------
-- INSERT INTO `migrations` (`migration`, `batch`) VALUES
--   ('2026_07_11_090000_create_chart_of_accounts_table', :BATCH),
--   ('2026_07_11_090100_create_journals_table', :BATCH),
--   ('2026_07_11_090200_create_journal_details_table', :BATCH),
--   ('2026_07_11_090300_add_chart_of_account_id_to_accounts_table', :BATCH);

-- =====================================================================
-- UPDATE 2026-07-14 — jalankan SEKALI SAJA setelah blok di atas sudah ada.
-- Menambahkan: (5) akun beban per kategori biaya, (6) flag "Kas/Bank" di
-- Kode Akun supaya Daftar Akun tidak perlu menu tambah akun terpisah lagi.
-- =====================================================================
START TRANSACTION;

-- ---------------------------------------------------------------------
-- 5) expense_categories.chart_of_account_id — akun Beban per kategori
--    biaya. Default: Beban Operasional (5200), sama seperti sebelumnya.
-- ---------------------------------------------------------------------
ALTER TABLE `expense_categories` ADD COLUMN `chart_of_account_id` INT NULL DEFAULT NULL AFTER `name`;

UPDATE `expense_categories`
SET `chart_of_account_id` = (SELECT id FROM `chart_of_accounts` WHERE `code` = '5200')
WHERE `chart_of_account_id` IS NULL;

-- ---------------------------------------------------------------------
-- 6) chart_of_accounts.is_cash_account — tandai akun Kas/Bank. Setiap COA
--    yang sudah punya `accounts` terkait (dibuat oleh migration 090300 di
--    atas) otomatis ditandai sebagai akun Kas/Bank.
-- ---------------------------------------------------------------------
ALTER TABLE `chart_of_accounts` ADD COLUMN `is_cash_account` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_default`;

UPDATE `chart_of_accounts`
SET `is_cash_account` = 1
WHERE `id` IN (SELECT `chart_of_account_id` FROM `accounts` WHERE `chart_of_account_id` IS NOT NULL);

COMMIT;

-- Catat kedua migration di atas sebagai "sudah dijalankan" (opsional, lihat
-- catatan batch di atas):
-- INSERT INTO `migrations` (`migration`, `batch`) VALUES
--   ('2026_07_14_090000_add_chart_of_account_id_to_expense_categories_table', :BATCH),
--   ('2026_07_14_090100_add_is_cash_account_to_chart_of_accounts_table', :BATCH);

-- =====================================================================
-- UPDATE 2026-07-14 (lanjutan) — jalankan SEKALI SAJA setelah blok di atas.
-- Menambahkan akun "Selisih Kasir" (kode 2300) yang dipakai jurnal otomatis
-- fitur Penyesuaian Stok (modul Produk): selisih stok fisik vs sistem.
-- Setara dengan migration 2026_07_14_120000_add_selisih_kasir_account.
-- =====================================================================
START TRANSACTION;

INSERT INTO `chart_of_accounts`
  (`code`, `name`, `type`, `normal_balance`, `parent_id`, `opening_balance`, `description`, `is_default`, `is_active`, `created_at`, `updated_at`)
SELECT '2300', 'Selisih Kasir (Penyesuaian Stok)', 'liability', 'credit', NULL, 0,
       'Selisih stok fisik vs sistem hasil penyesuaian stok', 1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `chart_of_accounts` WHERE `code` = '2300');

COMMIT;

-- INSERT INTO `migrations` (`migration`, `batch`) VALUES
--   ('2026_07_14_120000_add_selisih_kasir_account', :BATCH);
