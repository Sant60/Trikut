<?php

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/security.php';

function ensure_customer_schema(PDO $rootPdo): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    $rootPdo->exec(
        'CREATE TABLE IF NOT EXISTS customer_users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL,
            mobile VARCHAR(20) NOT NULL,
            password VARCHAR(255) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_login_at DATETIME NULL,
            UNIQUE KEY uniq_customer_users_email (email),
            UNIQUE KEY uniq_customer_users_mobile (mobile)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $rootPdo->exec(
        'CREATE TABLE IF NOT EXISTS customer_order_links (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            customer_id INT UNSIGNED NOT NULL,
            admin_id INT UNSIGNED NOT NULL,
            restaurant_slug VARCHAR(160) NOT NULL,
            restaurant_name VARCHAR(160) NOT NULL,
            tenant_order_id INT UNSIGNED NOT NULL,
            total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status VARCHAR(30) NOT NULL DEFAULT "new",
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_customer_order_links_customer_id (customer_id),
            INDEX idx_customer_order_links_admin_id (admin_id),
            INDEX idx_customer_order_links_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $rootPdo->exec(
        'CREATE TABLE IF NOT EXISTS customer_booking_links (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            customer_id INT UNSIGNED NOT NULL,
            admin_id INT UNSIGNED NOT NULL,
            restaurant_slug VARCHAR(160) NOT NULL,
            restaurant_name VARCHAR(160) NOT NULL,
            tenant_booking_id INT UNSIGNED NOT NULL,
            booking_date DATETIME NOT NULL,
            guest_count SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_customer_booking_links_customer_id (customer_id),
            INDEX idx_customer_booking_links_admin_id (admin_id),
            INDEX idx_customer_booking_links_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $ensured = true;
}

function customer_table_columns(PDO $pdo, string $table): array
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

function ensure_customer_columns_on_tenant_tables(PDO $tenantPdo): void
{
    foreach (['orders', 'bookings'] as $table) {
        $columns = customer_table_columns($tenantPdo, $table);
        if (!isset($columns['customer_id'])) {
            $tenantPdo->exec("ALTER TABLE `{$table}` ADD COLUMN customer_id INT UNSIGNED NULL AFTER id");
        }
        try {
            $tenantPdo->exec("CREATE INDEX `idx_{$table}_customer_id` ON `{$table}` (`customer_id`)");
        } catch (Throwable $e) {
        }
    }
}

function normalize_email(?string $value): string
{
    return strtolower(trim((string) $value));
}

function is_valid_email_address(string $value): bool
{
    return $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
}

function customer_is_logged_in(): bool
{
    ensure_session_started();
    return !empty($_SESSION['customer_id']);
}

function current_customer_id(): int
{
    ensure_session_started();
    return (int) ($_SESSION['customer_id'] ?? 0);
}

function current_customer(PDO $rootPdo): ?array
{
    ensure_customer_schema($rootPdo);
    $customerId = current_customer_id();
    if ($customerId <= 0) {
        return null;
    }

    try {
        $stmt = $rootPdo->prepare('SELECT id, name, email, mobile, is_active, created_at, last_login_at FROM customer_users WHERE id = ? LIMIT 1');
        $stmt->execute([$customerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        return ($row && (int) ($row['is_active'] ?? 1) === 1) ? $row : null;
    } catch (Throwable $e) {
        return null;
    }
}

function find_customer_by_identifier(PDO $rootPdo, string $identifier): ?array
{
    ensure_customer_schema($rootPdo);
    $identifier = normalize_text($identifier, 190);
    if ($identifier === '') {
        return null;
    }

    $mobile = normalize_indian_phone($identifier);
    $email = normalize_email($identifier);

    try {
        if ($mobile !== null) {
            $stmt = $rootPdo->prepare('SELECT * FROM customer_users WHERE mobile = ? LIMIT 1');
            $stmt->execute([$mobile]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        }

        $stmt = $rootPdo->prepare('SELECT * FROM customer_users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function create_customer_account(PDO $rootPdo, string $name, string $email, string $mobile, string $password, string $confirmPassword): array
{
    ensure_customer_schema($rootPdo);

    $errors = [];
    $name = normalize_text($name, 120);
    $email = normalize_email($email);
    $normalizedMobile = normalize_indian_phone($mobile);

    if ($name === '' || !is_valid_person_name($name)) {
        $errors[] = 'Enter a valid full name.';
    }
    if (!is_valid_email_address($email)) {
        $errors[] = 'Enter a valid email address.';
    }
    if ($normalizedMobile === null || !is_valid_indian_phone($normalizedMobile)) {
        $errors[] = 'Enter a valid mobile number.';
    }
    $passwordError = validate_new_password($password);
    if ($passwordError !== null) {
        $errors[] = $passwordError;
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Password confirmation does not match.';
    }

    if (!empty($errors)) {
        return ['errors' => $errors, 'customer_id' => 0];
    }

    try {
        $stmt = $rootPdo->prepare('SELECT id FROM customer_users WHERE email = ? OR mobile = ? LIMIT 1');
        $stmt->execute([$email, $normalizedMobile]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            return ['errors' => ['That email or mobile number is already registered.'], 'customer_id' => 0];
        }

        $stmt = $rootPdo->prepare('INSERT INTO customer_users (name, email, mobile, password, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())');
        $stmt->execute([$name, $email, $normalizedMobile, password_hash($password, PASSWORD_DEFAULT)]);

        return ['errors' => [], 'customer_id' => (int) $rootPdo->lastInsertId()];
    } catch (Throwable $e) {
        return ['errors' => ['Could not create the customer account right now.'], 'customer_id' => 0];
    }
}

function customer_login(array $customer): void
{
    ensure_session_started();
    session_regenerate_id(true);
    $_SESSION['customer_id'] = (int) ($customer['id'] ?? 0);
}

function customer_logout(): void
{
    ensure_session_started();
    unset($_SESSION['customer_id']);
}

function require_customer_login(): void
{
    if (!customer_is_logged_in()) {
        header('Location: ' . app_url('customer/login.php'));
        exit;
    }
}

function customer_profile_url(): string
{
    return app_url('customer/profile.php');
}

function store_customer_order_link(PDO $rootPdo, int $customerId, array $restaurant, int $tenantOrderId, float $total, string $status): void
{
    ensure_customer_schema($rootPdo);

    $stmt = $rootPdo->prepare(
        'INSERT INTO customer_order_links (customer_id, admin_id, restaurant_slug, restaurant_name, tenant_order_id, total, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        $customerId,
        (int) ($restaurant['owner_admin_id'] ?? 0),
        (string) ($restaurant['slug'] ?? ''),
        (string) ($restaurant['name'] ?? 'Restaurant'),
        $tenantOrderId,
        number_format($total, 2, '.', ''),
        $status,
    ]);
}

function store_customer_booking_link(PDO $rootPdo, int $customerId, array $restaurant, int $tenantBookingId, string $bookingDate, int $guestCount): void
{
    ensure_customer_schema($rootPdo);

    $stmt = $rootPdo->prepare(
        'INSERT INTO customer_booking_links (customer_id, admin_id, restaurant_slug, restaurant_name, tenant_booking_id, booking_date, guest_count, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        $customerId,
        (int) ($restaurant['owner_admin_id'] ?? 0),
        (string) ($restaurant['slug'] ?? ''),
        (string) ($restaurant['name'] ?? 'Restaurant'),
        $tenantBookingId,
        $bookingDate,
        $guestCount,
    ]);
}

function fetch_customer_order_history(PDO $rootPdo, int $customerId): array
{
    ensure_customer_schema($rootPdo);

    try {
        $stmt = $rootPdo->prepare(
            'SELECT customer_id, admin_id, restaurant_slug, restaurant_name, tenant_order_id, total, status, created_at
             FROM customer_order_links
             WHERE customer_id = ?
             ORDER BY created_at DESC'
        );
        $stmt->execute([$customerId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function fetch_customer_booking_history(PDO $rootPdo, int $customerId): array
{
    ensure_customer_schema($rootPdo);

    try {
        $stmt = $rootPdo->prepare(
            'SELECT customer_id, admin_id, restaurant_slug, restaurant_name, tenant_booking_id, booking_date, guest_count, created_at
             FROM customer_booking_links
             WHERE customer_id = ?
             ORDER BY created_at DESC'
        );
        $stmt->execute([$customerId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}
