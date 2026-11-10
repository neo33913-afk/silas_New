<?php
require __DIR__ . '/auth.php';
require_role('petugas');

// Deteksi kolom nama pada tabel users (nama kolom bisa berbeda-beda)
$kolomNama = 'id_users';
try {
    $cols = array_column(q($pdo, 'SHOW COLUMNS FROM users'), 'Field');
    foreach (['nama_users', 'nama', 'nama_lengkap', 'username'] as $c) {
        if (in_array($c, $cols, true)) { $kolomNama = $c; break; }
    }
} catch (Throwable $e) {}

$nama = $_SESSION['nama'] ?? 'Petugas';

$AKTIF = "LOWER(p.status) = 'dipinjam'";
$one = fn(string $sql) => (int) q1($pdo, $sql)['c'];

$menunggu = $one("SELECT COUNT(*) c FROM peminjaman p WHERE LOWER(p.status) IN ('menunggu','diajukan')");
$dipinjam = $one("SELECT COUNT(*) c FROM peminjaman p WHERE $AKTIF");
$hariIni  = $one("SELECT COUNT(*) c FROM peminjaman p WHERE $AKTIF AND p.akan_kembali = CURDATE()");
$telat    = $one("SELECT COUNT(*) c FROM peminjaman p WHERE $AKTIF AND p.akan_kembali < CURDATE()");

// Ketersediaan laptop
$total = $one('SELECT COUNT(*) c FROM laptops');
$siap  = $one("SELECT COUNT(*) c FROM laptops WHERE LOWER(status) IN ('tersedia','siap')");
$pinj  = $one("SELECT COUNT(*) c FROM laptops WHERE LOWER(status) = 'dipinjam'");
$servis = $one("SELECT COUNT(*) c FROM laptops WHERE LOWER(status) IN ('perbaikan','diperbaiki','servis')");

// Antrean & peminjaman berjalan (paling mendesak dulu)
$daftar = q($pdo,
    "SELECT p.id_peminjaman id, u.`$kolomNama` peminjam, l.kode_laptop kode, l.merk,
            p.tanggal_pinjam, p.akan_kembali, LOWER(p.status) status,
            DATEDIFF(CURDATE(), p.akan_kembali) AS lewat
     FROM peminjaman p
     JOIN users u ON u.id_users = p.id_users
     JOIN laptops l ON l.id_laptop = p.id_laptop
     WHERE LOWER(p.status) IN ('menunggu','diajukan','dipinjam')
     ORDER BY (LOWER(p.status) = 'dipinjam' AND p.akan_kembali < CURDATE()) DESC, p.akan_kembali ASC
     LIMIT 8");

echo json_encode([
    'nama'    => $nama,
    'stat'    => compact('menunggu', 'dipinjam', 'hariIni', 'telat'),
    'laptop'  => compact('total', 'siap', 'pinj', 'servis'),
    'daftar'  => $daftar,
    'badge'   => ['peminjaman' => $menunggu, 'pengembalian' => $dipinjam],
]);