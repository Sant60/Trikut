<?php

require_once __DIR__ . '/security.php';

function ensure_restaurant_schema(PDO $rootPdo): void
{
    static $ensured = false;
    static $backfilling = false;

    if ($ensured) {
        return;
    }

    $rootPdo->exec(
        'CREATE TABLE IF NOT EXISTS restaurants (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            owner_admin_id INT UNSIGNED NOT NULL,
            name VARCHAR(160) NOT NULL,
            slug VARCHAR(160) NOT NULL,
            phone VARCHAR(20) NULL,
            email VARCHAR(190) NULL,
            address VARCHAR(255) NULL,
            logo VARCHAR(1000) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_restaurants_owner_admin_id (owner_admin_id),
            UNIQUE KEY uniq_restaurants_slug (slug),
            INDEX idx_restaurants_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $ensured = true;
    if (!$backfilling) {
        $backfilling = true;
        backfill_restaurants_for_admins($rootPdo);
        $backfilling = false;
    }
}

function restaurant_slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '';
    $value = trim($value, '-');

    return $value !== '' ? substr($value, 0, 160) : 'restaurant';
}

function unique_restaurant_slug(PDO $rootPdo, string $candidate, int $excludeRestaurantId = 0): string
{
    ensure_restaurant_schema($rootPdo);

    $base = restaurant_slugify($candidate);
    $slug = $base;
    $suffix = 1;

    while (restaurant_slug_exists($rootPdo, $slug, $excludeRestaurantId)) {
        $suffix++;
        $slug = substr($base, 0, max(1, 155 - strlen((string) $suffix))) . '-' . $suffix;
    }

    return $slug;
}

function restaurant_slug_exists(PDO $rootPdo, string $slug, int $excludeRestaurantId = 0): bool
{
    try {
        if ($excludeRestaurantId > 0) {
            $stmt = $rootPdo->prepare('SELECT id FROM restaurants WHERE slug = ? AND id != ? LIMIT 1');
            $stmt->execute([$slug, $excludeRestaurantId]);
        } else {
            $stmt = $rootPdo->prepare('SELECT id FROM restaurants WHERE slug = ? LIMIT 1');
            $stmt->execute([$slug]);
        }

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return true;
    }
}

function backfill_restaurants_for_admins(PDO $rootPdo): void
{
    try {
        $stmt = $rootPdo->query(
            'SELECT a.id, a.display_name, a.username, a.mobile
             FROM admins a
             LEFT JOIN restaurants r ON r.owner_admin_id = a.id
             WHERE r.id IS NULL
             ORDER BY a.id ASC'
        );
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return;
    }

    foreach ($admins as $admin) {
        $name = normalize_text((string) ($admin['display_name'] ?? ''), 160);
        if ($name === '') {
            $name = normalize_text((string) ($admin['username'] ?? ''), 160);
        }
        if ($name === '') {
            $name = 'Restaurant ' . (int) ($admin['id'] ?? 0);
        }

        $slugSource = $name !== '' ? $name : ((string) ($admin['username'] ?? 'restaurant'));
        $slug = unique_restaurant_slug($rootPdo, $slugSource);

        try {
            $insert = $rootPdo->prepare(
                'INSERT INTO restaurants (owner_admin_id, name, slug, phone, is_active, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 1, NOW(), NOW())'
            );
            $insert->execute([
                (int) ($admin['id'] ?? 0),
                $name,
                $slug,
                normalize_indian_phone((string) ($admin['mobile'] ?? '')),
            ]);
        } catch (Throwable $e) {
        }
    }
}

