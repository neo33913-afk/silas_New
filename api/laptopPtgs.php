<?php
require __DIR__ . '/ptgsLib.php';

// Wewenang petugas atas data laptop: melihat, dan mengubah status/kondisi.
// Tambah & hapus unit tetap hanya untuk admin. Status "dipinjam" diatur otomatis oleh peminjaman/pengembalian.
const STATUS_UBAH = ['tersedia', 'perbaikan'];

// Samakan variasi penulisan status di database ke tiga nilai baku
const NORMAL = "CASE WHEN LOWER(status) IN ('tersedia','siap') THEN 'tersedia'
                     WHEN LOWER(status) = 'dipinjam' THEN 'dipinjam'
                     WHEN LOWER(status) IN ('perbaikan','diperbaiki','servis') THEN 'perbaikan'
                     ELSE LOWER(COALESCE(status, '')) END";

$in = json_decode(file_get_contents('php://input'), true) ?? [];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $q = '%' . trim($_GET['q'] ?? '') . '%'; $st = $_GET['status'] ?? '';
        $sql = "SELECT * FROM (
                    SELECT id_laptop id, kode_laptop kode, merk, tipe, kondisi, " . NORMAL . " status FROM laptops
                ) t WHERE (kode LIKE ? OR merk LIKE ? OR tipe LIKE ?)";
        $p = [$q, $q, $q];
        if (in_array($st, ['tersedia', 'dipinjam', 'perbaikan'], true)) { $sql .= ' AND status = ?'; $p[] = $st; }
        $sql .= ' ORDER BY kode LIMIT 500';

        $h = q1($pdo, "SELECT COUNT(*) total,
                              SUM(" . NORMAL . " = 'tersedia') tersedia,
                              SUM(" . NORMAL . " = 'dipinjam') dipinjam,
                              SUM(" . NORMAL . " = 'perbaikan') perbaikan FROM laptops");
        $s = hitung_status($pdo);
        out(['nama'   => $_SESSION['nama'] ?? 'Petugas',
             'hitung' => array_map('intval', $h),
             'badge'  => ['peminjaman' => $s['menunggu'], 'pengembalian' => $s['dipinjam']],
             'data'   => q($pdo, $sql, $p)]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['error' => 'Metode tidak diizinkan'], 405);
    if (($in['aksi'] ?? '') !== 'ubah') out(['error' => 'Aksi tidak dikenal'], 400);

    $id = (int) ($in['id'] ?? 0); $status = (string) ($in['status'] ?? ''); $kond = (string) ($in['kondisi'] ?? '');
    if (!in_array($status, STATUS_UBAH, true)) out(['error' => 'Status hanya bisa diubah ke tersedia atau perbaikan'], 422);
    if (!in_array($kond, KONDISI, true))       out(['error' => 'Pilih kondisi laptop'], 422);
    if ($kond === 'Rusak' && $status === 'tersedia') out(['error' => 'Laptop yang rusak tidak bisa berstatus tersedia. Ubah status menjadi perbaikan.'], 422);

    $pdo->beginTransaction();
    $lp = kunciLaptop($pdo, $id);
    if ($lp['st'] === 'dipinjam') out(['error' => 'Laptop sedang dipinjam. Statusnya berubah setelah pengembalian dicatat.'], 409);
    q($pdo, 'UPDATE laptops SET status = ?, kondisi = ? WHERE id_laptop = ?', [$status, $kond, $id]);
    $pdo->commit();
    out(['ok' => true]);

} catch (PDOException $e) {
    galat_db($e);
}