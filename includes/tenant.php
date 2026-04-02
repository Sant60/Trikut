<?php

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/admin_profile.php';
require_once __DIR__ . '/db.php';

function ensure_multi_admin_schema(PDO $rootPdo): void
{
    static $ensured = false;

    ensure_admin_profile_schema($rootPdo);
    ensure_admin_access_schema($rootPdo);
    ensure_admin_tenant_schema($rootPdo);
    ensure_legacy_shared_schema($rootPdo);

    if ($ensured) {
        return;
    }

    $defaultAdminId = get_default_admin_id($rootPdo);
    if ($defaultAdminId > 0) {
        foreach (['menu', 'gallery', 'hero_media', 'orders', 'bookings'] as $table) {
            try {
                $stmt = $rootPdo->prepare("UPDATE `{$table}` SET admin_id = ? WHERE admin_id IS NULL OR admin_id = 0");
                $stmt->execute([$defaultAdminId]);
            } catch (Throwable $e) {
            }
        }
    }

    $ensured = true;
}

function ensure_admin_access_schema(PDO $rootPdo): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    $columns = table_columns($rootPdo, 'admins');
    if (!isset($columns['is_owner'])) {
        $rootPdo->exec("ALTER TABLE admins ADD COLUMN is_owner TINYINT(1) NOT NULL DEFAULT 0 AFTER password");
    }

    if (!isset($columns['is_active'])) {
        $rootPdo->exec("ALTER TABLE admins ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER is_owner");
    }

    if (!isset($columns['role'])) {
        $rootPdo->exec("ALTER TABLE admins ADD COLUMN role VARCHAR(30) NOT NULL DEFAULT 'manager' AFTER is_active");
    }

    try {
        $stmt = $rootPdo->query('SELECT COUNT(*) AS c FROM admins WHERE is_owner = 1');
        $count = (int) (($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0));
        if ($count === 0) {
            $defaultAdminId = get_default_admin_id($rootPdo);
            if ($defaultAdminId > 0) {
                $stmt = $rootPdo->prepare('UPDATE admins SET is_owner = 1 WHERE id = ? LIMIT 1');
                $stmt->execute([$defaultAdminId]);
            }
        }
    } catch (Throwable $e) {
    }

    try {
        $rootPdo->exec("UPDATE admins SET role = 'owner' WHERE is_owner = 1");
        $rootPdo->exec("UPDATE admins SET role = 'manager' WHERE is_owner = 0 AND (role IS NULL OR role = '' OR role NOT IN ('owner','manager','employee'))");
    } catch (Throwable $e) {
    }

    $ensured = true;
}

function ensure_admin_tenant_schema(PDO $rootPdo): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    $columns = table_columns($rootPdo, 'admins');
    if (!isset($columns['tenant_db'])) {
        $rootPdo->exec("ALTER TABLE admins ADD COLUMN tenant_db VARCHAR(128) NULL AFTER mobile");
    }

    if (!isset($columns['tenant_initialized'])) {
        $rootPdo->exec("ALTER TABLE admins ADD COLUMN tenant_initialized TINYINT(1) NOT NULL DEFAULT 0 AFTER tenant_db");
    }

    $ensured = true;
}

function ensure_legacy_shared_schema(PDO $rootPdo): void
{
    foreach (['menu', 'gallery', 'hero_media', 'orders', 'bookings'] as $table) {
        $columns = table_columns($rootPdo, $table);
        if (empty($columns)) {
            continue;
        }

        if (!isset($columns['admin_id'])) {
            $position = $table === 'menu' || $table === 'gallery' ? ' AFTER created_at' : ' AFTER id';
            $rootPdo->exec("ALTER TABLE `{$table}` ADD COLUMN admin_id INT UNSIGNED NULL{$position}");
        }

        try {
            $rootPdo->exec("CREATE INDEX `idx_{$table}_admin_id` ON `{$table}` (`admin_id`)");
        } catch (Throwable $e) {
        }
    }
}

