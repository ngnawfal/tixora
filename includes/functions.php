<?php
// ============================================================
// TIXORA — Modul Data & Logika Bisnis
// Seluruh query menggunakan Prepared Statements (anti SQL Injection)
// dan terhubung dengan skema ternormalisasi 3NF (db_tixora).
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security.php';

/**
 * Menampilkan banner notifikasi dengan escaping penuh (anti Reflected XSS).
 */
function tampilkanPesan(?string $pesan, string $tipe = 'success'): void
{
    if (!$pesan) {
        return;
    }
    $allowed = ['success', 'danger', 'warning', 'info'];
    $safeType = in_array($tipe, $allowed, true) ? $tipe : 'info';
    echo '<div class="alert alert-' . $safeType . '" role="alert">' . e($pesan) . '</div>';
}

/**
 * Format angka rupiah.
 */
function formatRupiah(float|int|string $amount): string
{
    return 'Rp ' . number_format((float) $amount, 0, ',', '.');
}

/**
 * Format tanggal event ke Bahasa Indonesia.
 */
function formatTanggalIndo(?string $datetime, bool $withTime = false): string
{
    if (!$datetime) {
        return '-';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '-';
    }
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $d = (int) date('j', $ts);
    $m = $bulan[(int) date('n', $ts)] ?? '';
    $y = date('Y', $ts);
    if ($withTime) {
        return "{$d} {$m} {$y}, " . date('H:i', $ts) . ' WIB';
    }
    return "{$d} {$m} {$y}";
}

/**
 * Hitung harga akhir tiket setelah diskon (selalu dilakukan di server).
 */
function hitungHargaAkhir(float|int $price, int $discountPercent): float
{
    $price = max(0, (float) $price);
    $discount = max(0, min(100, $discountPercent));
    return round($price - ($price * $discount / 100), 2);
}

/**
 * Buat slug URL-friendly dari string.
 */
function slugify(string $text): string
{
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim((string) $slug, '-') ?: 'item-' . time();
}

// ============================================================
// EVENT CATALOG (v_event_catalog & events)
// ============================================================

function ambilSemuaEvent(bool $onlyPublic = false): array
{
    global $koneksi;
    $sql = 'SELECT * FROM v_event_catalog';
    if ($onlyPublic) {
        $sql .= " WHERE status IN ('active', 'sold_out')";
    }
    $sql .= ' ORDER BY event_date ASC, id_event DESC';

    $res = $koneksi->query($sql);
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function ambilEventById(int $id): ?array
{
    global $koneksi;
    $stmt = $koneksi->prepare('SELECT * FROM v_event_catalog WHERE id_event = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Upload poster event dengan validasi konten asli (finfo + getimagesize)
 * dan penamaan acak kriptografis (anti RCE / extension spoofing).
 */
function uploadPoster(?array $file): array
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'filename' => null, 'error' => null];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'filename' => null, 'error' => 'Gagal mengunggah file poster.'];
    }

    $maxSize = 2 * 1024 * 1024; // 2MB
    if (($file['size'] ?? 0) > $maxSize || ($file['size'] ?? 0) <= 0) {
        return ['ok' => false, 'filename' => null, 'error' => 'Ukuran poster maksimal 2MB.'];
    }

    $tmpPath = $file['tmp_name'] ?? '';
    if (!is_uploaded_file($tmpPath)) {
        return ['ok' => false, 'filename' => null, 'error' => 'File upload tidak valid.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $realMime = $finfo->file($tmpPath);

    $mimeToExt = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    if (!isset($mimeToExt[$realMime])) {
        return ['ok' => false, 'filename' => null, 'error' => 'Format poster harus JPG, PNG, GIF, atau WebP yang valid.'];
    }

    if (@getimagesize($tmpPath) === false) {
        return ['ok' => false, 'filename' => null, 'error' => 'File tidak dikenali sebagai gambar yang valid.'];
    }

    $ext = $mimeToExt[$realMime];
    $filename = 'poster_' . bin2hex(random_bytes(12)) . '.' . $ext;

    if (!is_dir(UPLOADS_PATH)) {
        mkdir(UPLOADS_PATH, 0755, true);
    }

    $destination = UPLOADS_PATH . $filename;
    if (!move_uploaded_file($tmpPath, $destination)) {
        return ['ok' => false, 'filename' => null, 'error' => 'Gagal menyimpan file poster ke server.'];
    }

    return ['ok' => true, 'filename' => $filename, 'error' => null];
}

function hapusFilePoster(?string $filename): void
{
    if (!$filename) {
        return;
    }
    $safeName = basename($filename);
    $fullPath = UPLOADS_PATH . $safeName;
    if ($safeName !== '' && is_file($fullPath)) {
        @unlink($fullPath);
    }
}

function tambahEvent(array $data, ?array $file = null): array
{
    global $koneksi;

    $title       = trim($data['title'] ?? '');
    $artist      = trim($data['artist'] ?? '');
    $idCategory  = (int) ($data['id_category'] ?? 0);
    $idVenue     = (int) ($data['id_venue'] ?? 0);
    $eventDateIn = trim($data['event_date'] ?? '');
    $quota       = max(0, (int) ($data['quota'] ?? 0));
    $price       = max(0, (float) ($data['price'] ?? 100000));
    $discount    = max(0, min(100, (int) ($data['discount_percent'] ?? 0)));
    $description = trim($data['description'] ?? '');

    if ($title === '' || $artist === '' || $idCategory <= 0 || $idVenue <= 0) {
        return ['ok' => false, 'error' => 'Nama event, artis, kategori, dan venue wajib diisi.'];
    }

    $ts = strtotime(str_replace('T', ' ', $eventDateIn));
    if ($ts === false) {
        return ['ok' => false, 'error' => 'Tanggal dan jam pelaksanaan event tidak valid.'];
    }
    $eventDate = date('Y-m-d H:i:s', $ts);
    $eventYear = (int) date('Y', $ts);

    $upload = uploadPoster($file);
    if (!$upload['ok']) {
        return ['ok' => false, 'error' => $upload['error']];
    }
    $poster = $upload['filename'];

    $stmt = $koneksi->prepare(
        'INSERT INTO events
            (id_category, id_venue, title, artist, event_date, event_year, quota, price, discount_percent, poster, description)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        hapusFilePoster($poster);
        return ['ok' => false, 'error' => 'Gagal menyiapkan query penyimpanan event.'];
    }

    $stmt->bind_param(
        'iisssiidiss',
        $idCategory,
        $idVenue,
        $title,
        $artist,
        $eventDate,
        $eventYear,
        $quota,
        $price,
        $discount,
        $poster,
        $description
    );
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        hapusFilePoster($poster);
        return ['ok' => false, 'error' => 'Gagal menyimpan event ke database. Pastikan kategori dan venue valid.'];
    }

    return ['ok' => true, 'error' => null];
}

