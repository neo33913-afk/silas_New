<?php
require __DIR__ . '/auth.php';
require_role('siswa');

function out(array $d, int $c = 200): never { http_response_code($c); echo json_encode($d); exit; }
function ter_hash(string $s): bool { return (bool) preg_match('/^\$2[aby]\$|^\$argon2/', $s); }
$id = (int) $_SESSION['id_users'];
$in = json_decode(file_get_contents('php://input'), true) ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'GET')
    out(q1($pdo, 'SELECT nama, username, kelas, email, no_hp FROM users WHERE id_users = ?', [$id]) ?? out(['error' => 'Akun tidak ditemukan'], 404));
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['error' => 'Metode tidak diizinkan'], 405);

if (($in['aksi'] ?? '') === 'kontak') {            // nama, username & kelas hanya bisa diubah admin
    $email = trim($in['email'] ?? ''); $hp = trim($in['no_hp'] ?? '');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) out(['error' => 'Format email tidak valid'], 422);
    if ($hp !== '' && !preg_match('/^[0-9+\- ]{5,15}$/', $hp))      out(['error' => 'No. HP tidak valid (5–15 karakter)'], 422);
    q($pdo, 'UPDATE users SET email = ?, no_hp = ? WHERE id_users = ?', [$email ?: null, $hp ?: null, $id]);
    out(['ok' => true]);
}
if (($in['aksi'] ?? '') === 'password') {
    $lama = (string) ($in['lama'] ?? ''); $baru = (string) ($in['baru'] ?? ''); $ulang = (string) ($in['ulang'] ?? '');
    $db = q1($pdo, 'SELECT password FROM users WHERE id_users = ?', [$id])['password'] ?? '';
    if (!(ter_hash($db) ? password_verify($lama, $db) : hash_equals($db, $lama))) out(['error' => 'Password lama salah'], 403);
    if (mb_strlen($baru) < 6) out(['error' => 'Password baru minimal 6 karakter'], 422);
    if ($baru !== $ulang)     out(['error' => 'Konfirmasi password tidak sama'], 422);
    if ($baru === $lama)      out(['error' => 'Password baru harus berbeda dari yang lama'], 422);
    q($pdo, 'UPDATE users SET password = ? WHERE id_users = ?', [password_hash($baru, PASSWORD_DEFAULT), $id]);
    session_regenerate_id(true);
    out(['ok' => true]);
}
out(['error' => 'Aksi tidak dikenal'], 400);