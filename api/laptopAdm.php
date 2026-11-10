<?php
require __DIR__ . '/auth.php';
require_role('admin');

const STATUS  = ['tersedia', 'dipinjam', 'perbaikan'];
const KONDISI = ['Baik', 'Perlu servis ringan', 'Rusak', ''];

function out(array $d, int $c = 200): never { http_response_code($c); echo json_encode($d); exit; }

function validasi(array $in): array {
    $kode = trim($in['kode_laptop'] ?? ''); $merk = trim($in['merk'] ?? '');
    $tipe = trim($in['tipe'] ?? '');        $kond = trim($in['kondisi'] ?? '');
    $stat = strtolower(trim($in['status'] ?? 'tersedia'));
    if ($kode === '' || $merk === '')           out(['error' => 'Kode dan merk wajib diisi'], 422);
    if (mb_strlen($kode) > 100 || mb_strlen($merk) > 20 || mb_strlen($tipe) > 10)
                                                out(['error' => 'Kode maks 100, merk maks 20, tipe maks 10 karakter'], 422);
    if (!in_array($kond, KONDISI, true))        out(['error' => 'Kondisi tidak valid'], 422);
    if (!in_array($stat, STATUS, true))         out(['error' => 'Status tidak valid'], 422);
    return [$kode, $merk, $tipe === '' ? null : $tipe, $kond === '' ? null : $kond, $stat];
}

$in = json_decode(file_get_contents('php://input'), true) ?? [];

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            $q = '%' . trim($_GET['q'] ?? '') . '%';
            $st = strtolower($_GET['status'] ?? '');
            $sql = 'SELECT id_laptop, kode_laptop, merk, tipe, kondisi, LOWER(status) status FROM laptops
                    WHERE (kode_laptop LIKE ? OR merk LIKE ? OR tipe LIKE ?)';
            $p = [$q, $q, $q];
            if (in_array($st, STATUS, true)) { $sql .= ' AND LOWER(status) = ?'; $p[] = $st; }
            $total = (int) q1($pdo, 'SELECT COUNT(*) c FROM laptops')['c'];
            out(['nama' => $_SESSION['nama'] ?? 'Admin', 'total' => $total,
                 'data' => q($pdo, $sql . ' ORDER BY id_laptop DESC', $p)]);

        case 'POST':
            [$k, $m, $t, $c, $s] = validasi($in);
            q($pdo, 'INSERT INTO laptops (kode_laptop, merk, tipe, kondisi, status) VALUES (?,?,?,?,?)', [$k, $m, $t, $c, $s]);
            out(['ok' => true], 201);

        case 'PUT':
            $id = (int) ($in['id_laptop'] ?? 0);
            [$k, $m, $t, $c, $s] = validasi($in);
            q($pdo, 'UPDATE laptops SET kode_laptop=?, merk=?, tipe=?, kondisi=?, status=? WHERE id_laptop=?', [$k, $m, $t, $c, $s, $id]);
            out(['ok' => true]);

        case 'DELETE':
            q($pdo, 'DELETE FROM laptops WHERE id_laptop = ?', [(int) ($_GET['id'] ?? 0)]);
            out(['ok' => true]);

        default: out(['error' => 'Metode tidak diizinkan'], 405);
    }
} catch (PDOException $e) {
    $k = $e->errorInfo[1] ?? 0;
    if ($k == 1062) out(['error' => 'Kode laptop sudah dipakai'], 409);
    if ($k == 1451) out(['error' => 'Laptop punya riwayat peminjaman, tidak bisa dihapus. Ubah statusnya saja.'], 409);
    out(['error' => 'Kesalahan database'], 500);
}