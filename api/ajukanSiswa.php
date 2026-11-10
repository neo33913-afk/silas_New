<?php
require __DIR__ . '/auth.php';
require_role('siswa');
date_default_timezone_set('Asia/Jakarta');

const MAKS_HARI  = 7;   // lama pinjam maksimal (hari)
const MAKS_AKTIF = 1;   // jumlah pengajuan/peminjaman aktif sekaligus

function out(array $d, int $c = 200): never { http_response_code($c); echo json_encode($d); exit; }
function tgl(string $s): string {
    $d = DateTime::createFromFormat('Y-m-d', $s);
    if (!$d || $d->format('Y-m-d') !== $s) out(['error' => 'Format tanggal tidak valid'], 422);
    return $s;
}
function kondisiAkun(PDO $pdo, int $id): array {
    $aktif = (int) q1($pdo, "SELECT COUNT(*) c FROM peminjaman WHERE id_users = ? AND LOWER(status) IN ('menunggu','dipinjam')", [$id])['c'];
    $telat = (int) q1($pdo, "SELECT COUNT(*) c FROM peminjaman WHERE id_users = ? AND LOWER(status) = 'dipinjam' AND akan_kembali < CURDATE()", [$id])['c'];
    $blokir = $telat > 0 ? 'Kamu masih punya laptop yang terlambat dikembalikan. Kembalikan dulu ke petugas sebelum meminjam lagi.'
            : ($aktif >= MAKS_AKTIF ? 'Kamu sudah mencapai batas ' . MAKS_AKTIF . ' pengajuan/peminjaman aktif. Selesaikan atau batalkan dulu yang sedang berjalan.' : null);
    return [$aktif, $blokir];
}

$id = (int) $_SESSION['id_users'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $q = '%' . trim($_GET['q'] ?? '') . '%';
        [$aktif, $blokir] = kondisiAkun($pdo, $id);
        out(['nama' => $_SESSION['nama'] ?? 'Siswa', 'maksHari' => MAKS_HARI, 'maksAktif' => MAKS_AKTIF, 'aktif' => $aktif, 'blokir' => $blokir,
             'data' => q($pdo, "SELECT id_laptop, kode_laptop, merk, tipe, kondisi FROM laptops
                                WHERE LOWER(status) IN ('tersedia','siap') AND (kode_laptop LIKE ? OR merk LIKE ? OR tipe LIKE ?)
                                ORDER BY merk, kode_laptop", [$q, $q, $q])]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['error' => 'Metode tidak diizinkan'], 405);

    $in = json_decode(file_get_contents('php://input'), true) ?? [];
    $lp = (int) ($in['id_laptop'] ?? 0);
    $a = tgl($in['tanggal_pinjam'] ?? ''); $b = tgl($in['akan_kembali'] ?? '');
    if ($a < date('Y-m-d')) out(['error' => 'Tanggal pinjam tidak boleh sebelum hari ini'], 422);
    if ($b < $a)            out(['error' => 'Tanggal kembali tidak boleh sebelum tanggal pinjam'], 422);
    if ((new DateTime($a))->diff(new DateTime($b))->days > MAKS_HARI) out(['error' => 'Lama pinjam maksimal ' . MAKS_HARI . ' hari'], 422);

    $pdo->beginTransaction();
    q($pdo, 'SELECT id_users FROM users WHERE id_users = ? FOR UPDATE', [$id]);   // cegah pengajuan ganda bersamaan
    [, $blokir] = kondisiAkun($pdo, $id);
    if ($blokir) out(['error' => $blokir], 409);
    $l = q1($pdo, 'SELECT LOWER(status) st FROM laptops WHERE id_laptop = ?', [$lp]);
    if (!$l || !in_array($l['st'], ['tersedia', 'siap'], true)) out(['error' => 'Laptop sudah tidak tersedia'], 409);
    q($pdo, "INSERT INTO peminjaman (id_users, id_laptop, id_petugas, tanggal_pinjam, akan_kembali, status) VALUES (?,?,NULL,?,?, 'menunggu')", [$id, $lp, $a, $b]);
    $pdo->commit();
    out(['ok' => true], 201);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    out(['error' => ($e->errorInfo[1] ?? 0) == 1048 ? 'Database belum siap: jalankan database/migrasi_users_role.sql' : 'Kesalahan database'], 500);
}