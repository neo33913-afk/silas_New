<?php
require __DIR__ . '/auth.php';
require_role('siswa');

// Deteksi kolom nama pada tabel users
$kolomNama = 'username';
try {
    $cols = array_column(q($pdo, 'SHOW COLUMNS FROM users'), 'Field');
    foreach (['nama_users', 'nama', 'nama_lengkap', 'username'] as $c) {
        if (in_array($c, $cols, true)) { $kolomNama = $c; break; }
    }
} catch (Throwable $e) {}

$user = q1($pdo, 'SELECT id_users, nama FROM users WHERE id_users = ?', [$_SESSION['id_users']]);
if (!$user) { http_response_code(404); echo json_encode(['error' => 'Pengguna tidak ditemukan']); exit; }
$id = (int) $user['id_users'];

$hitung = fn(string $where) => (int) q1($pdo,
    "SELECT COUNT(*) c FROM peminjaman WHERE id_users = ? AND $where", [$id])['c'];

$aktif    = $hitung("LOWER(status) = 'dipinjam'");
$menunggu = $hitung("LOWER(status) IN ('menunggu','diajukan')");
$riwayat  = $hitung("LOWER(status) IN ('dikembalikan','selesai')");
$telat    = $hitung("LOWER(status) = 'dipinjam' AND akan_kembali < CURDATE()");

// Peminjaman milik siswa (yang berjalan dulu, lalu terbaru)
$pinjaman = q($pdo,
    "SELECT p.id_peminjaman id, l.kode_laptop kode, l.merk, l.tipe,
            p.tanggal_pinjam, p.akan_kembali, LOWER(p.status) status,
            DATEDIFF(p.akan_kembali, CURDATE()) AS sisa
     FROM peminjaman p JOIN laptops l ON l.id_laptop = p.id_laptop
     WHERE p.id_users = ?
     ORDER BY (LOWER(p.status) IN ('dipinjam','menunggu','diajukan')) DESC, p.tanggal_pinjam DESC
     LIMIT 6", [$id]);

// Laptop yang bisa diajukan
$tersedia = q($pdo,
    "SELECT id_laptop id, kode_laptop kode, merk, tipe, kondisi
     FROM laptops WHERE LOWER(status) IN ('tersedia','siap')
     ORDER BY merk, kode_laptop LIMIT 5");
$jumlahTersedia = (int) q1($pdo,
    "SELECT COUNT(*) c FROM laptops WHERE LOWER(status) IN ('tersedia','siap')")['c'];

echo json_encode([
    'nama'     => $user['nama'],
    'stat'     => compact('aktif', 'menunggu', 'riwayat', 'telat'),
    'pinjaman' => $pinjaman,
    'tersedia' => $tersedia,
    'jumlahTersedia' => $jumlahTersedia,
    'badge'    => ['saya' => $aktif + $menunggu],
]);