<?php
require __DIR__ . '/ptgsLib.php';

$in   = json_decode(file_get_contents('php://input'), true) ?? [];
$saya = (int) $_SESSION['id_users'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $q = '%' . trim($_GET['q'] ?? '') . '%';

        // Laptop yang sedang dipinjam; yang paling lama lewat batas tampil paling atas
        $aktif = q($pdo,
            "SELECT p.id_peminjaman id, u.nama, u.kelas, l.kode_laptop kode, l.merk, l.tipe,
                    p.tanggal_pinjam, p.akan_kembali,
                    DATEDIFF(CURDATE(), p.akan_kembali) AS lewat
             FROM peminjaman p
             JOIN users u ON u.id_users = p.id_users
             JOIN laptops l ON l.id_laptop = p.id_laptop
             WHERE LOWER(p.status) = 'dipinjam'
               AND (u.nama LIKE ? OR l.kode_laptop LIKE ? OR u.kelas LIKE ?)
             ORDER BY p.akan_kembali ASC, p.id_peminjaman ASC
             LIMIT 200", [$q, $q, $q]);

        $s = q1($pdo,
            "SELECT COUNT(*) dipinjam,
                    SUM(akan_kembali = CURDATE()) hari_ini,
                    SUM(akan_kembali < CURDATE()) telat
             FROM peminjaman WHERE LOWER(status) = 'dipinjam'");
        $bulanIni = (int) q1($pdo,
            "SELECT COUNT(*) c FROM pengembalian WHERE tanggal_kembali >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")['c'];

        $riwayat = q($pdo,
            "SELECT u.nama, u.kelas, l.kode_laptop kode, l.merk,
                    g.tanggal_kembali, g.kondisi_kembali, g.keterangan,
                    DATEDIFF(g.tanggal_kembali, p.akan_kembali) AS telat
             FROM pengembalian g
             JOIN peminjaman p ON p.id_peminjaman = g.id_peminjaman
             JOIN users u ON u.id_users = p.id_users
             JOIN laptops l ON l.id_laptop = p.id_laptop
             ORDER BY g.tanggal_kembali DESC, g.id_pengembalian DESC
             LIMIT 8");

        $hitung = hitung_status($pdo);
        out(['nama'    => $_SESSION['nama'] ?? 'Petugas',
             'stat'    => ['dipinjam' => (int) $s['dipinjam'], 'hariIni' => (int) $s['hari_ini'],
                           'telat' => (int) $s['telat'], 'bulanIni' => $bulanIni],
             'badge'   => ['peminjaman' => $hitung['menunggu'], 'pengembalian' => $hitung['dipinjam']],
             'aktif'   => $aktif,
             'riwayat' => $riwayat]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['error' => 'Metode tidak diizinkan'], 405);

    if (($in['aksi'] ?? '') !== 'kembali') out(['error' => 'Aksi tidak dikenal'], 400);
    $pdo->beginTransaction();
    proses_kembali($pdo, $in, $saya);
    $pdo->commit();
    out(['ok' => true]);

} catch (PDOException $e) {
    galat_db($e);
}