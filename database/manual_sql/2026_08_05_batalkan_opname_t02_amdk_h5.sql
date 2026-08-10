-- ============================================================================
-- Batalkan penyesuaian stok (opname) yang dobel: adr-20260805-043443 di T02.
--
-- PILIHAN UTAMA: hapus penyesuaian ini lewat menu Penyesuaian di aplikasi.
-- Tombol hapus di aplikasi sudah mengembalikan stok DAN membalik jurnal
-- secara otomatis. File ini hanya untuk kondisi darurat kalau menu hapusnya
-- tidak bisa dipakai.
--
-- Aman dijalankan berulang: setelah baris adjustments-nya terhapus, @adj
-- bernilai NULL sehingga semua perintah di bawah tidak mengenai baris apa pun.
--
-- BACKUP DULU sebelum menjalankan.
-- ============================================================================

SET @ref := 'adr-20260805-043443';
SET @adj := (SELECT id FROM adjustments WHERE reference_no = @ref LIMIT 1);

-- Sebelum: lihat kondisi saat ini
SELECT pw.warehouse_id, w.name AS gudang, pw.qty, p.qty AS qty_global
FROM product_warehouse pw
JOIN warehouses w ON w.id = pw.warehouse_id
JOIN products p ON p.id = pw.product_id
WHERE pw.product_id IN (SELECT product_id FROM product_adjustments WHERE adjustment_id = @adj);

-- 1. Kembalikan stok per gudang (action '+' dikurangi, action '-' ditambah).
UPDATE product_warehouse pw
JOIN product_adjustments pa ON pa.product_id = pw.product_id
JOIN adjustments a ON a.id = pa.adjustment_id
SET pw.qty = pw.qty + IF(pa.action = '+', -pa.qty, pa.qty)
WHERE a.id = @adj
  AND pw.warehouse_id = a.warehouse_id
  AND ((pa.variant_id IS NULL AND pw.variant_id IS NULL) OR pw.variant_id = pa.variant_id);

-- 2. Kembalikan qty global di tabel products.
UPDATE products p
JOIN product_adjustments pa ON pa.product_id = p.id
JOIN adjustments a ON a.id = pa.adjustment_id
SET p.qty = p.qty + IF(pa.action = '+', -pa.qty, pa.qty)
WHERE a.id = @adj;

-- 3. Hapus jurnal "Selisih Stok" yang dibuat penyesuaian ini.
DELETE jd FROM journal_details jd
JOIN journals j ON j.id = jd.journal_id
WHERE j.reference_type = 'adjustment' AND j.reference_id = @adj;

DELETE FROM journals
WHERE reference_type = 'adjustment' AND reference_id = @adj;

-- 4. Hapus baris penyesuaiannya.
DELETE FROM product_adjustments WHERE adjustment_id = @adj;
DELETE FROM adjustments WHERE id = @adj;

-- Sesudah: verifikasi. Stok T02 harus kembali 0 dan products.qty jadi 10.
SELECT pw.warehouse_id, w.name AS gudang, pw.rack_id, pw.qty, p.qty AS qty_global
FROM product_warehouse pw
JOIN warehouses w ON w.id = pw.warehouse_id
JOIN products p ON p.id = pw.product_id
WHERE p.name LIKE '%AMDK%H5%';
