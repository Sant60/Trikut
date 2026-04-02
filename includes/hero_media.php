<?php

function ensure_hero_media_table(PDO $tenantPdo): void
{
    static $ensured = [];

    $key = spl_object_hash($tenantPdo);
    if (isset($ensured[$key])) {
        return;
    }

    $tenantPdo->exec(
        'CREATE TABLE IF NOT EXISTS hero_media (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            img VARCHAR(1000) NOT NULL,
            caption VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $ensured[$key] = true;
}

function fetch_current_hero_media(PDO $tenantPdo, int $adminId = 0): ?array
{
    ensure_hero_media_table($tenantPdo);

    $stmt = $tenantPdo->query('SELECT id, img, caption, created_at FROM hero_media ORDER BY id DESC LIMIT 1');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}
