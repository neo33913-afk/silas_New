<?php
require __DIR__ . '/auth.php';
require_role('admin');
date_default_timezone_set('Asia/Jakarta');

const STATUS = ['menunggu', 'dipinjam', 'dikembalikan', 'ditolak'];
function tglq(?string $s, string $def): string {
    $d = DateTime::createFromFormat('Y-m-d', $s ?? '');
    return ($d && $d->format('Y-m-d') === $s) ? $s : $def;
}

$dari   = tglq($_GET['dari'] ?? null, date('Y-m-01'));
$sampai = tglq($_GET['sampai'] ?? null, date('Y-m-d'));
if ($sampai < $dari) [$dari, $sampai] = [$sampai, $dari];
$st = in_array($_GET['status'] ?? '', STATUS, true) ? $_GET['status'] : '';

$sql = "SELECT u.nama, u.kelas, l.kode_laptop kode, l.merk, l.tipe, p.tanggal_pinjam, p.akan_kembali,
               LOWER(p.status) status, g.tanggal_kembali, g.kondisi_kembali, g.keterangan,
               GREATEST(0, CASE WHEN LOWER(p.status) = 'dipinjam' THEN DATEDIFF(CURDATE(), p.akan_kembali)
                                WHEN g.tanggal_kembali IS NOT NULL THEN DATEDIFF(g.tanggal_kembali, p.akan_kembali)
                                ELSE 0 END) telat
        FROM peminjaman p
        JOIN users u ON u.id_users = p.id_users
        JOIN laptops l ON l.id_laptop = p.id_laptop
        LEFT JOIN pengembalian g ON g.id_peminjaman = p.id_peminjaman
        WHERE p.tanggal_pinjam BETWEEN ? AND ?";
$p = [$dari, $sampai];
if ($st) { $sql .= ' AND LOWER(p.status) = ?'; $p[] = $st; }
$rows = q($pdo, $sql . ' ORDER BY p.tanggal_pinjam DESC, p.id_peminjaman DESC LIMIT 5000', $p);

// ---- Unduh CSV (dibuka Excel dengan pemisah ;) ----
if (($_GET['format'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"laporan-peminjaman_{$dari}_{$sampai}.csv\"");
    $aman = fn($v) => is_string($v) && $v !== '' && strpos('=+-@', $v[0]) !== false ? "'" . $v : $v;  // cegah formula injection
    $f = fopen('php://output', 'w');
    fwrite($f, "\xEF\xBB\xBFsep=;\n");
    fputcsv($f, ['Peminjam', 'Kelas', 'Kode Laptop', 'Merk', 'Tipe', 'Tgl Pinjam', 'Harus Kembali', 'Tgl Kembali', 'Status', 'Terlambat (hari)', 'Kondisi Kembali', 'Keterangan'], ';');
    foreach ($rows as $r) {
        fputcsv($f, array_map($aman, [$r['nama'], $r['kelas'], $r['kode'], $r['merk'], $r['tipe'], $r['tanggal_pinjam'], $r['akan_kembali'],
            $r['tanggal_kembali'], $r['status'], $r['telat'], $r['kondisi_kembali'], $r['keterangan']]), ';');
    }
    exit;
}

// ---- Ringkasan & inventaris (JSON) ----
$r = ['total' => count($rows), 'menunggu' => 0, 'dipinjam' => 0, 'dikembalikan' => 0, 'ditolak' => 0, 'terlambat' => 0];
foreach ($rows as $x) { if (isset($r[$x['status']])) $r[$x['status']]++; if ($x['telat'] > 0) $r['terlambat']++; }

$inv = ['total' => 0, 'tersedia' => 0, 'dipinjam' => 0, 'perbaikan' => 0, 'baik' => 0, 'servis' => 0, 'rusak' => 0];
foreach (q($pdo, 'SELECT LOWER(status) s, kondisi k FROM laptops') as $x) {
    $inv['total']++;
    if (in_array($x['s'], ['tersedia', 'siap'], true)) $inv['tersedia']++; elseif (isset($inv[$x['s']])) $inv[$x['s']]++;
    $k = strtolower((string) $x['k']);
    if (str_contains($k, 'rusak')) $inv['rusak']++; elseif (str_contains($k, 'servis')) $inv['servis']++; elseif (str_contains($k, 'baik')) $inv['baik']++;
}

echo json_encode(['nama' => $_SESSION['nama'] ?? 'Admin', 'dari' => $dari, 'sampai' => $sampai,
                  'ringkasan' => $r, 'inventaris' => $inv, 'data' => $rows]);