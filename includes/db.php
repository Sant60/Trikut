<?php
// avoid re-creating PDO if already present
if (isset($pdo) && $pdo instanceof PDO) {
    return;
}

$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'trikut_restaurant';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

$dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // do not expose DB details to users — log and show minimal message
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    echo "Database connection error.";
    exit;
}