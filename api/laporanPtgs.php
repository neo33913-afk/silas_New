<?php
require __DIR__ . '/ptgsLib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') out(['error' => 'Metode tidak diizinkan'], 405);

try {
    // ---- Filter: rentang tanggal pinjam (bawaan bulan ini) dan status ----
    $dari   = tgl(($_GET['dari']   ?? '') ?: date('Y-m-01'));
    $sampai = tgl(($_GET['sampai'] ?? '') ?: date('Y-m-d'));
    if ($dari > $sampai) out(['error' => 'Tanggal awal tidak boleh setelah tanggal akhir'], 422);
    $st = $_GET['status'] ?? '';

    $where = 'p.tanggal_pinjam BETWEEN ? AND ?';
    $par   = [$dari, $sampai];
    if (in_array($st, STATUS, true)) { $where .= ' AND LOWER(p.status) = ?'; $par[] = $st; }

    $from = "FROM peminjaman p
             JOIN users u ON u.id_users = p.id_users
             JOIN laptops l ON l.id_laptop = p.id_laptop
             LEFT JOIN pengembalian g ON g.id_peminjaman = p.id_peminjaman";

    $baris = "SELECT p.id_peminjaman id, u.nama, u.kelas, l.kode_laptop kode, l.merk, l.tipe,
                     p.tanggal_pinjam, p.akan_kembali, LOWER(p.status) status,
                     g.tanggal_kembali, g.kondisi_kembali, g.keterangan,
                     CASE WHEN LOWER(p.status) = 'dipinjam' THEN DATEDIFF(CURDATE(), p.akan_kembali)
                          WHEN g.tanggal_kembali IS NOT NULL THEN DATEDIFF(g.tanggal_kembali, p.akan_kembali)
                          ELSE NULL END lewat
              $from WHERE $where
              ORDER BY p.tanggal_pinjam DESC, p.id_peminjaman DESC";

    // ---- Unduh CSV (sesuai filter) ----
    if (isset($_GET['unduh'])) {
        $rows = q($pdo, $baris . ' LIMIT 5000', $par);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="laporan-peminjaman_' . $dari . '_' . $sampai . '.csv"');
        $f = fopen('php://output', 'w');
        fwrite($f, "\xEF\xBB\xBFsep=;\r\n");   // BOM + petunjuk pemisah agar rapi di Excel
        $aman = fn($v) => (($v = (string) $v) !== '' && strpbrk($v[0], "=+-@\t\r") !== false) ? "'" . $v : $v;
        fputcsv($f, ['No', 'Peminjam', 'Kelas', 'Kode Laptop', 'Merk', 'Tipe', 'Tgl Pinjam', 'Akan Kembali',
                     'Status', 'Tgl Kembali', 'Kondisi Kembali', 'Keterangan'], ';', '"', '');
        foreach ($rows as $i => $r) {
            fputcsv($f, array_map($aman, [$i + 1, $r['nama'], $r['kelas'], $r['kode'], $r['merk'], $r['tipe'],
                $r['tanggal_pinjam'], $r['akan_kembali'], ucfirst($r['status']),
                $r['tanggal_kembali'], $r['kondisi_kembali'], $r['keterangan']]), ';', '"', '');
        }
        fclose($f);
        exit;
    }

    // ---- Ringkasan periode ----
    $r = q1($pdo,
        "SELECT COUNT(*) total,
                SUM(LOWER(p.status) = 'dikembalikan') kembali,
                SUM(LOWER(p.status) = 'dipinjam') dipinjam,
                SUM((LOWER(p.status) = 'dipinjam' AND p.akan_kembali < CURDATE())
                    OR (g.tanggal_kembali IS NOT NULL AND g.tanggal_kembali > p.akan_kembali)) terlambat
         $from WHERE $where", $par);

    // ---- Kondisi inventaris saat ini ----
    $i = q1($pdo,
        "SELECT COUNT(*) total,
                SUM(LOWER(status) IN ('tersedia','siap')) tersedia,
                SUM(LOWER(status) = 'dipinjam') dipinjam,
                SUM(LOWER(status) IN ('perbaikan','diperbaiki','servis')) perbaikan,
                SUM(LOWER(kondisi) LIKE '%baik%') baik,
                SUM(LOWER(kondisi) LIKE '%servis%') servis,
                SUM(LOWER(kondisi) LIKE '%rusak%') rusak
         FROM laptops");

    $hitung = hitung_status($pdo);
    out(['nama'   => $_SESSION['nama'] ?? 'Petugas',
         'badge'  => ['peminjaman' => $hitung['menunggu'], 'pengembalian' => $hitung['dipinjam']],
         'filter' => ['dari' => $dari, 'sampai' => $sampai, 'status' => $st],
         'ringkasan' => array_map('intval', $r),
         'inventaris' => array_map('intval', $i),
         'data'   => q($pdo, $baris . ' LIMIT 5000', $par)]);

} catch (PDOException $e) {
    galat_db($e);
}