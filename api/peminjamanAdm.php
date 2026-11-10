<?php
require __DIR__ . '/auth.php';
require_role('admin');

const STATUS  = ['menunggu', 'dipinjam', 'dikembalikan', 'ditolak'];
const KONDISI = ['Baik', 'Perlu servis ringan', 'Rusak'];

function out(array $d, int $c = 200): never { http_response_code($c); echo json_encode($d); exit; }
function tgl(string $s): string {
    $d = DateTime::createFromFormat('Y-m-d', $s);
    if (!$d || $d->format('Y-m-d') !== $s) out(['error' => 'Format tanggal tidak valid'], 422);
    return $s;
}
// Ambil & kunci baris peminjaman (dipakai di dalam transaksi)
function kunci(PDO $pdo, int $id): array {
    return q1($pdo, 'SELECT *, LOWER(status) st FROM peminjaman WHERE id_peminjaman = ? FOR UPDATE', [$id])
        ?? out(['error' => 'Data peminjaman tidak ditemukan'], 404);
}
function kunciLaptop(PDO $pdo, int $id): array {
    return q1($pdo, 'SELECT *, LOWER(status) st FROM laptops WHERE id_laptop = ? FOR UPDATE', [$id])
        ?? out(['error' => 'Laptop tidak ditemukan'], 404);
}