function table_columns(PDO $pdo, string $table): array
{
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
        $columns = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $columns[(string) ($column['Field'] ?? '')] = true;
        }
        return $columns;
    } catch (Throwable $e) {
        return [];
    }
}

function get_default_admin_id(PDO $rootPdo): int
{
    ensure_admin_profile_schema($rootPdo);

    try {
        $stmt = $rootPdo->query('SELECT id FROM admins ORDER BY id ASC LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['id'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function find_admin_by_identifier(PDO $rootPdo, string $identifier): ?array
{
    ensure_multi_admin_schema($rootPdo);

    $identifier = normalize_text($identifier, 100);
    if ($identifier === '') {
        return null;
    }

    try {
        $mobile = normalize_indian_phone($identifier);
        if ($mobile !== null) {
            $stmt = $rootPdo->prepare('SELECT id, username, display_name, mobile, tenant_db, tenant_initialized, photo, password, is_owner, is_active, role, created_at FROM admins WHERE mobile = ? LIMIT 1');
            $stmt->execute([$mobile]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        }

        $stmt = $rootPdo->prepare('SELECT id, username, display_name, mobile, tenant_db, tenant_initialized, photo, password, is_owner, is_active, role, created_at FROM admins WHERE username = ? LIMIT 1');
        $stmt->execute([$identifier]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function resolve_public_admin_id(PDO $rootPdo): int
{
    ensure_session_started();
    ensure_multi_admin_schema($rootPdo);

    $identifier = normalize_text($_GET['admin'] ?? '', 100);
    if ($identifier !== '') {
        $admin = find_admin_by_identifier($rootPdo, $identifier);
        if ($admin && (int) ($admin['is_active'] ?? 1) === 1) {
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

    $defaultAdminId = get_default_admin_id($rootPdo);
    $_SESSION['public_admin_id'] = $defaultAdminId;

    return $defaultAdminId;
}

function normalize_admin_role(?string $role, bool $isOwner = false): string
{
    if ($isOwner) {
        return 'owner';
    }

    $role = strtolower(trim((string) $role));
    if (in_array($role, ['manager', 'employee'], true)) {
        return $role;
    }

    return 'manager';
}

function admin_role_label(string $role): string
{
    return match ($role) {
        'owner' => 'Owner',
        'employee' => 'Employee',
        default => 'Manager',
    };
}

function admin_role(PDO $rootPdo, int $adminId): string
{
    ensure_admin_access_schema($rootPdo);

    try {
        $stmt = $rootPdo->prepare('SELECT is_owner, role FROM admins WHERE id = ? LIMIT 1');
        $stmt->execute([$adminId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return normalize_admin_role((string) ($row['role'] ?? ''), (int) ($row['is_owner'] ?? 0) === 1);
    } catch (Throwable $e) {
        return 'employee';
    }
}

function admin_can(PDO $rootPdo, int $adminId, string $permission): bool
{
    $role = admin_role($rootPdo, $adminId);

    if ($role === 'owner') {
        return true;
    }

    $managerPermissions = [
        'manage_menu',
        'manage_gallery',
        'manage_hero',
        'manage_orders',
        'manage_bookings',
    ];

    $employeePermissions = [
        'view_dashboard',
        'view_menu',
        'view_gallery',
        'view_orders',
        'view_bookings',
        'view_hero',
    ];

    if ($role === 'manager') {
        return in_array($permission, array_merge($managerPermissions, $employeePermissions), true);
    }

    return in_array($permission, $employeePermissions, true);
}

function require_admin_permission(PDO $rootPdo, int $adminId, string $permission): void
{
    if (!admin_can($rootPdo, $adminId, $permission)) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
}

function create_admin_account(PDO $rootPdo, string $username, string $displayName, string $mobile, string $password, string $role = 'manager'): array
{
    ensure_multi_admin_schema($rootPdo);

    $errors = [];
    $username = normalize_text($username, 100);
    $displayName = normalize_text($displayName, 120);
    $normalizedMobile = normalize_indian_phone($mobile);
    $role = normalize_admin_role($role, false);

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
        $stmt = $rootPdo->prepare('SELECT id FROM admins WHERE username = ? OR mobile = ? LIMIT 1');
        $stmt->execute([$username, $normalizedMobile]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            return ['errors' => ['That username or mobile number is already in use.'], 'admin_id' => 0];
        }

        $stmt = $rootPdo->prepare('INSERT INTO admins (username, display_name, mobile, password, is_owner, is_active, role, created_at) VALUES (?, ?, ?, ?, 0, 1, ?, NOW())');
        $stmt->execute([$username, $displayName, $normalizedMobile, password_hash($password, PASSWORD_DEFAULT), $role]);
        $adminId = (int) $rootPdo->lastInsertId();

        return ['errors' => [], 'admin_id' => $adminId];
    } catch (Throwable $e) {
        return ['errors' => ['Could not create the admin account right now.'], 'admin_id' => 0];
    }
}

function admin_database_name(int $adminId, string $username): string
{
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $username) ?? 'admin');
    $slug = trim($slug, '_');
    if ($slug === '') {
        $slug = 'admin';
    }

    return 'trikut_admin_' . $adminId . '_' . substr($slug, 0, 40);
}

function ensure_admin_tenant_database(PDO $rootPdo, int $adminId, bool $migrateLegacy = true): PDO
{
    ensure_multi_admin_schema($rootPdo);

    $stmt = $rootPdo->prepare('SELECT id, username, tenant_db, tenant_initialized FROM admins WHERE id = ? LIMIT 1');
    $stmt->execute([$adminId]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        throw new RuntimeException('Admin not found.');
    }

    $tenantDb = (string) ($admin['tenant_db'] ?? '');
    if ($tenantDb === '') {
        $tenantDb = admin_database_name($adminId, (string) ($admin['username'] ?? 'admin'));
        $stmt = $rootPdo->prepare('UPDATE admins SET tenant_db = ? WHERE id = ? LIMIT 1');
        $stmt->execute([$tenantDb, $adminId]);
    }

    create_database_if_missing($tenantDb);
    $tenantPdo = connect_database($tenantDb);
    ensure_tenant_database_tables($tenantPdo);

    $isInitialized = (int) ($admin['tenant_initialized'] ?? 0) === 1;
    if (!$isInitialized) {
        if ($migrateLegacy) {
            migrate_legacy_admin_data($rootPdo, $tenantPdo, $adminId);
        }

        $stmt = $rootPdo->prepare('UPDATE admins SET tenant_initialized = 1 WHERE id = ? LIMIT 1');
        $stmt->execute([$adminId]);
    }

    return $tenantPdo;
}

function create_database_if_missing(string $databaseName): void
{
    $serverPdo = connect_database(null);
    $safeName = str_replace('`', '``', $databaseName);
    $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `{$safeName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}

function ensure_tenant_database_tables(PDO $tenantPdo): void
{
    $tenantPdo->exec(
        'CREATE TABLE IF NOT EXISTS menu (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            img VARCHAR(1000) NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (active),
            INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $tenantPdo->exec(
        'CREATE TABLE IF NOT EXISTS gallery (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            img VARCHAR(1000) NOT NULL,
            caption VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $tenantPdo->exec(
        'CREATE TABLE IF NOT EXISTS hero_media (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            img VARCHAR(1000) NOT NULL,
            caption VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $tenantPdo->exec(
        'CREATE TABLE IF NOT EXISTS orders (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            customer_id INT UNSIGNED NULL,
            name VARCHAR(255) NOT NULL,
            phone VARCHAR(40) NOT NULL,
            total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            items TEXT NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT "new",
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (customer_id),
            INDEX (phone),
            INDEX (created_at),
            INDEX (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $tenantPdo->exec(
        'CREATE TABLE IF NOT EXISTS bookings (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            customer_id INT UNSIGNED NULL,
            name VARCHAR(255) NOT NULL,
            phone VARCHAR(40) NULL,
            date DATETIME NOT NULL,
            size SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (customer_id),
            INDEX (phone),
            INDEX (date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function migrate_legacy_admin_data(PDO $rootPdo, PDO $tenantPdo, int $adminId): void
{
    $tenantHasData = false;
    foreach (['menu', 'gallery', 'hero_media', 'orders', 'bookings'] as $table) {
        try {
            $stmt = $tenantPdo->query("SELECT COUNT(*) AS c FROM `{$table}`");
            $count = (int) (($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0));
            if ($count > 0) {
                $tenantHasData = true;
                break;
            }
        } catch (Throwable $e) {
        }
    }

    if ($tenantHasData) {
        return;
    }

    migrate_legacy_table_rows($rootPdo, $tenantPdo, $adminId, 'menu', ['id', 'name', 'description', 'price', 'img', 'active', 'created_at']);
    migrate_legacy_table_rows($rootPdo, $tenantPdo, $adminId, 'gallery', ['id', 'img', 'caption', 'created_at']);
    migrate_legacy_table_rows($rootPdo, $tenantPdo, $adminId, 'hero_media', ['id', 'img', 'caption', 'created_at']);
    migrate_legacy_table_rows($rootPdo, $tenantPdo, $adminId, 'orders', ['id', 'name', 'phone', 'total', 'items', 'status', 'created_at']);
    migrate_legacy_table_rows($rootPdo, $tenantPdo, $adminId, 'bookings', ['id', 'name', 'phone', 'date', 'size', 'created_at']);
}

function migrate_legacy_table_rows(PDO $rootPdo, PDO $tenantPdo, int $adminId, string $table, array $columns): void
{
    $rootColumns = table_columns($rootPdo, $table);
    if (empty($rootColumns) || !isset($rootColumns['admin_id'])) {
        return;
    }

    $columnList = implode(', ', $columns);
    try {
        $stmt = $rootPdo->prepare("SELECT {$columnList} FROM `{$table}` WHERE admin_id = ? ORDER BY id ASC");
        $stmt->execute([$adminId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) {
            return;
        }

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $insertStmt = $tenantPdo->prepare(
            'INSERT INTO `' . $table . '` (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')'
        );

        foreach ($rows as $row) {
            $values = [];
            foreach ($columns as $column) {
                $values[] = $row[$column] ?? null;
            }
            $insertStmt->execute($values);
        }
    } catch (Throwable $e) {
    }
}

function tenant_pdo_for_admin_id(PDO $rootPdo, int $adminId, bool $migrateLegacy = true): PDO
{
    return ensure_admin_tenant_database($rootPdo, $adminId, $migrateLegacy);
}

function tenant_database_name(PDO $rootPdo, int $adminId): string
{
    ensure_multi_admin_schema($rootPdo);

    try {
        $stmt = $rootPdo->prepare('SELECT tenant_db FROM admins WHERE id = ? LIMIT 1');
        $stmt->execute([$adminId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (string) ($row['tenant_db'] ?? '');
    } catch (Throwable $e) {
        return '';
    }
}

function is_owner_admin(PDO $rootPdo, int $adminId): bool
{
    ensure_admin_access_schema($rootPdo);

    try {
        $stmt = $rootPdo->prepare('SELECT is_owner FROM admins WHERE id = ? LIMIT 1');
        $stmt->execute([$adminId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['is_owner'] ?? 0) === 1;
    } catch (Throwable $e) {
        return false;
    }
}

function require_owner_admin(PDO $rootPdo, int $adminId): void
{
    if (!is_owner_admin($rootPdo, $adminId)) {
        http_response_code(403);
        exit('Owner access required.');
    }
}
