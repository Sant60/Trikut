<?php

require_once __DIR__ . '/tenant.php';

function ensure_hero_media_table(PDO $pdo): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS hero_media (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            admin_id INT UNSIGNED NULL,
            img VARCHAR(1000) NOT NULL,
            caption VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (admin_id),
            INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    ensure_multi_admin_schema($pdo);
    $ensured = true;
}

function fetch_current_hero_media(PDO $pdo, int $adminId): ?array
{
    ensure_hero_media_table($pdo);

    $stmt = $pdo->prepare('SELECT id, admin_id, img, caption, created_at FROM hero_media WHERE admin_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$adminId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}