$in   = json_decode(file_get_contents('php://input'), true) ?? [];
$saya = (int) $_SESSION['id_users'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Pilihan untuk form "Tambah peminjaman"
        if (isset($_GET['opsi'])) {
            out(['siswa'  => q($pdo, "SELECT id_users, nama, kelas FROM users WHERE role = 'siswa' ORDER BY nama"),
                 'laptop' => q($pdo, "SELECT id_laptop, kode_laptop, merk, tipe FROM laptops WHERE LOWER(status) IN ('tersedia','siap') ORDER BY kode_laptop")]);
        }
        $q = '%' . trim($_GET['q'] ?? '') . '%'; $st = $_GET['status'] ?? '';
        $sql = "SELECT p.id_peminjaman id, u.nama, u.kelas, l.kode_laptop kode, l.merk, l.tipe,
                       p.tanggal_pinjam, p.akan_kembali, LOWER(p.status) status,
                       g.tanggal_kembali, g.kondisi_kembali,
                       IF(LOWER(p.status) = 'dipinjam', DATEDIFF(CURDATE(), p.akan_kembali), NULL) lewat
                FROM peminjaman p
                JOIN users u ON u.id_users = p.id_users
                JOIN laptops l ON l.id_laptop = p.id_laptop
                LEFT JOIN pengembalian g ON g.id_peminjaman = p.id_peminjaman
                WHERE (u.nama LIKE ? OR l.kode_laptop LIKE ? OR u.kelas LIKE ?)";
        $p = [$q, $q, $q];
        if (in_array($st, STATUS, true)) { $sql .= ' AND LOWER(p.status) = ?'; $p[] = $st; }
        $sql .= " ORDER BY FIELD(LOWER(p.status),'menunggu','dipinjam','dikembalikan','ditolak'), p.tanggal_pinjam DESC, p.id_peminjaman DESC LIMIT 200";

        $hitung = array_fill_keys(STATUS, 0);
        foreach (q($pdo, 'SELECT LOWER(status) s, COUNT(*) c FROM peminjaman GROUP BY LOWER(status)') as $x)
            if (isset($hitung[$x['s']])) $hitung[$x['s']] = (int) $x['c'];
        out(['nama' => $_SESSION['nama'] ?? 'Admin', 'hitung' => $hitung, 'data' => q($pdo, $sql, $p)]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['error' => 'Metode tidak diizinkan'], 405);

    $aksi = $in['aksi'] ?? '';
    $pdo->beginTransaction();

    if ($aksi === 'tambah') {                       // admin mencatat peminjaman langsung
        $u = (int) ($in['id_users'] ?? 0); $lp = (int) ($in['id_laptop'] ?? 0);
        $a = tgl($in['tanggal_pinjam'] ?? ''); $b = tgl($in['akan_kembali'] ?? '');
        if ($b < $a) out(['error' => 'Tanggal kembali tidak boleh sebelum tanggal pinjam'], 422);
        if (!q1($pdo, "SELECT 1 FROM users WHERE id_users = ? AND role = 'siswa'", [$u])) out(['error' => 'Peminjam harus berupa siswa'], 422);
        if (kunciLaptop($pdo, $lp)['st'] !== 'tersedia') out(['error' => 'Laptop sedang tidak tersedia'], 409);
        q($pdo, "INSERT INTO peminjaman (id_users, id_laptop, id_petugas, tanggal_pinjam, akan_kembali, status) VALUES (?,?,?,?,?, 'dipinjam')", [$u, $lp, $saya, $a, $b]);
        q($pdo, "UPDATE laptops SET status = 'dipinjam' WHERE id_laptop = ?", [$lp]);

    } elseif ($aksi === 'setujui') {
        $pj = kunci($pdo, (int) ($in['id'] ?? 0));
        if ($pj['st'] !== 'menunggu') out(['error' => 'Pengajuan ini sudah diproses'], 409);
        if (kunciLaptop($pdo, (int) $pj['id_laptop'])['st'] !== 'tersedia') out(['error' => 'Laptop sedang tidak tersedia'], 409);
        q($pdo, "UPDATE peminjaman SET status = 'dipinjam', id_petugas = ? WHERE id_peminjaman = ?", [$saya, $pj['id_peminjaman']]);
        q($pdo, "UPDATE laptops SET status = 'dipinjam' WHERE id_laptop = ?", [$pj['id_laptop']]);

    } elseif ($aksi === 'tolak') {
        $pj = kunci($pdo, (int) ($in['id'] ?? 0));
        if ($pj['st'] !== 'menunggu') out(['error' => 'Pengajuan ini sudah diproses'], 409);
        q($pdo, "UPDATE peminjaman SET status = 'ditolak', id_petugas = ? WHERE id_peminjaman = ?", [$saya, $pj['id_peminjaman']]);

    } elseif ($aksi === 'kembali') {
        $pj = kunci($pdo, (int) ($in['id'] ?? 0));
        $tk = tgl($in['tanggal_kembali'] ?? ''); $kond = $in['kondisi_kembali'] ?? '';
        $ket = trim($in['keterangan'] ?? '');
        if ($pj['st'] !== 'dipinjam') out(['error' => 'Laptop ini tidak sedang dipinjam'], 409);
        if ($tk < $pj['tanggal_pinjam']) out(['error' => 'Tanggal kembali tidak boleh sebelum tanggal pinjam'], 422);
        if (!in_array($kond, KONDISI, true)) out(['error' => 'Pilih kondisi laptop saat dikembalikan'], 422);
        q($pdo, 'INSERT INTO pengembalian (id_peminjaman, id_petugas, tanggal_kembali, kondisi_kembali, keterangan) VALUES (?,?,?,?,?)',
          [$pj['id_peminjaman'], $saya, $tk, $kond, $ket ?: null]);
        q($pdo, "UPDATE peminjaman SET status = 'dikembalikan' WHERE id_peminjaman = ?", [$pj['id_peminjaman']]);
        q($pdo, 'UPDATE laptops SET status = ?, kondisi = ? WHERE id_laptop = ?',
          [$kond === 'Rusak' ? 'perbaikan' : 'tersedia', $kond, $pj['id_laptop']]);

    } else out(['error' => 'Aksi tidak dikenal'], 400);

    $pdo->commit();
    out(['ok' => true]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $k = $e->errorInfo[1] ?? 0;
    if ($k == 1054) out(['error' => 'Struktur database belum sesuai. Jalankan database/migrasi_users_role.sql terlebih dahulu.'], 500);
    out(['error' => 'Kesalahan database'], 500);
}