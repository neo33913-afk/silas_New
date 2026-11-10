<?php
// Konfigurasi database (XAMPP default)
const DB_HOST = 'localhost';
const DB_NAME = 'db_peminjaman_laptop';
const DB_USER = 'root';
const DB_PASS = '';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Koneksi database gagal']);
    exit;
}

function q(PDO $pdo, string $sql, array $p = []): array {
    $st = $pdo->prepare($sql);
    $st->execute($p);
    return $st->fetchAll();
}
function q1(PDO $pdo, string $sql, array $p = []) {
    $r = q($pdo, $sql, $p);
    return $r[0] ?? null;
}