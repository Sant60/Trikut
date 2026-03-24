<?php

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/media.php';

function ensure_admin_profile_schema(PDO $pdo): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    $columns = [];
    try {
        $stmt = $pdo->query('SHOW COLUMNS FROM admins');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $columns[(string) ($column['Field'] ?? '')] = true;
        }
    } catch (Throwable $e) {
        $columns = [];
    }

    if (!isset($columns['display_name'])) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN display_name VARCHAR(120) NULL AFTER username");
    }

    if (!isset($columns['mobile'])) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN mobile VARCHAR(20) NULL AFTER display_name");
    }

    if (!isset($columns['photo'])) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN photo VARCHAR(1000) NULL AFTER mobile");
    }

    $ensured = true;
}

function fetch_admin_profile(PDO $pdo, int $adminId): ?array
{
    ensure_admin_profile_schema($pdo);

    $stmt = $pdo->prepare('SELECT id, username, display_name, mobile, photo, created_at FROM admins WHERE id = ? LIMIT 1');
    $stmt->execute([$adminId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function validate_admin_profile_payload(array $input): array
{
    $errors = [];

    $username = normalize_text($input['username'] ?? '', 100);
    $displayName = normalize_text($input['display_name'] ?? '', 120);
    $mobile = trim((string) ($input['mobile'] ?? ''));

    if ($username === '' || !preg_match('/^[A-Za-z0-9_.-]{3,100}$/', $username)) {
        $errors[] = 'Username must be 3 to 100 characters and use letters, numbers, dots, underscores, or hyphens.';
    }

    if ($displayName === '' || !preg_match('/^[\p{L}\p{N}\s&\'().,-]{2,120}$/u', $displayName)) {
        $errors[] = 'Display name must be between 2 and 120 valid characters.';
    }

    if ($mobile !== '' && !is_valid_indian_phone($mobile)) {
        $errors[] = 'Enter a valid admin mobile number.';
    }

    return [
        'errors' => $errors,
        'username' => $username,
        'display_name' => $displayName,
        'mobile' => $mobile === '' ? '' : normalize_indian_phone($mobile),
    ];
}

function validate_new_password(?string $password): ?string
{
    $password = (string) $password;
    if ($password === '') {
        return null;
    }

    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters.';
    }

    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Password must include uppercase, lowercase, and a number.';
    }

    return null;
}

function admin_photo_public_dir(): string
{
    return '/assets/admin';
}

function admin_photo_target_dir(): string
{
    return dirname(__DIR__) . '/assets/admin';
}
