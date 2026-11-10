<?php
require __DIR__ . '/auth.php';
require_role('siswa');

function out(array $d, int $c = 200): never { http_response_code($c); echo json_encode($d); exit; }
$id = (int) $_SESSION['id_users'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $riwayat = ($_GET['scope'] ?? 'aktif') === 'riwayat';
    $in = $riwayat ? "('dikembalikan','ditolak')" : "('menunggu','dipinjam')";
    $rows = q($pdo, "SELECT p.id_peminjaman id, l.kode_laptop kode, l.merk, l.tipe, p.tanggal_pinjam, p.akan_kembali, LOWER(p.status) status,
                            g.tanggal_kembali, g.kondisi_kembali,
                            IF(LOWER(p.status) = 'dipinjam', DATEDIFF(p.akan_kembali, CURDATE()), NULL) sisa,
                            IF(g.tanggal_kembali IS NOT NULL, GREATEST(0, DATEDIFF(g.tanggal_kembali, p.akan_kembali)), 0) telat
                     FROM peminjaman p JOIN laptops l ON l.id_laptop = p.id_laptop
                     LEFT JOIN pengembalian g ON g.id_peminjaman = p.id_peminjaman
                     WHERE p.id_users = ? AND LOWER(p.status) IN $in
                     ORDER BY p.tanggal_pinjam DESC, p.id_peminjaman DESC LIMIT 200", [$id]);
    $aktif = (int) q1($pdo, "SELECT COUNT(*) c FROM peminjaman WHERE id_users = ? AND LOWER(status) IN ('menunggu','dipinjam')", [$id])['c'];
    out(['nama' => $_SESSION['nama'] ?? 'Siswa', 'aktif' => $aktif, 'data' => $rows]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = json_decode(file_get_contents('php://input'), true) ?? [];
    if (($in['aksi'] ?? '') !== 'batal') out(['error' => 'Aksi tidak dikenal'], 400);
    $pid = (int) ($in['id'] ?? 0);
    $r = q1($pdo, 'SELECT LOWER(status) st FROM peminjaman WHERE id_peminjaman = ? AND id_users = ?', [$pid, $id]);
    if (!$r) out(['error' => 'Pengajuan tidak ditemukan'], 404);
    if ($r['st'] !== 'menunggu') out(['error' => 'Pengajuan sudah diproses petugas, tidak bisa dibatalkan'], 409);
    q($pdo, "DELETE FROM peminjaman WHERE id_peminjaman = ? AND id_users = ? AND LOWER(status) = 'menunggu'", [$pid, $id]);
    out(['ok' => true]);
}
out(['error' => 'Metode tidak diizinkan'], 405);