<?php
require __DIR__ . '/auth.php';
require_role('admin');

$admin = ['nama' => $_SESSION['nama'] ?? 'Admin'];

// Ringkasan laptop
$total    = (int) q1($pdo, 'SELECT COUNT(*) c FROM laptops')['c'];
$kategori = (int) q1($pdo, 'SELECT COUNT(DISTINCT merk) c FROM laptops')['c'];
$siap     = (int) q1($pdo, "SELECT COUNT(*) c FROM laptops WHERE LOWER(status) IN ('tersedia','siap')")['c'];
$dipinjam = (int) q1($pdo, "SELECT COUNT(*) c FROM laptops WHERE LOWER(status) = 'dipinjam'")['c'];
$servis   = (int) q1($pdo, "SELECT COUNT(*) c FROM laptops WHERE LOWER(status) IN ('perbaikan','diperbaiki','servis')")['c'];

// Kondisi laptop
$kondisi = ['Baik' => 0, 'Perlu servis ringan' => 0, 'Rusak' => 0, 'Belum diperiksa' => 0];
foreach (q($pdo, 'SELECT kondisi, COUNT(*) c FROM laptops GROUP BY kondisi') as $r) {
    $k = trim((string) $r['kondisi']);
    $key = match (true) {
        $k === ''                                  => 'Belum diperiksa',
        stripos($k, 'rusak') !== false             => 'Rusak',
        stripos($k, 'servis') !== false            => 'Perlu servis ringan',
        stripos($k, 'baik') !== false              => 'Baik',
        default                                    => 'Belum diperiksa',
    };
    $kondisi[$key] += (int) $r['c'];
}

// Peminjaman 6 bulan terakhir (yang disetujui/dipinjam/dikembalikan)
$perBulan = [];
for ($i = 5; $i >= 0; $i--) {
    $perBulan[date('Y-m', strtotime("first day of -$i month"))] = 0;
}
$rows = q($pdo,
    "SELECT DATE_FORMAT(tanggal_pinjam,'%Y-%m') ym, COUNT(*) c
     FROM peminjaman
     WHERE tanggal_pinjam >= DATE_FORMAT(CURDATE() - INTERVAL 5 MONTH, '%Y-%m-01')
       AND LOWER(status) IN ('disetujui','dipinjam','dikembalikan','selesai')
     GROUP BY ym");
foreach ($rows as $r) {
    if (isset($perBulan[$r['ym']])) $perBulan[$r['ym']] = (int) $r['c'];
}

// Badge sidebar
$pengguna = 0;
try { $pengguna = (int) q1($pdo, "SELECT COUNT(*) c FROM users WHERE role = 'siswa'")['c']; } catch (Throwable $e) {}
$aktif = (int) q1($pdo, "SELECT COUNT(*) c FROM peminjaman WHERE LOWER(status) IN ('menunggu','diajukan','dipinjam')")['c'];

echo json_encode([
    'admin'    => $admin['nama'],
    'ringkasan'=> compact('total', 'kategori', 'siap', 'dipinjam', 'servis'),
    'kondisi'  => $kondisi,
    'bulanan'  => $perBulan,
    'badge'    => ['laptop' => $total, 'pengguna' => $pengguna, 'peminjaman' => $aktif],
]);