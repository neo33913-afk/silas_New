<?php
require __DIR__ . '/auth.php';
require_role('admin');

const ROLE = ['admin', 'petugas', 'siswa'];
function out(array $d, int $c = 200): never { http_response_code($c); echo json_encode($d); exit; }
function adaDuaAdmin(PDO $pdo): bool { return (int) q1($pdo, "SELECT COUNT(*) c FROM users WHERE role='admin'")['c'] > 1; }

function validasi(array $in, bool $baru): array {
    $nama = trim($in['nama'] ?? '');     $user = trim($in['username'] ?? '');
    $pass = (string) ($in['password'] ?? ''); $role = $in['role'] ?? '';
    $kelas = trim($in['kelas'] ?? '');  $hp = trim($in['no_hp'] ?? '');  $email = trim($in['email'] ?? '');
    if ($nama === '' || mb_strlen($nama) > 100)               out(['error' => 'Nama wajib diisi (maks 100 karakter)'], 422);
    if (!preg_match('/^[A-Za-z0-9_.]{3,20}$/', $user))        out(['error' => 'Username 3–20 karakter: huruf, angka, titik, atau garis bawah'], 422);
    if (!in_array($role, ROLE, true))                         out(['error' => 'Role tidak valid'], 422);
    if ($role === 'siswa' && ($kelas === '' || mb_strlen($kelas) > 20)) out(['error' => 'Kelas wajib diisi untuk siswa (maks 20 karakter)'], 422);
    if ($hp !== '' && !preg_match('/^[0-9+\- ]{5,15}$/', $hp)) out(['error' => 'No. HP tidak valid (5–15 karakter)'], 422);
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) out(['error' => 'Format email tidak valid'], 422);
    if (($baru || $pass !== '') && mb_strlen($pass) < 6)      out(['error' => 'Password minimal 6 karakter'], 422);
    return [$nama, $user, $pass, $role, $role === 'siswa' ? $kelas : null, $hp ?: null, $email ?: null];
}

$in = json_decode(file_get_contents('php://input'), true) ?? [];
$saya = (int) $_SESSION['id_users'];

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            $q = '%' . trim($_GET['q'] ?? '') . '%'; $r = $_GET['role'] ?? '';
            $sql = 'SELECT id_users, nama, username, role, kelas, no_hp, email FROM users
                    WHERE (nama LIKE ? OR username LIKE ? OR kelas LIKE ?)';
            $p = [$q, $q, $q];
            if (in_array($r, ROLE, true)) { $sql .= ' AND role = ?'; $p[] = $r; }
            $hitung = ['admin' => 0, 'petugas' => 0, 'siswa' => 0];
            foreach (q($pdo, 'SELECT role, COUNT(*) c FROM users GROUP BY role') as $x) $hitung[$x['role']] = (int) $x['c'];
            out(['nama' => $_SESSION['nama'] ?? 'Admin', 'saya' => $saya, 'hitung' => $hitung,
                 'data' => q($pdo, $sql . " ORDER BY FIELD(role,'admin','petugas','siswa'), nama", $p)]);

        case 'POST':
            [$n, $u, $pw, $ro, $k, $h, $e] = validasi($in, true);
            q($pdo, 'INSERT INTO users (nama, username, password, role, kelas, no_hp, email) VALUES (?,?,?,?,?,?,?)',
              [$n, $u, password_hash($pw, PASSWORD_DEFAULT), $ro, $k, $h, $e]);
            out(['ok' => true], 201);

        case 'PUT':
            $id = (int) ($in['id_users'] ?? 0);
            $lama = q1($pdo, 'SELECT role FROM users WHERE id_users = ?', [$id]) ?? out(['error' => 'Pengguna tidak ditemukan'], 404);
            [$n, $u, $pw, $ro, $k, $h, $e] = validasi($in, false);
            if ($lama['role'] === 'admin' && $ro !== 'admin' && !adaDuaAdmin($pdo))
                out(['error' => 'Tidak bisa mengubah role admin terakhir'], 409);
            $set = 'nama=?, username=?, role=?, kelas=?, no_hp=?, email=?'; $p = [$n, $u, $ro, $k, $h, $e];
            if ($pw !== '') { $set .= ', password=?'; $p[] = password_hash($pw, PASSWORD_DEFAULT); }
            $p[] = $id;
            q($pdo, "UPDATE users SET $set WHERE id_users = ?", $p);
            out(['ok' => true]);

        case 'DELETE':
            $id = (int) ($_GET['id'] ?? 0);
            if ($id === $saya) out(['error' => 'Tidak bisa menghapus akun yang sedang dipakai'], 409);
            $lama = q1($pdo, 'SELECT role FROM users WHERE id_users = ?', [$id]) ?? out(['error' => 'Pengguna tidak ditemukan'], 404);
            if ($lama['role'] === 'admin' && !adaDuaAdmin($pdo)) out(['error' => 'Tidak bisa menghapus admin terakhir'], 409);
            q($pdo, 'DELETE FROM users WHERE id_users = ?', [$id]);
            out(['ok' => true]);

        default: out(['error' => 'Metode tidak diizinkan'], 405);
    }
} catch (PDOException $e) {
    $k = $e->errorInfo[1] ?? 0;
    if ($k == 1062) out(['error' => 'Username sudah dipakai'], 409);
    if ($k == 1451) out(['error' => 'Pengguna punya riwayat peminjaman/pengembalian, tidak bisa dihapus.'], 409);
    out(['error' => 'Kesalahan database'], 500);
}