function ubahEvent(int $idEvent, array $data, ?array $file = null): array
{
    global $koneksi;

    $existing = ambilEventById($idEvent);
    if (!$existing) {
        return ['ok' => false, 'error' => 'Data event tidak ditemukan.'];
    }

    $title       = trim($data['title'] ?? '');
    $artist      = trim($data['artist'] ?? '');
    $idCategory  = (int) ($data['id_category'] ?? 0);
    $idVenue     = (int) ($data['id_venue'] ?? 0);
    $eventDateIn = trim($data['event_date'] ?? '');
    $quota       = max(0, (int) ($data['quota'] ?? 0));
    $price       = max(0, (float) ($data['price'] ?? 100000));
    $discount    = max(0, min(100, (int) ($data['discount_percent'] ?? 0)));
    $description = trim($data['description'] ?? '');

    if ($title === '' || $artist === '' || $idCategory <= 0 || $idVenue <= 0) {
        return ['ok' => false, 'error' => 'Nama event, artis, kategori, dan venue wajib diisi.'];
    }

    $ts = strtotime(str_replace('T', ' ', $eventDateIn));
    if ($ts === false) {
        return ['ok' => false, 'error' => 'Tanggal dan jam pelaksanaan event tidak valid.'];
    }
    $eventDate = date('Y-m-d H:i:s', $ts);
    $eventYear = (int) date('Y', $ts);

    $upload = uploadPoster($file);
    if (!$upload['ok']) {
        return ['ok' => false, 'error' => $upload['error']];
    }

    $newPoster = $upload['filename'];
    $posterToSave = $newPoster ?? $existing['poster'];

    $stmt = $koneksi->prepare(
        'UPDATE events SET
            id_category = ?,
            id_venue = ?,
            title = ?,
            artist = ?,
            event_date = ?,
            event_year = ?,
            quota = ?,
            price = ?,
            discount_percent = ?,
            poster = ?,
            description = ?
         WHERE id_event = ?'
    );
    if (!$stmt) {
        hapusFilePoster($newPoster);
        return ['ok' => false, 'error' => 'Gagal menyiapkan query pembaruan event.'];
    }

    $stmt->bind_param(
        'iisssiidissi',
        $idCategory,
        $idVenue,
        $title,
        $artist,
        $eventDate,
        $eventYear,
        $quota,
        $price,
        $discount,
        $posterToSave,
        $description,
        $idEvent
    );
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        hapusFilePoster($newPoster);
        return ['ok' => false, 'error' => 'Gagal menyimpan perubahan event.'];
    }

    if ($newPoster && $existing['poster'] && $existing['poster'] !== $newPoster) {
        hapusFilePoster($existing['poster']);
    }

    return ['ok' => true, 'error' => null];
}

