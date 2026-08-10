-- Tambah akun COA "Diskon / Potongan Pembelian" (5410) dan "Biaya Kirim Pembelian" (5210)
-- Setara dengan migration: 2026_07_16_090000_add_purchase_discount_and_shipping_accounts.php
-- Aman dijalankan berulang kali (skip kalau code sudah ada).

INSERT INTO chart_of_accounts
    (code, name, type, normal_balance, parent_id, opening_balance, description, is_default, is_active, created_at, updated_at)
SELECT '5410', 'Diskon / Potongan Pembelian', 'expense', 'credit', NULL, 0, NULL, 1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM chart_of_accounts WHERE code = '5410');

INSERT INTO chart_of_accounts
    (code, name, type, normal_balance, parent_id, opening_balance, description, is_default, is_active, created_at, updated_at)
SELECT '5210', 'Biaya Kirim Pembelian', 'expense', 'debit', NULL, 0, NULL, 1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM chart_of_accounts WHERE code = '5210');

-- Rollback (kalau perlu dibatalkan):
-- DELETE FROM chart_of_accounts WHERE code IN ('5410', '5210');
