<?php
require __DIR__ . '/ptgsLib.php';

$in   = json_decode(file_get_contents('php://input'), true) ?? [];
$saya = (int) $_SESSION['id_users'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Pilihan untuk form "Catat peminjaman"
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

        $hitung = hitung_status($pdo);
        out(['nama'   => $_SESSION['nama'] ?? 'Petugas',
             'hitung' => $hitung,
             'badge'  => ['peminjaman' => $hitung['menunggu'], 'pengembalian' => $hitung['dipinjam']],
             'data'   => q($pdo, $sql, $p)]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['error' => 'Metode tidak diizinkan'], 405);

    $aksi = $in['aksi'] ?? '';
    $pdo->beginTransaction();

    if ($aksi === 'tambah') {                       // petugas mencatat peminjaman langsung
        $u = (int) ($in['id_users'] ?? 0); $lp = (int) ($in['id_laptop'] ?? 0);
        $a = tgl((string) ($in['tanggal_pinjam'] ?? '')); $b = tgl((string) ($in['akan_kembali'] ?? ''));
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
        proses_kembali($pdo, $in, $saya);

    } else out(['error' => 'Aksi tidak dikenal'], 400);

    $pdo->commit();
    out(['ok' => true]);

} catch (PDOException $e) {
    galat_db($e);
}