function hapusEvent(int $idEvent): array
{
    global $koneksi;

    $event = ambilEventById($idEvent);
    if (!$event) {
        return ['ok' => false, 'error' => 'Event tidak ditemukan.'];
    }

    $stmt = $koneksi->prepare('DELETE FROM events WHERE id_event = ?');
    $stmt->bind_param('i', $idEvent);
    $ok = $stmt->execute();
    $errno = $stmt->errno;
    $stmt->close();

    if (!$ok) {
        if ($errno === 1451) {
            return ['ok' => false, 'error' => 'Event tidak dapat dihapus karena masih memiliki riwayat transaksi (integritas referensial FK).'];
        }
        return ['ok' => false, 'error' => 'Gagal menghapus data event.'];
    }

    hapusFilePoster($event['poster']);
    return ['ok' => true, 'error' => null];
}

// ============================================================
// KATEGORI (categories)
// ============================================================

function ambilSemuaKategori(): array
{
    global $koneksi;
    $sql = 'SELECT c.*, COUNT(e.id_event) AS event_count
            FROM categories c
            LEFT JOIN events e ON e.id_category = c.id_category
            GROUP BY c.id_category
            ORDER BY c.name ASC';
    $res = $koneksi->query($sql);
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function tambahKategori(string $nama, string $accentColor = '#E04826'): array
{
    global $koneksi;
    $nama = trim($nama);
    if ($nama === '' || mb_strlen($nama) > 50) {
        return ['ok' => false, 'error' => 'Nama kategori wajib diisi (maks. 50 karakter).'];
    }
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $accentColor)) {
        $accentColor = '#E04826';
    }
    $slug = slugify($nama);

    $stmt = $koneksi->prepare('INSERT INTO categories (name, slug, accent_color) VALUES (?, ?, ?)');
    $stmt->bind_param('sss', $nama, $slug, $accentColor);
    $ok = $stmt->execute();
    $errno = $stmt->errno;
    $stmt->close();

    if (!$ok) {
        if ($errno === 1062) {
            return ['ok' => false, 'error' => 'Jenis event tersebut sudah tersedia.'];
        }
        return ['ok' => false, 'error' => 'Gagal menambahkan jenis event.'];
    }
    return ['ok' => true, 'error' => null];
}

function hapusKategori(int $id): array
{
    global $koneksi;
    $stmt = $koneksi->prepare('DELETE FROM categories WHERE id_category = ?');
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $errno = $stmt->errno;
    $stmt->close();

    if (!$ok) {
        if ($errno === 1451) {
            return ['ok' => false, 'error' => 'Kategori sedang digunakan oleh event aktif dan tidak dapat dihapus.'];
        }
        return ['ok' => false, 'error' => 'Gagal menghapus kategori.'];
    }
    return ['ok' => true, 'error' => null];
}

// ============================================================
// VENUE (venues)
// ============================================================

function ambilSemuaVenue(): array
{
    global $koneksi;
    $sql = 'SELECT v.*, COUNT(e.id_event) AS event_count
            FROM venues v
            LEFT JOIN events e ON e.id_venue = v.id_venue
            GROUP BY v.id_venue
            ORDER BY v.name ASC';
    $res = $koneksi->query($sql);
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function tambahVenue(string $nama, string $kota = 'Indonesia'): array
{
    global $koneksi;
    $nama = trim($nama);
    $kota = trim($kota) ?: 'Indonesia';
    if ($nama === '' || mb_strlen($nama) > 100) {
        return ['ok' => false, 'error' => 'Nama venue wajib diisi (maks. 100 karakter).'];
    }

    $stmt = $koneksi->prepare('INSERT INTO venues (name, city) VALUES (?, ?)');
    $stmt->bind_param('ss', $nama, $kota);
    $ok = $stmt->execute();
    $errno = $stmt->errno;
    $stmt->close();

    if (!$ok) {
        if ($errno === 1062) {
            return ['ok' => false, 'error' => 'Venue tersebut sudah tersedia.'];
        }
        return ['ok' => false, 'error' => 'Gagal menambahkan venue.'];
    }
    return ['ok' => true, 'error' => null];
}

function hapusVenue(int $id): array
{
    global $koneksi;
    $stmt = $koneksi->prepare('DELETE FROM venues WHERE id_venue = ?');
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $errno = $stmt->errno;
    $stmt->close();

    if (!$ok) {
        if ($errno === 1451) {
            return ['ok' => false, 'error' => 'Venue sedang digunakan oleh event aktif dan tidak dapat dihapus.'];
        }
        return ['ok' => false, 'error' => 'Gagal menghapus venue.'];
    }
    return ['ok' => true, 'error' => null];
}

// ============================================================
// STATISTIK DASHBOARD & TRANSAKSI
// ============================================================

function ambilStatistikAdmin(): array
{
    global $koneksi;
    $stats = [
        'total_events'    => 0,
        'total_quota'     => 0,
        'total_orders'    => 0,
        'paid_orders'     => 0,
        'pending_orders'  => 0,
        'total_revenue'   => 0.0,
        'tickets_sold'    => 0,
    ];

    $r1 = $koneksi->query('SELECT COUNT(*) AS cnt, COALESCE(SUM(quota), 0) AS qty FROM events');
    if ($r1 && ($row = $r1->fetch_assoc())) {
        $stats['total_events'] = (int) $row['cnt'];
        $stats['total_quota']  = (int) $row['qty'];
    }

    $r2 = $koneksi->query(
        "SELECT
            COUNT(*) AS total_orders,
            SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) AS paid_orders,
            SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) AS pending_orders,
            COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END), 0) AS revenue,
            COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN quantity ELSE 0 END), 0) AS sold
         FROM orders"
    );
    if ($r2 && ($row = $r2->fetch_assoc())) {
        $stats['total_orders']   = (int) $row['total_orders'];
        $stats['paid_orders']    = (int) $row['paid_orders'];
        $stats['pending_orders'] = (int) $row['pending_orders'];
        $stats['total_revenue']  = (float) $row['revenue'];
        $stats['tickets_sold']   = (int) $row['sold'];
    }

    return $stats;
}

