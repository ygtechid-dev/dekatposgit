-- =====================================================================
-- SalePro Product Module — manual SQL (setara dengan 9 file migration
-- tanggal 2026-07-14, tanpa perlu `php artisan migrate`).
-- Jalankan SEKALI SAJA di database SalePro kamu (phpMyAdmin / Adminer / mysql CLI).
-- Ganti prefix tabel jika perlu (default: tanpa prefix).
-- =====================================================================

START TRANSACTION;

-- ---------------------------------------------------------------------
-- 1) products — tambah field Mode Harga (Satu Harga/Satuan/Level/Jumlah)
--    + Proc %, Poin, Komisi Sales untuk mode "Satu Harga".
-- ---------------------------------------------------------------------
ALTER TABLE `products`
  ADD COLUMN `price_mode` VARCHAR(255) NOT NULL DEFAULT 'single' AFTER `wholesale_price`,
  ADD COLUMN `markup_percent` DOUBLE NULL DEFAULT NULL AFTER `price_mode`,
  ADD COLUMN `point` DOUBLE NULL DEFAULT NULL AFTER `markup_percent`,
  ADD COLUMN `commission` DOUBLE NULL DEFAULT NULL AFTER `point`;

-- ---------------------------------------------------------------------
-- 2) product_units — 1 baris per (product, unit) untuk mode Satuan/Level/Jumlah
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_units` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT NOT NULL,
  `unit_id` INT NOT NULL,
  `cost` DOUBLE NULL DEFAULT NULL,
  `markup_percent` DOUBLE NULL DEFAULT NULL,
  `price` DOUBLE NULL DEFAULT NULL,
  `point` DOUBLE NULL DEFAULT NULL,
  `commission` DOUBLE NULL DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_units_product_id_unit_id_unique` (`product_id`, `unit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 3) product_unit_price_levels — mode "Level Harga" (per Customer Group)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_unit_price_levels` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_unit_id` INT NOT NULL,
  `customer_group_id` INT NOT NULL,
  `markup_percent` DOUBLE NULL DEFAULT NULL,
  `price` DOUBLE NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pupl_unit_group_unique` (`product_unit_id`, `customer_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 4) product_unit_price_tiers — mode "Jumlah" (tier qty 1-4)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_unit_price_tiers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_unit_id` INT NOT NULL,
  `tier_no` TINYINT UNSIGNED NOT NULL,
  `min_qty` DOUBLE NULL DEFAULT NULL,
  `markup_percent` DOUBLE NULL DEFAULT NULL,
  `price` DOUBLE NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `puptier_unit_tier_unique` (`product_unit_id`, `tier_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 5) warehouses — tambah tipe (Gudang/Toko)
-- ---------------------------------------------------------------------
ALTER TABLE `warehouses`
  ADD COLUMN `type` VARCHAR(255) NOT NULL DEFAULT 'gudang' AFTER `address`;

-- ---------------------------------------------------------------------
-- 6) racks — rak per gudang/toko
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `racks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `warehouse_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 7) product_warehouse — tambah rack_id (lokasi rak stok per gudang/toko)
-- ---------------------------------------------------------------------
ALTER TABLE `product_warehouse`
  ADD COLUMN `rack_id` INT NULL DEFAULT NULL AFTER `warehouse_id`;

-- ---------------------------------------------------------------------
-- 8) product_adjustments — tambah rack_id (rak yang dihitung fisik)
-- ---------------------------------------------------------------------
ALTER TABLE `product_adjustments`
  ADD COLUMN `rack_id` INT NULL DEFAULT NULL AFTER `product_id`;

-- ---------------------------------------------------------------------
-- 9) chart_of_accounts — akun "Selisih Kasir" (2300, liability) untuk
--    jurnal otomatis Penyesuaian Stok (stok fisik vs sistem).
-- ---------------------------------------------------------------------
INSERT INTO `chart_of_accounts`
  (`code`, `name`, `type`, `normal_balance`, `parent_id`, `opening_balance`, `description`, `is_default`, `is_active`, `created_at`, `updated_at`)
SELECT '2300', 'Selisih Kasir (Penyesuaian Stok)', 'liability', 'credit', NULL, 0,
       'Selisih stok fisik vs sistem hasil penyesuaian stok', 1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `chart_of_accounts` WHERE `code` = '2300');

COMMIT;

-- ---------------------------------------------------------------------
-- (Opsional) catat ke tabel `migrations` supaya Laravel tidak bingung
-- kalau suatu saat kamu jalankan `php artisan migrate` juga.
-- Cek dulu batch terakhir: SELECT MAX(batch) FROM migrations;
-- lalu ganti :BATCH di bawah dengan (hasil query + 1).
-- ---------------------------------------------------------------------
-- INSERT INTO `migrations` (`migration`, `batch`) VALUES
--   ('2026_07_14_100000_add_price_mode_fields_to_products_table', :BATCH),
--   ('2026_07_14_100100_create_product_units_table', :BATCH),
--   ('2026_07_14_100200_create_product_unit_price_levels_table', :BATCH),
--   ('2026_07_14_100300_create_product_unit_price_tiers_table', :BATCH),
--   ('2026_07_14_110000_add_type_to_warehouses_table', :BATCH),
--   ('2026_07_14_110100_create_racks_table', :BATCH),
--   ('2026_07_14_110200_add_rack_id_to_product_warehouse_table', :BATCH),
--   ('2026_07_14_110300_add_rack_id_to_product_adjustments_table', :BATCH),
--   ('2026_07_14_120000_add_selisih_kasir_account', :BATCH);
