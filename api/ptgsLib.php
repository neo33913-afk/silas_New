<?php
// Fungsi bersama untuk API petugas (peminjaman, pengembalian, laporan).
// Cukup di-require di baris pertama: sudah memuat auth.php dan memeriksa role petugas.
require __DIR__ . '/auth.php';
require_role('petugas');

date_default_timezone_set('Asia/Jakarta');

const STATUS  = ['menunggu', 'dipinjam', 'dikembalikan', 'ditolak'];
const KONDISI = ['Baik', 'Perlu servis ringan', 'Rusak'];

function out(array $d, int $c = 200): never {
    global $pdo;
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code($c);
    echo json_encode($d);
    exit;
}
function tgl(string $s): string {
    $d = DateTime::createFromFormat('Y-m-d', $s);
    if (!$d || $d->format('Y-m-d') !== $s) out(['error' => 'Format tanggal tidak valid'], 422);
    return $s;
}
// Ambil & kunci baris (dipakai di dalam transaksi)
function kunci(PDO $pdo, int $id): array {
    return q1($pdo, 'SELECT *, LOWER(status) st FROM peminjaman WHERE id_peminjaman = ? FOR UPDATE', [$id])
        ?? out(['error' => 'Data peminjaman tidak ditemukan'], 404);
}
function kunciLaptop(PDO $pdo, int $id): array {
    return q1($pdo, 'SELECT *, LOWER(status) st FROM laptops WHERE id_laptop = ? FOR UPDATE', [$id])
        ?? out(['error' => 'Laptop tidak ditemukan'], 404);
}
// Jumlah peminjaman per status + angka badge sidebar
function hitung_status(PDO $pdo): array {
    $h = array_fill_keys(STATUS, 0);
    foreach (q($pdo, 'SELECT LOWER(status) s, COUNT(*) c FROM peminjaman GROUP BY LOWER(status)') as $x)
        if (isset($h[$x['s']])) $h[$x['s']] = (int) $x['c'];
    return $h;
}
// Catat pengembalian (harus dipanggil di dalam transaksi)
function proses_kembali(PDO $pdo, array $in, int $saya): void {
    $pj   = kunci($pdo, (int) ($in['id'] ?? 0));
    $tk   = tgl((string) ($in['tanggal_kembali'] ?? ''));
    $kond = (string) ($in['kondisi_kembali'] ?? '');
    $ket  = trim((string) ($in['keterangan'] ?? ''));

    if ($pj['st'] !== 'dipinjam') out(['error' => 'Laptop ini tidak sedang dipinjam'], 409);
    if ($tk < $pj['tanggal_pinjam']) out(['error' => 'Tanggal kembali tidak boleh sebelum tanggal pinjam'], 422);
    if ($tk > date('Y-m-d')) out(['error' => 'Tanggal kembali tidak boleh di masa depan'], 422);
    if (!in_array($kond, KONDISI, true)) out(['error' => 'Pilih kondisi laptop saat dikembalikan'], 422);
    if (mb_strlen($ket) > 500) out(['error' => 'Keterangan maksimal 500 karakter'], 422);

    q($pdo, 'INSERT INTO pengembalian (id_peminjaman, id_petugas, tanggal_kembali, kondisi_kembali, keterangan) VALUES (?,?,?,?,?)',
      [$pj['id_peminjaman'], $saya, $tk, $kond, $ket ?: null]);
    q($pdo, "UPDATE peminjaman SET status = 'dikembalikan' WHERE id_peminjaman = ?", [$pj['id_peminjaman']]);
    q($pdo, 'UPDATE laptops SET status = ?, kondisi = ? WHERE id_laptop = ?',
      [$kond === 'Rusak' ? 'perbaikan' : 'tersedia', $kond, $pj['id_laptop']]);
}
function galat_db(PDOException $e): never {
    $k = $e->errorInfo[1] ?? 0;
    if ($k == 1054) out(['error' => 'Struktur database belum sesuai. Jalankan database/migrasi_users_role.sql terlebih dahulu.'], 500);
    out(['error' => 'Kesalahan database'], 500);
}