/**
 * Konfirmasi pembayaran pesanan secara atomik (transaksi DB + row lock FOR UPDATE)
 * untuk mencegah double-deduction dan race condition overselling.
 */
function selesaikanPembayaranOrder(int $idOrder, string $paymentRef, ?string $paymentMethod = null): array
{
    global $koneksi;

    $koneksi->begin_transaction();
    try {
        $stmt = $koneksi->prepare(
            'SELECT id_order, id_user, id_event, quantity, payment_status
             FROM orders
             WHERE id_order = ?
             FOR UPDATE'
        );
        $stmt->bind_param('i', $idOrder);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$order) {
            $koneksi->rollback();
            return ['ok' => false, 'reason' => 'not_found'];
        }

        if ($order['payment_status'] === 'paid') {
            $koneksi->commit();
            return ['ok' => true, 'reason' => 'already_paid'];
        }

        if ($order['payment_status'] !== 'pending') {
            $koneksi->rollback();
            return ['ok' => false, 'reason' => 'invalid_state'];
        }

        // Kunci baris event & pastikan kuota masih cukup
        $evStmt = $koneksi->prepare('SELECT quota FROM events WHERE id_event = ? FOR UPDATE');
        $evStmt->bind_param('i', $order['id_event']);
        $evStmt->execute();
        $ev = $evStmt->get_result()->fetch_assoc();
        $evStmt->close();

        if (!$ev || (int) $ev['quota'] < (int) $order['quantity']) {
            $koneksi->rollback();
            return ['ok' => false, 'reason' => 'insufficient_stock'];
        }

        $updStock = $koneksi->prepare('UPDATE events SET quota = quota - ? WHERE id_event = ?');
        $updStock->bind_param('ii', $order['quantity'], $order['id_event']);
        $updStock->execute();
        $updStock->close();

        $ticketToken = generateTicketToken((int) $order['id_order'], (int) $order['id_user']);
        $methodToSave = $paymentMethod ?: 'qris';

        $updOrder = $koneksi->prepare(
            "UPDATE orders
             SET payment_status = 'paid',
                 payment_reference = ?,
                 payment_method = COALESCE(?, payment_method),
                 ticket_token = ?,
                 paid_at = NOW(),
                 updated_at = NOW()
             WHERE id_order = ?"
        );
        $updOrder->bind_param('sssi', $paymentRef, $methodToSave, $ticketToken, $idOrder);
        $updOrder->execute();
        $updOrder->close();

        $koneksi->commit();
        return ['ok' => true, 'reason' => 'paid', 'ticket_token' => $ticketToken];
    } catch (Throwable $ex) {
        $koneksi->rollback();
        error_log('[TIXORA TX ERROR] ' . $ex->getMessage());
        return ['ok' => false, 'reason' => 'exception'];
    }
}

function statusOrderMeta(string $status): array
{
    return match ($status) {
        'paid'     => ['class' => 'badge-success', 'label' => 'Lunas'],
        'pending'  => ['class' => 'badge-pending', 'label' => 'Menunggu Pembayaran'],
        'expired'  => ['class' => 'badge-muted',   'label' => 'Kedaluwarsa'],
        'canceled' => ['class' => 'badge-danger',  'label' => 'Dibatalkan'],
        default    => ['class' => 'badge-danger',  'label' => 'Gagal'],
    };
}
