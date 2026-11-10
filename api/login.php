<?php
require __DIR__ . '/auth.php';

function gagal(string $pesan, int $kode = 401): never {
    http_response_code($kode);
    echo json_encode(['error' => $pesan]);
    exit;
}
// Mendukung password ter-hash (bcrypt/argon) maupun teks biasa (data lama)
function ter_hash(string $s): bool { return (bool) preg_match('/^\$2[aby]\$|^\$argon2/', $s); }
function cek_password(string $input, string $db): bool {
    return ter_hash($db) ? password_verify($input, $db) : hash_equals($db, $input);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') gagal('Metode tidak diizinkan', 405);

$in       = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$username = trim($in['username'] ?? '');
$password = (string) ($in['password'] ?? '');
if ($username === '' || $password === '') gagal('Username dan password wajib diisi', 400);

// Batasi percobaan: 5x gagal → tunggu 60 detik
if (($_SESSION['lock'] ?? 0) > time()) gagal('Terlalu banyak percobaan. Coba lagi sebentar lagi.', 429);

try {
    $row = q1($pdo, 'SELECT id_users, nama, password, role FROM users WHERE username = ?', [$username]);
} catch (PDOException $e) {
    gagal('Struktur database belum sesuai. Jalankan database/migrasi_users_role.sql terlebih dahulu.', 500);
}

$tujuan = ['admin' => 'admin/dashboard.html', 'petugas' => 'petugas/dashboard.html', 'siswa' => 'siswa/dashboard.html'];

if (!$row || $row['password'] === null || !isset($tujuan[$row['role']]) || !cek_password($password, $row['password'])) {
    $_SESSION['try'] = ($_SESSION['try'] ?? 0) + 1;
    if ($_SESSION['try'] >= 5) { $_SESSION['lock'] = time() + 60; $_SESSION['try'] = 0; }
    gagal('Username atau password salah');
}

// Upgrade otomatis password teks biasa → hash
if (!ter_hash($row['password'])) {
    q($pdo, 'UPDATE users SET password = ? WHERE id_users = ?', [password_hash($password, PASSWORD_DEFAULT), $row['id_users']]);
}

session_regenerate_id(true);
$_SESSION = ['role' => $row['role'], 'nama' => $row['nama'], 'id_users' => (int) $row['id_users']];
echo json_encode(['ok' => true, 'redirect' => $tujuan[$row['role']], 'nama' => $row['nama']]);