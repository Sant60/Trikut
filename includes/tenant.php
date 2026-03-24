<?php

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/admin_profile.php';

function ensure_multi_admin_schema(PDO $pdo): void
{
    static $ensured = false;

    ensure_admin_profile_schema($pdo);
    ensure_admin_access_schema($pdo);

    if ($ensured) {
        return;
    }

    ensure_table_has_admin_id($pdo, 'menu', true);
    ensure_table_has_admin_id($pdo, 'gallery', true);
    ensure_table_has_admin_id($pdo, 'hero_media', false);
    ensure_table_has_admin_id($pdo, 'orders', false);
    ensure_table_has_admin_id($pdo, 'bookings', false);

    $defaultAdminId = get_default_admin_id($pdo);
    if ($defaultAdminId > 0) {
        foreach (['menu', 'gallery', 'hero_media', 'orders', 'bookings'] as $table) {
            try {
                $stmt = $pdo->prepare("UPDATE `{$table}` SET admin_id = ? WHERE admin_id IS NULL OR admin_id = 0");
                $stmt->execute([$defaultAdminId]);
            } catch (Throwable $e) {
            }
        }
    }

    $ensured = true;
}

function ensure_admin_access_schema(PDO $pdo): void
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

    if (!isset($columns['is_owner'])) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN is_owner TINYINT(1) NOT NULL DEFAULT 0 AFTER password");
    }

    try {
        $stmt = $pdo->query('SELECT COUNT(*) AS c FROM admins WHERE is_owner = 1');
        $count = (int) (($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0));
        if ($count === 0) {
            $defaultAdminId = get_default_admin_id($pdo);
            if ($defaultAdminId > 0) {
                $stmt = $pdo->prepare('UPDATE admins SET is_owner = 1 WHERE id = ? LIMIT 1');
                $stmt->execute([$defaultAdminId]);
            }
        }
    } catch (Throwable $e) {
    }

    $ensured = true;
}

function ensure_table_has_admin_id(PDO $pdo, string $table, bool $afterCreatedAt): void
{
    $columns = [];

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $columns[(string) ($column['Field'] ?? '')] = true;
        }
    } catch (Throwable $e) {
        return;
    }

    if (!isset($columns['admin_id'])) {
        $position = $afterCreatedAt ? ' AFTER created_at' : ' AFTER id';
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN admin_id INT UNSIGNED NULL{$position}");
    }

    try {
        $pdo->exec("CREATE INDEX `idx_{$table}_admin_id` ON `{$table}` (`admin_id`)");
    } catch (Throwable $e) {
    }
}

function get_default_admin_id(PDO $pdo): int
{
    ensure_admin_profile_schema($pdo);

    try {
        $stmt = $pdo->query('SELECT id FROM admins ORDER BY id ASC LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['id'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function find_admin_by_identifier(PDO $pdo, string $identifier): ?array
{
    ensure_admin_profile_schema($pdo);
    ensure_admin_access_schema($pdo);

    $identifier = normalize_text($identifier, 100);
    if ($identifier === '') {
        return null;
    }

    try {
        $mobile = normalize_indian_phone($identifier);
        if ($mobile !== null) {
            $stmt = $pdo->prepare('SELECT id, username, display_name, mobile, photo, password, is_owner, created_at FROM admins WHERE mobile = ? LIMIT 1');
            $stmt->execute([$mobile]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        }

        $stmt = $pdo->prepare('SELECT id, username, display_name, mobile, photo, password, is_owner, created_at FROM admins WHERE username = ? LIMIT 1');
        $stmt->execute([$identifier]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function resolve_public_admin_id(PDO $pdo): int
{
    ensure_session_started();
    ensure_multi_admin_schema($pdo);

    $identifier = normalize_text($_GET['admin'] ?? '', 100);
    if ($identifier !== '') {
        $admin = find_admin_by_identifier($pdo, $identifier);
        if ($admin) {
            $_SESSION['public_admin_id'] = (int) $admin['id'];
            return (int) $admin['id'];
        }
    }

    if (!empty($_SESSION['admin'])) {
        $_SESSION['public_admin_id'] = (int) $_SESSION['admin'];
        return (int) $_SESSION['admin'];
    }

    $sessionAdminId = (int) ($_SESSION['public_admin_id'] ?? 0);
    if ($sessionAdminId > 0) {
        return $sessionAdminId;
    }

    $defaultAdminId = get_default_admin_id($pdo);
    $_SESSION['public_admin_id'] = $defaultAdminId;

    return $defaultAdminId;
}

function create_admin_account(PDO $pdo, string $username, string $displayName, string $mobile, string $password): array
{
    ensure_admin_profile_schema($pdo);
    ensure_admin_access_schema($pdo);

    $errors = [];
    $username = normalize_text($username, 100);
    $displayName = normalize_text($displayName, 120);
    $normalizedMobile = normalize_indian_phone($mobile);

    if ($username === '' || !preg_match('/^[A-Za-z0-9_.-]{3,100}$/', $username)) {
        $errors[] = 'Username must be 3 to 100 characters and use letters, numbers, dots, underscores, or hyphens.';
    }

    if ($displayName === '' || !preg_match('/^[\p{L}\p{N}\s&\'().,-]{2,120}$/u', $displayName)) {
        $errors[] = 'Display name must be between 2 and 120 valid characters.';
    }

    if ($normalizedMobile === null || !is_valid_indian_phone($normalizedMobile)) {
        $errors[] = 'Enter a valid admin mobile number.';
    }

    $passwordError = validate_new_password($password);
    if ($passwordError !== null) {
        $errors[] = $passwordError;
    }

    if (!empty($errors)) {
        return ['errors' => $errors, 'admin_id' => 0];
    }

    try {
        $stmt = $pdo->prepare('SELECT id FROM admins WHERE username = ? OR mobile = ? LIMIT 1');
        $stmt->execute([$username, $normalizedMobile]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            return ['errors' => ['That username or mobile number is already in use.'], 'admin_id' => 0];
        }

        $stmt = $pdo->prepare('INSERT INTO admins (username, display_name, mobile, password, is_owner, created_at) VALUES (?, ?, ?, ?, 0, NOW())');
        $stmt->execute([$username, $displayName, $normalizedMobile, password_hash($password, PASSWORD_DEFAULT)]);

        return ['errors' => [], 'admin_id' => (int) $pdo->lastInsertId()];
    } catch (Throwable $e) {
        return ['errors' => ['Could not create the admin account right now.'], 'admin_id' => 0];
    }
}

function is_owner_admin(PDO $pdo, int $adminId): bool
{
    ensure_admin_access_schema($pdo);

    try {
        $stmt = $pdo->prepare('SELECT is_owner FROM admins WHERE id = ? LIMIT 1');
        $stmt->execute([$adminId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['is_owner'] ?? 0) === 1;
    } catch (Throwable $e) {
        return false;
    }
}

function require_owner_admin(PDO $pdo, int $adminId): void
{
    if (!is_owner_admin($pdo, $adminId)) {
        http_response_code(403);
        exit('Owner access required.');
    }
}
