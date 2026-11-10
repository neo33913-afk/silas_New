<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

// Panggil di baris pertama API dashboard: require_role('admin') / 'petugas' / 'siswa'
function require_role(string $role): void {
    if (($_SESSION['role'] ?? null) !== $role) {
        http_response_code(401);
        echo json_encode(['error' => 'Sesi berakhir, silakan login kembali']);
        exit;
    }
}