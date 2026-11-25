-- ============================================================
-- TIXORA — Sistem Manajemen Tiket Konser
-- Skema Basis Data Relasional Ternormalisasi (3NF)
-- Kompatibel dengan MariaDB 10.4+ (XAMPP) & MySQL 8.0/8.4+ (Laragon)
-- ============================================================

CREATE DATABASE IF NOT EXISTS `db_tixora`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `db_tixora`;

SET NAMES utf8mb4;
SET time_zone = '+07:00';

-- Hapus objek lama sesuai urutan ketergantungan FK agar idempoten
DROP VIEW IF EXISTS `v_transaction_summary`;
DROP VIEW IF EXISTS `v_event_catalog`;
DROP TRIGGER IF EXISTS `trg_events_status_before_update`;
DROP TRIGGER IF EXISTS `trg_events_status_before_insert`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `events`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `venues`;
DROP TABLE IF EXISTS `categories`;

-- ============================================================
-- 1. TABEL MASTER: categories (Genre / Jenis Event)
-- ============================================================
CREATE TABLE `categories` (
  `id_category` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `slug` VARCHAR(60) NOT NULL,
  `accent_color` VARCHAR(7) NOT NULL DEFAULT '#E04826',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_category`),
  UNIQUE KEY `uq_categories_name` (`name`),
  UNIQUE KEY `uq_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. TABEL MASTER: venues (Lokasi / Arena Konser)
-- ============================================================
CREATE TABLE `venues` (
  `id_venue` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `city` VARCHAR(60) NOT NULL DEFAULT 'Indonesia',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_venue`),
  UNIQUE KEY `uq_venues_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. TABEL UTAMA: events (Katalog Konser & Tiket)
--    Menggantikan tabel legacy 'buku' dengan relasi FK penuh (3NF)
-- ============================================================
CREATE TABLE `events` (
  `id_event` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_category` INT UNSIGNED NOT NULL,
  `id_venue` INT UNSIGNED NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `artist` VARCHAR(150) NOT NULL,
  `event_date` DATETIME NOT NULL,
  `event_year` SMALLINT UNSIGNED NOT NULL,
  `quota` INT UNSIGNED NOT NULL DEFAULT 0,
  `price` DECIMAL(12,2) NOT NULL DEFAULT 100000.00,
  `discount_percent` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `poster` VARCHAR(255) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `status` ENUM('active', 'sold_out', 'draft', 'archived') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_event`),
  KEY `idx_events_category` (`id_category`),
  KEY `idx_events_venue` (`id_venue`),
  KEY `idx_events_status_date` (`status`, `event_date`),
  CONSTRAINT `fk_events_category`
    FOREIGN KEY (`id_category`) REFERENCES `categories` (`id_category`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_events_venue`
    FOREIGN KEY (`id_venue`) REFERENCES `venues` (`id_venue`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_events_price` CHECK (`price` >= 0),
  CONSTRAINT `chk_events_discount` CHECK (`discount_percent` <= 100),
  CONSTRAINT `chk_events_year` CHECK (`event_year` BETWEEN 2000 AND 2100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. TABEL PENGGUNA: users (Autentikasi & Otorisasi)
-- ============================================================
CREATE TABLE `users` (
  `id_user` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `email` VARCHAR(120) NOT NULL,
  `phone` VARCHAR(25) DEFAULT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `role` ENUM('admin', 'user') NOT NULL DEFAULT 'user',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_user`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. TABEL TRANSAKSI: orders (Pemesanan & Pembayaran Tiket)
--    Menyimpan snapshot harga & diskon saat transaksi dibuat
-- ============================================================
CREATE TABLE `orders` (
  `id_order` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_code` VARCHAR(32) NOT NULL,
  `id_user` INT UNSIGNED NOT NULL,
  `id_event` INT UNSIGNED NOT NULL,
  `quantity` INT UNSIGNED NOT NULL,
  `unit_price` DECIMAL(12,2) NOT NULL,
  `discount_percent` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `total_amount` DECIMAL(12,2) NOT NULL,
  `payment_status` ENUM('pending', 'paid', 'failed', 'expired', 'canceled') NOT NULL DEFAULT 'pending',
  `payment_method` VARCHAR(50) DEFAULT NULL,
  `payment_reference` VARCHAR(100) DEFAULT NULL,
  `payment_url` VARCHAR(255) DEFAULT NULL,
  `ticket_token` VARCHAR(64) DEFAULT NULL,
  `buyer_name` VARCHAR(100) NOT NULL,
  `buyer_email` VARCHAR(120) NOT NULL,
  `buyer_phone` VARCHAR(25) NOT NULL,
  `paid_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_order`),
  UNIQUE KEY `uq_orders_code` (`order_code`),
  UNIQUE KEY `uq_orders_ticket_token` (`ticket_token`),
  KEY `idx_orders_user_created` (`id_user`, `created_at`),
  KEY `idx_orders_event` (`id_event`),
  KEY `idx_orders_status_created` (`payment_status`, `created_at`),
  KEY `idx_orders_payment_ref` (`payment_reference`),
  CONSTRAINT `fk_orders_user`
    FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_orders_event`
    FOREIGN KEY (`id_event`) REFERENCES `events` (`id_event`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_orders_qty` CHECK (`quantity` > 0),
  CONSTRAINT `chk_orders_unit_price` CHECK (`unit_price` >= 0),
  CONSTRAINT `chk_orders_total` CHECK (`total_amount` >= 0),
  CONSTRAINT `chk_orders_discount` CHECK (`discount_percent` <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TRIGGERS: Sinkronisasi otomatis status stok event
-- ============================================================
DELIMITER $$
CREATE TRIGGER `trg_events_status_before_insert`
BEFORE INSERT ON `events`
FOR EACH ROW
BEGIN
  IF NEW.`status` IN ('active', 'sold_out') THEN
    IF NEW.`quota` = 0 THEN
      SET NEW.`status` = 'sold_out';
    ELSE
      SET NEW.`status` = 'active';
    END IF;
  END IF;
END$$

CREATE TRIGGER `trg_events_status_before_update`
BEFORE UPDATE ON `events`
FOR EACH ROW
BEGIN
  IF NEW.`status` IN ('active', 'sold_out') THEN
    IF NEW.`quota` = 0 THEN
      SET NEW.`status` = 'sold_out';
    ELSE
      SET NEW.`status` = 'active';
    END IF;
  END IF;
END$$
DELIMITER ;

-- ============================================================
-- VIEWS: Katalog Event & Ringkasan Transaksi
-- ============================================================
CREATE VIEW `v_event_catalog` AS
SELECT
  e.`id_event`,
  e.`id_category`,
  c.`name` AS `category_name`,
  c.`slug` AS `category_slug`,
  c.`accent_color`,
  e.`id_venue`,
  v.`name` AS `venue_name`,
  v.`city` AS `venue_city`,
  e.`title`,
  e.`artist`,
  e.`event_date`,
  e.`event_year`,
  e.`quota`,
  e.`price`,
  e.`discount_percent`,
  ROUND(e.`price` - (e.`price` * e.`discount_percent` / 100), 2) AS `final_price`,
  e.`poster`,
  e.`description`,
  e.`status`,
  CASE
    WHEN e.`quota` = 0 THEN 'Sold Out'
    WHEN e.`quota` <= 10 THEN 'Hampir Habis'
    ELSE 'Tersedia'
  END AS `availability_label`,
  e.`created_at`,
  e.`updated_at`
FROM `events` e
JOIN `categories` c ON e.`id_category` = c.`id_category`
JOIN `venues` v ON e.`id_venue` = v.`id_venue`;

CREATE VIEW `v_transaction_summary` AS
SELECT
  o.`id_order`,
  o.`order_code`,
  o.`id_user`,
  u.`username`,
  u.`full_name` AS `user_full_name`,
  u.`email` AS `user_email`,
  o.`id_event`,
  e.`title` AS `event_title`,
  e.`artist` AS `event_artist`,
  e.`event_date`,
  e.`poster` AS `event_poster`,
  c.`name` AS `category_name`,
  c.`accent_color`,
  v.`name` AS `venue_name`,
  v.`city` AS `venue_city`,
  o.`quantity`,
  o.`unit_price`,
  o.`discount_percent`,
  o.`total_amount`,
  o.`payment_status`,
  o.`payment_method`,
  o.`payment_reference`,
  o.`payment_url`,
  o.`ticket_token`,
  o.`buyer_name`,
  o.`buyer_email`,
  o.`buyer_phone`,
  o.`paid_at`,
  o.`created_at`,
  o.`updated_at`
FROM `orders` o
JOIN `users` u ON o.`id_user` = u.`id_user`
JOIN `events` e ON o.`id_event` = e.`id_event`
JOIN `categories` c ON e.`id_category` = c.`id_category`
JOIN `venues` v ON e.`id_venue` = v.`id_venue`;

-- ============================================================
-- DATA AWAL (Dimigrasikan & dibersihkan dari nawfdev_uts_ppll)
-- ============================================================

INSERT INTO `categories` (`id_category`, `name`, `slug`, `accent_color`, `created_at`) VALUES
(1, 'Rock',    'rock',    '#C42B2B', '2026-04-29 05:34:11'),
(2, 'Pop',     'pop',     '#B8326A', '2026-04-29 05:34:11'),
(3, 'Jazz',    'jazz',    '#2B5B84', '2026-04-29 05:34:11'),
(4, 'Hip Hop', 'hip-hop', '#B26408', '2026-04-29 05:34:11'),
(5, 'EDM',     'edm',     '#6B42C2', '2026-04-29 05:34:11'),
(6, 'Metal',   'metal',   '#3A3A38', '2026-04-29 05:34:11'),
(7, 'Indie',   'indie',   '#157A52', '2026-04-29 05:34:11'),
(8, 'Dangdut', 'dangdut', '#C9541C', '2026-04-29 05:34:11'),
(9, 'K-Pop',   'k-pop',   '#AD2E62', '2026-04-29 05:34:11'),
(10,'Reggae',  'reggae',  '#26733D', '2026-04-29 05:34:11');

INSERT INTO `venues` (`id_venue`, `name`, `city`, `created_at`) VALUES
(1,  'JIExpo Kemayoran',             'Jakarta Pusat', '2026-04-29 05:34:11'),
(2,  'GBK Senayan',                  'Jakarta Selatan', '2026-04-29 05:34:11'),
(3,  'Istora Senayan',               'Jakarta Selatan', '2026-04-29 05:34:11'),
(4,  'Parkir Timur Senayan',         'Jakarta Selatan', '2026-04-29 05:34:11'),
(5,  'Gambir Expo Jakarta',          'Jakarta Pusat', '2026-04-29 05:34:11'),
(6,  'ICE BSD',                      'Tangerang', '2026-04-29 05:34:11'),
(7,  'Candi Prambanan',              'Yogyakarta', '2026-04-29 05:34:11'),
(8,  'Pantai Carnaval Bali',         'Bali', '2026-04-29 05:34:11'),
(9,  'Stadion Kridosono Yogyakarta', 'Yogyakarta', '2026-04-29 05:34:11'),
(10, 'The Kasablanka Hall',          'Jakarta Selatan', '2026-04-29 05:34:11');

INSERT INTO `events` (`id_event`, `id_category`, `id_venue`, `title`, `artist`, `event_date`, `event_year`, `quota`, `price`, `discount_percent`, `poster`, `description`, `status`, `created_at`) VALUES
(1,  3, 1, 'Java Jazz Festival 2026',      'Dewa 19, Sheila on 7, Tulus',                   '2026-05-29 19:00:00', 2026,  5000, 150000.00,   0, 'java-jazz.jpg',      'Festival jazz internasional terbesar di Asia Tenggara yang mempertemukan musisi legendaris Indonesia dan mancanegara dalam panggung multi-arena.', 'active', '2026-04-29 05:34:11'),
(2,  4, 2, 'We The Fest 2026',             'Rich Brian, NIKI, Warren Hue',                  '2026-07-18 18:00:00', 2026,  8000, 180000.00,   0, 'we-the-fest.jpg',    'Festival musik, seni, fesyen, dan kuliner musim panas tahunan dengan kurasi line-up hip hop dan R&B kelas dunia.', 'active', '2026-04-29 05:34:11'),
(3,  1, 8, 'Soundrenaline 2026',           'Superman Is Dead, Burgerkill, Koil',            '2026-09-05 18:30:00', 2026, 12000, 200000.00,   0, 'soundrenaline.jpg',  'Selebrasi musik rock dan alternatif di tepi pantai Bali dengan tata suara bertenaga tinggi dan kolaborasi lintas generasi.', 'active', '2026-04-29 05:34:11'),
(4,  3, 7, 'Prambanan Jazz 2026',          'Tompi, Andien, Glenn Fredly Tribute',           '2026-07-04 19:00:00', 2026,  3000, 150000.00,   0, 'prambanan-jazz.jpg', 'Perpaduan harmonis antara mahakarya candi bersejarah dan alunan musik jazz modern di bawah langit malam Yogyakarta.', 'active', '2026-04-29 05:34:11'),
(5,  6, 5, 'Hammersonic Festival 2026',    'Slipknot, Lamb of God, Trivium',                '2026-05-03 18:00:00', 2026, 15000, 200000.00,   0, 'hammersonic.jpg',    'Ajang festival musik metal terbesar di Asia Pasifik dengan produksi panggung spektakuler dan sound system berstandar tur dunia.', 'active', '2026-04-29 05:34:11'),
(6,  7, 2, 'Synchronize Fest 2026',        'Fourtwnty, Payung Teduh, Efek Rumah Kaca',      '2026-10-02 18:30:00', 2026, 10000, 150000.00,   0, 'synchronize.jpg',    'Festival musik multi-genre berskala nasional yang merayakan keberagaman karya musisi independen tanah air.', 'active', '2026-04-29 05:34:11'),
(7,  2, 3, 'Pestapora 2026',               'Tulus, Isyana Sarasvati, Raisa',                '2026-09-19 19:00:00', 2026,  7500, 175000.00,   0, 'pestapora.jpg',      'Pesta musik tiga hari dengan deretan solois dan band pop terbaik Indonesia dalam atmosfer hangat penuh energi.', 'active', '2026-04-29 05:34:11'),
(8,  5, 1, 'Djakarta Warehouse Project',   'Martin Garrix, Alesso, KSHMR',                  '2026-12-12 20:00:00', 2026, 20000, 250000.00,   0, 'dwp.jpg',            'Festival musik elektronik terbesar di Asia dengan visual laser 360 derajat, piroteknik, dan jajaran DJ papan atas dunia.', 'active', '2026-04-29 05:34:11'),
(9,  7, 4, 'The Sounds Project',           'Pamungkas, Hindia, Sal Priadi',                 '2026-08-08 18:00:00', 2026,  5998, 150000.00, 100, 'sounds-project.jpg', 'Edisi spesial promo 100% gratis untuk komunitas musik indie dengan penampilan intim dari musisi favorit masa kini.', 'active', '2026-04-29 05:34:11'),
(10, 1, 9, 'Jogjarockarta 2026',           'Slank, Navicula, Seringai',                     '2026-11-14 19:30:00', 2026,  8500, 200000.00,  97, 'jogjarockarta.jpg',  'Festival rock ikonik di jantung Yogyakarta yang menghadirkan energi distorsi penuh dengan penawaran diskon spesial.', 'active', '2026-04-29 05:34:11');

-- Akun demo bawaan (password default untuk kedua akun demo: password)
-- Segera ubah password melalui menu Profil saat deploy ke lingkungan produksi.
INSERT INTO `users` (`id_user`, `username`, `password_hash`, `email`, `phone`, `full_name`, `role`, `created_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@tixora.id',  '081200000001', 'Administrator', 'admin', '2026-04-29 16:52:31'),
(2, 'user',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user@example.com', '081234567890', 'User Demo',     'user',  '2026-04-29 16:59:53');

-- Contoh transaksi awal (seluruh referensi FK valid)
INSERT INTO `orders` (`id_order`, `order_code`, `id_user`, `id_event`, `quantity`, `unit_price`, `discount_percent`, `total_amount`, `payment_status`, `payment_method`, `payment_reference`, `payment_url`, `ticket_token`, `buyer_name`, `buyer_email`, `buyer_phone`, `paid_at`, `created_at`, `updated_at`) VALUES
(1,  'TXR-20260430-0001', 1, 10, 1, 200000.00,  97, 6000.00, 'canceled', 'qris', 'BAYAR-1777521440-09E1C1', NULL, NULL, 'Administrator', 'admin@tixora.id',  '081200000001', NULL,                  '2026-04-30 03:57:19', '2026-04-30 04:10:09'),
(2,  'TXR-20260430-0002', 1, 10, 1, 200000.00,  97, 6000.00, 'canceled', 'qris', 'BAYAR-1777521617-F5F5CE', NULL, NULL, 'Administrator', 'admin@tixora.id',  '081200000001', NULL,                  '2026-04-30 04:00:16', '2026-04-30 04:10:04'),
(3,  'TXR-20260430-0003', 1, 10, 1, 200000.00,  97, 6000.00, 'canceled', 'qris', 'BAYAR-1777521701-CD102E', NULL, NULL, 'Administrator', 'admin@tixora.id',  '081200000001', NULL,                  '2026-04-30 04:01:40', '2026-04-30 04:08:13'),
(4,  'TXR-20260430-0004', 1, 10, 1, 200000.00,  97, 6000.00, 'canceled', 'qris', 'BAYAR-1777521763-26B0CE', NULL, NULL, 'Administrator', 'admin@tixora.id',  '081200000001', NULL,                  '2026-04-30 04:02:42', '2026-04-30 04:08:18'),
(5,  'TXR-20260430-0005', 1,  9, 1, 150000.00, 100,    0.00, 'paid',     'promo','PROMO-100',               NULL, 'TXR-PASS-0005-A9F2C4D8', 'Administrator', 'admin@tixora.id',  '081200000001', '2026-04-30 04:32:52', '2026-04-30 04:32:52', '2026-04-30 04:32:52'),
(6,  'TXR-20260430-0006', 1,  9, 1, 150000.00, 100,    0.00, 'paid',     'promo','PROMO-100',               NULL, 'TXR-PASS-0006-E7B19D3A', 'Administrator', 'admin@tixora.id',  '081200000001', '2026-04-30 04:42:16', '2026-04-30 04:42:16', '2026-04-30 04:42:16'),
(7,  'TXR-20260501-0007', 2, 10, 1, 200000.00,  97, 6000.00, 'canceled', 'qris', 'BAYAR-1777636782-4EFBBB', NULL, NULL, 'User Demo',     'user@example.com', '081234567890', NULL,                  '2026-05-01 11:59:40', '2026-05-03 11:35:32'),
(8,  'TXR-20260830-0008', 2, 10, 1, 200000.00,  97, 6000.00, 'canceled', 'qris', 'PENDING-14',              NULL, NULL, 'User Demo',     'user@example.com', '081234567890', NULL,                  '2026-08-30 01:08:22', '2026-08-31 01:29:51'),
(9,  'TXR-20260830-0009', 2, 10, 1, 200000.00,  97, 6000.00, 'canceled', 'qris', 'PENDING-15',              NULL, NULL, 'User Demo',     'user@example.com', '081234567890', NULL,                  '2026-08-30 01:08:28', '2026-08-31 01:29:51'),
(10, 'TXR-20260830-0010', 2, 10, 1, 200000.00,  97, 6000.00, 'canceled', 'qris', 'PENDING-16',              NULL, NULL, 'User Demo',     'user@example.com', '081234567890', NULL,                  '2026-08-30 01:08:32', '2026-08-31 01:29:51');
