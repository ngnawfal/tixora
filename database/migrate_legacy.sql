-- ============================================================
-- TIXORA — Script Migrasi dari Database Legacy (nawfdev_uts_ppll)
-- Jalankan setelah struktur db_tixora dibuat (tixora.sql bagian DDL)
-- apabila ingin memigrasikan data langsung dari DB lama di server.
-- ============================================================

-- 1. Migrasi kategori
INSERT IGNORE INTO `db_tixora`.`categories` (`id_category`, `name`, `slug`, `created_at`)
SELECT
  `id_kategori`,
  `nama_kategori`,
  LOWER(REPLACE(`nama_kategori`, ' ', '-')),
  `tanggal_dibuat`
FROM `nawfdev_uts_ppll`.`kategori`;

-- 2. Migrasi venue (sebelumnya tabel 'rak')
INSERT IGNORE INTO `db_tixora`.`venues` (`id_venue`, `name`, `created_at`)
SELECT `id_rak`, `nama_rak`, `tanggal_dibuat`
FROM `nawfdev_uts_ppll`.`rak`;

-- 3. Migrasi events (sebelumnya tabel 'buku') dengan pemetaan FK
INSERT IGNORE INTO `db_tixora`.`events`
  (`id_event`, `id_category`, `id_venue`, `title`, `artist`, `event_date`, `event_year`, `quota`, `price`, `discount_percent`, `poster`, `description`, `created_at`)
SELECT
  b.`id_buku`,
  c.`id_category`,
  v.`id_venue`,
  b.`judul_buku`,
  b.`penulis`,
  STR_TO_DATE(CONCAT(b.`tahun_terbit`, '-08-15 19:00:00'), '%Y-%m-%d %H:%i:%s'),
  b.`tahun_terbit`,
  GREATEST(b.`jumlah`, 0),
  GREATEST(COALESCE(b.`harga`, 100000), 0),
  LEAST(GREATEST(COALESCE(b.`diskon`, 0), 0), 100),
  b.`cover`,
  NULLIF(b.`deskripsi`, ''),
  b.`tanggal_input`
FROM `nawfdev_uts_ppll`.`buku` b
JOIN `db_tixora`.`categories` c ON c.`name` = b.`kategori`
JOIN `db_tixora`.`venues` v ON v.`name` = b.`rak`;

-- 4. Migrasi users (normalisasi role 'public' -> 'user')
INSERT IGNORE INTO `db_tixora`.`users`
  (`id_user`, `username`, `password_hash`, `email`, `phone`, `full_name`, `role`, `created_at`)
SELECT
  `id_user`,
  `username`,
  `password`,
  `email`,
  `no_hp`,
  `nama_lengkap`,
  IF(`role` = 'admin', 'admin', 'user'),
  `created_at`
FROM `nawfdev_uts_ppll`.`users`;

-- 5. Migrasi orders yang referensinya valid (menyaring orphan rows)
INSERT IGNORE INTO `db_tixora`.`orders`
  (`id_order`, `order_code`, `id_user`, `id_event`, `quantity`, `unit_price`, `discount_percent`, `total_amount`, `payment_status`, `payment_method`, `payment_reference`, `buyer_name`, `buyer_email`, `buyer_phone`, `paid_at`, `created_at`, `updated_at`)
SELECT
  o.`id_order`,
  CONCAT('TXR-', DATE_FORMAT(o.`created_at`, '%Y%m%d'), '-', LPAD(o.`id_order`, 4, '0')),
  o.`id_user`,
  o.`id_buku`,
  GREATEST(o.`jumlah_tiket`, 1),
  e.`price`,
  e.`discount_percent`,
  GREATEST(o.`total_harga`, 0),
  IF(o.`status_pembayaran` = 'success', 'paid', o.`status_pembayaran`),
  o.`payment_method`,
  o.`payment_reference`,
  o.`nama_pembeli`,
  o.`email_pembeli`,
  o.`no_hp`,
  IF(o.`status_pembayaran` IN ('paid', 'success'), o.`updated_at`, NULL),
  o.`created_at`,
  o.`updated_at`
FROM `nawfdev_uts_ppll`.`orders` o
JOIN `db_tixora`.`users` u ON u.`id_user` = o.`id_user`
JOIN `db_tixora`.`events` e ON e.`id_event` = o.`id_buku`;
