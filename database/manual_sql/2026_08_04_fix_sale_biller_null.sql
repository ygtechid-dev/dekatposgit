-- 2026-08-04 : Perbaikan retur penjualan gagal
-- SQLSTATE[23000]: Column 'biller_id' cannot be null (insert into `returns`)
--
-- Penyebab: penjualan yang dibuat oleh user kasir (role_id > 2) yang belum
-- di-set biller-nya tersimpan dengan sales.biller_id = NULL. Saat diretur,
-- nilai NULL itu disalin ke tabel `returns` yang kolom biller_id-nya NOT NULL.
--
-- Script ini idempotent, aman dijalankan berulang kali.

-- 1) Isi biller_id penjualan yang NULL / 0 dengan biller default POS Setting,
--    fallback ke biller dengan id terkecil.
UPDATE `sales`
SET `biller_id` = COALESCE(
        (SELECT `biller_id` FROM `pos_setting` WHERE `biller_id` IS NOT NULL ORDER BY `id` LIMIT 1),
        (SELECT `id` FROM (SELECT MIN(`id`) AS `id` FROM `billers`) AS b)
    )
WHERE (`biller_id` IS NULL OR `biller_id` = 0)
  AND EXISTS (SELECT 1 FROM `billers`);

-- 2) Hal yang sama untuk retur lama (kalau ada baris yang lolos karena
--    MySQL non-strict mode menyimpan 0).
UPDATE `returns`
SET `biller_id` = COALESCE(
        (SELECT `biller_id` FROM `pos_setting` WHERE `biller_id` IS NOT NULL ORDER BY `id` LIMIT 1),
        (SELECT `id` FROM (SELECT MIN(`id`) AS `id` FROM `billers`) AS b)
    )
WHERE (`biller_id` IS NULL OR `biller_id` = 0)
  AND EXISTS (SELECT 1 FROM `billers`);

-- 3) Isi biller_id user yang masih kosong supaya penjualan berikutnya tidak NULL lagi.
UPDATE `users`
SET `biller_id` = COALESCE(
        (SELECT `biller_id` FROM `pos_setting` WHERE `biller_id` IS NOT NULL ORDER BY `id` LIMIT 1),
        (SELECT `id` FROM (SELECT MIN(`id`) AS `id` FROM `billers`) AS b)
    )
WHERE (`biller_id` IS NULL OR `biller_id` = 0)
  AND EXISTS (SELECT 1 FROM `billers`);

-- Cek hasil:
-- SELECT COUNT(*) FROM `sales` WHERE `biller_id` IS NULL OR `biller_id` = 0;
-- SELECT COUNT(*) FROM `users` WHERE `biller_id` IS NULL OR `biller_id` = 0;
