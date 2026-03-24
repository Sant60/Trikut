<?php

function app_base_url(): string
{
    static $baseUrl = null;

    if ($baseUrl !== null) {
        return $baseUrl;
    }

    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $dir = str_replace('\\', '/', dirname($scriptName));
    $dir = rtrim($dir, '/');

    foreach (['/admin', '/api', '/includes'] as $suffix) {
        if ($dir === $suffix) {
            $dir = '';
            break;
        }

        if ($dir !== '' && substr($dir, -strlen($suffix)) === $suffix) {
            $dir = substr($dir, 0, -strlen($suffix));
            break;
        }
    }

    $baseUrl = $dir === '' || $dir === '.' ? '' : $dir;
    return $baseUrl;
}

function app_url(?string $path = ''): string
{
    $path = trim((string) $path);
    if ($path === '') {
        return app_base_url() !== '' ? app_base_url() . '/' : '/';
    }

    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    $normalized = ltrim(str_replace('\\', '/', $path), '/');
    $base = app_base_url();

    return ($base === '' ? '' : $base) . '/' . $normalized;
}