function create_restaurant_for_admin(PDO $rootPdo, int $adminId, string $name, string $slug = '', ?string $phone = null): array
{
    ensure_restaurant_schema($rootPdo);

    $errors = [];
    $name = normalize_text($name, 160);
    $phone = $phone !== null ? normalize_indian_phone($phone) : null;

    if ($name === '') {
        $errors[] = 'Restaurant name is required.';
    }

    $slug = $slug !== '' ? restaurant_slugify($slug) : restaurant_slugify($name);
    if ($slug === '') {
        $slug = 'restaurant';
    }
    $slug = unique_restaurant_slug($rootPdo, $slug);

    if (!empty($errors)) {
        return ['errors' => $errors, 'restaurant_id' => 0, 'slug' => ''];
    }

    try {
        $stmt = $rootPdo->prepare(
            'INSERT INTO restaurants (owner_admin_id, name, slug, phone, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, 1, NOW(), NOW())'
        );
        $stmt->execute([$adminId, $name, $slug, $phone]);

        return [
            'errors' => [],
            'restaurant_id' => (int) $rootPdo->lastInsertId(),
            'slug' => $slug,
        ];
    } catch (Throwable $e) {
        return ['errors' => ['Could not create the restaurant profile.'], 'restaurant_id' => 0, 'slug' => ''];
    }
}

function restaurant_for_admin(PDO $rootPdo, int $adminId): ?array
{
    ensure_restaurant_schema($rootPdo);

    try {
        $stmt = $rootPdo->prepare('SELECT * FROM restaurants WHERE owner_admin_id = ? LIMIT 1');
        $stmt->execute([$adminId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function find_restaurant_by_slug(PDO $rootPdo, string $slug, bool $activeOnly = true): ?array
{
    ensure_restaurant_schema($rootPdo);

    $slug = restaurant_slugify($slug);
    if ($slug === '') {
        return null;
    }

    try {
        if ($activeOnly) {
            $stmt = $rootPdo->prepare('SELECT * FROM restaurants WHERE slug = ? AND is_active = 1 LIMIT 1');
        } else {
            $stmt = $rootPdo->prepare('SELECT * FROM restaurants WHERE slug = ? LIMIT 1');
        }
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function active_restaurants(PDO $rootPdo): array
{
    ensure_restaurant_schema($rootPdo);

    try {
        $stmt = $rootPdo->query(
            'SELECT r.*, a.display_name AS owner_name
             FROM restaurants r
             INNER JOIN admins a ON a.id = r.owner_admin_id
             WHERE r.is_active = 1 AND a.is_active = 1
             ORDER BY r.name ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function resolve_public_restaurant(PDO $rootPdo): ?array
{
    ensure_session_started();
    ensure_restaurant_schema($rootPdo);

    $slug = restaurant_slugify((string) ($_GET['restaurant'] ?? ''));
    if ($slug !== '') {
        $restaurant = find_restaurant_by_slug($rootPdo, $slug, true);
        if ($restaurant) {
            $_SESSION['public_restaurant_slug'] = $slug;
            return $restaurant;
        }
    }

    $sessionSlug = restaurant_slugify((string) ($_SESSION['public_restaurant_slug'] ?? ''));
    if ($sessionSlug !== '') {
        $restaurant = find_restaurant_by_slug($rootPdo, $sessionSlug, true);
        if ($restaurant) {
            return $restaurant;
        }
    }

    if (!empty($_SESSION['admin'])) {
        $restaurant = restaurant_for_admin($rootPdo, (int) $_SESSION['admin']);
        if ($restaurant && (int) ($restaurant['is_active'] ?? 1) === 1) {
            $_SESSION['public_restaurant_slug'] = (string) $restaurant['slug'];
            return $restaurant;
        }
    }

    $restaurants = active_restaurants($rootPdo);
    if (!empty($restaurants)) {
        $_SESSION['public_restaurant_slug'] = (string) ($restaurants[0]['slug'] ?? '');
        return $restaurants[0];
    }

    return null;
}

function restaurant_public_url(array $restaurant, string $path = 'index.php'): string
{
    $path = trim($path) !== '' ? trim($path) : 'index.php';
    $separator = strpos($path, '?') === false ? '?' : '&';

    return app_url($path . $separator . 'restaurant=' . rawurlencode((string) ($restaurant['slug'] ?? '')));
}
