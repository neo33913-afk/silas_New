<?php
require __DIR__ . '/auth.php';

function out(array $d, int $c = 200): never { http_response_code($c); echo json_encode($d); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['error' => 'Metode tidak diizinkan'], 405);

$in = json_decode(file_get_contents('php://input'), true) ?? [];

// Jebakan bot: kolom tersembunyi harus kosong
if (!empty($in['website'])) out(['ok' => true], 201);

// Batasi: maks 5 pendaftaran per sesi per jam
$_SESSION['reg'] = array_values(array_filter($_SESSION['reg'] ?? [], fn($t) => $t > time() - 3600));
if (count($_SESSION['reg']) >= 5) out(['error' => 'Terlalu banyak pendaftaran. Coba lagi nanti.'], 429);

$nama = trim($in['nama'] ?? '');   $kelas = trim($in['kelas'] ?? '');  $user = trim($in['username'] ?? '');
$pass = (string) ($in['password'] ?? ''); $ulang = (string) ($in['ulang'] ?? '');
$hp = trim($in['no_hp'] ?? '');    $email = trim($in['email'] ?? '');

if ($nama === '' || mb_strlen($nama) > 100)               out(['error' => 'Nama wajib diisi (maks 100 karakter)'], 422);
if ($kelas === '' || mb_strlen($kelas) > 20)              out(['error' => 'Kelas wajib diisi (maks 20 karakter)'], 422);
if (!preg_match('/^[A-Za-z0-9_.]{3,20}$/', $user))        out(['error' => 'Username 3–20 karakter: huruf, angka, titik, atau garis bawah'], 422);
if (mb_strlen($pass) < 6)                                 out(['error' => 'Password minimal 6 karakter'], 422);
if ($pass !== $ulang)                                     out(['error' => 'Konfirmasi password tidak sama'], 422);
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) out(['error' => 'Format email tidak valid'], 422);
if ($hp !== '' && !preg_match('/^[0-9+\- ]{5,15}$/', $hp)) out(['error' => 'No. HP tidak valid (5–15 karakter)'], 422);

try {
    // Role SELALU 'siswa' — input role dari pengguna diabaikan. Hanya admin yang bisa mengubahnya (halaman Pengguna).
    q($pdo, "INSERT INTO users (nama, username, password, role, kelas, no_hp, email) VALUES (?,?,?, 'siswa', ?,?,?)",
      [$nama, $user, password_hash($pass, PASSWORD_DEFAULT), $kelas, $hp ?: null, $email ?: null]);
} catch (PDOException $e) {
    out(['error' => ($e->errorInfo[1] ?? 0) == 1062 ? 'Username sudah dipakai, coba yang lain' : 'Kesalahan database'], ($e->errorInfo[1] ?? 0) == 1062 ? 409 : 500);
}
$_SESSION['reg'][] = time();
out(['ok' => true], 201);