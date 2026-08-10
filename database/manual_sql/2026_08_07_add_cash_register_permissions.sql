-- 2026-08-07 : Izin akses modul Kasir (Cash Register)
--
-- Sebelumnya modul Cash Register sama sekali tidak punya permission: menu
-- sidebar-nya dirender tanpa gate dan CashRegisterController hanya membedakan
-- role_id <= 2 (admin) vs > 2 (kasir). Akibatnya baris "Kasir" tidak pernah
-- muncul di halaman Group Permission.
--
-- Script ini membuat 4 permission baru dan memberikannya ke SEMUA role yang
-- ada sekarang, supaya setelah update tidak ada kasir yang kehilangan akses.
-- Admin bisa mencabutnya lagi lewat halaman Group Permission.
--
-- Pemetaan kolom di halaman Group Permission:
--   cash_register-index  = Lihat   -> membuka menu & halaman Cash Register
--   cash_register-add    = Tambah  -> membuka kasir (isi Modal Awal Kasir)
--   cash_register-edit   = Edit    -> tutup & setor kasir / revisi setoran
--   cash_register-delete = Hapus   -> approve setoran (tetap khusus role_id <= 2)
--
-- Script ini idempotent, aman dijalankan berulang kali.

-- 1) Buat permission-nya kalau belum ada.
INSERT INTO `permissions` (`name`, `guard_name`, `created_at`, `updated_at`)
SELECT 'cash_register-index', 'web', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id` FROM `permissions` WHERE `name` = 'cash_register-index') AS x);

INSERT INTO `permissions` (`name`, `guard_name`, `created_at`, `updated_at`)
SELECT 'cash_register-add', 'web', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id` FROM `permissions` WHERE `name` = 'cash_register-add') AS x);

INSERT INTO `permissions` (`name`, `guard_name`, `created_at`, `updated_at`)
SELECT 'cash_register-edit', 'web', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id` FROM `permissions` WHERE `name` = 'cash_register-edit') AS x);

INSERT INTO `permissions` (`name`, `guard_name`, `created_at`, `updated_at`)
SELECT 'cash_register-delete', 'web', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id` FROM `permissions` WHERE `name` = 'cash_register-delete') AS x);

-- 2) Berikan Lihat / Tambah / Edit ke semua role (kasir tetap bisa buka & tutup
--    kasirnya sendiri seperti sebelum update).
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`)
SELECT p.`id`, r.`id`
FROM `permissions` p
CROSS JOIN `roles` r
WHERE p.`name` IN ('cash_register-index', 'cash_register-add', 'cash_register-edit')
  AND NOT EXISTS (
      SELECT 1 FROM `role_has_permissions` rp
      WHERE rp.`permission_id` = p.`id` AND rp.`role_id` = r.`id`
  );

-- 3) Approve setoran hanya untuk role admin/owner (id 1 dan 2).
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`)
SELECT p.`id`, r.`id`
FROM `permissions` p
CROSS JOIN `roles` r
WHERE p.`name` = 'cash_register-delete'
  AND r.`id` IN (1, 2)
  AND NOT EXISTS (
      SELECT 1 FROM `role_has_permissions` rp
      WHERE rp.`permission_id` = p.`id` AND rp.`role_id` = r.`id`
  );

-- 4) Setelah menjalankan script ini, bersihkan cache izin.
--    Middleware Common menyimpan `role_has_permissions_list{role_id}` selama 1 tahun
--    dan Spatie punya cache sendiri, jadi izin baru bisa belum terlihat.
--    CACHE_DRIVER di .env menentukan caranya:
--      array    -> tidak ada yang perlu dibersihkan (cache hanya per-request)
--      file     -> hapus isi folder storage/framework/cache/data
--      database -> DELETE FROM `cache`;
--    Halaman Group Permission juga sudah membersihkan cache ini otomatis setiap
--    kali disimpan (perbaikan 2026-08-07 di RoleController@setPermission).
