<?php

require_once __DIR__ . '/tenant.php';

function site_admin_id(PDO $rootPdo): int
{
    $adminId = get_default_admin_id($rootPdo);
    return $adminId > 0 ? $adminId : 1;
}

function site_pdo(PDO $rootPdo): PDO
{
    return tenant_pdo_for_admin_id($rootPdo, site_admin_id($rootPdo));
}

function site_public_url(string $path = 'index.php'): string
{
    return app_url($path);
}

function site_restaurant_name(): string
{
    return 'Trikut Restaurant & Cafe';
}
