<?php

function db_host(): string
{
    return getenv('DB_HOST') ?: 'localhost';
}

function db_name(): string
{
    return getenv('DB_NAME') ?: 'trikut_restaurant';
}

function db_user(): string
{
    return getenv('DB_USER') ?: 'root';
}

function db_pass(): string
{
    return getenv('DB_PASS') ?: '';
}

function db_options(): array
{
    return [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
}

function connect_database(?string $databaseName = null): PDO
{
    $dsn = 'mysql:host=' . db_host() . ';charset=utf8mb4';
    if ($databaseName !== null && $databaseName !== '') {
        $dsn = 'mysql:host=' . db_host() . ';dbname=' . $databaseName . ';charset=utf8mb4';
    }

    return new PDO($dsn, db_user(), db_pass(), db_options());
}

if (isset($pdo) && $pdo instanceof PDO) {
    if (!isset($rootPdo) || !($rootPdo instanceof PDO)) {
        $rootPdo = $pdo;
    }
    return;
}

try {
    $pdo = connect_database(db_name());
    $rootPdo = $pdo;
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    echo 'Database connection error.';
    exit;
}
