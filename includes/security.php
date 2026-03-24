<?php

function ensure_session_started(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function csrf_token(): string
{
    ensure_session_started();

    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

function verify_csrf_request(?string $token = null): bool
{
    ensure_session_started();
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $token = $token ?? (string) ($_POST['_csrf'] ?? '');

    return is_string($sessionToken) && $sessionToken !== '' && hash_equals($sessionToken, (string) $token);
}

function normalize_text(?string $value, int $maxLength = 255): string
{
    $value = trim((string) $value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength);
    }

    return substr($value, 0, $maxLength);
}

function is_valid_person_name(string $value): bool
{
    return (bool) preg_match('/^[\p{L}][\p{L}\s\'.-]{1,99}$/u', $value);
}

function is_valid_indian_phone(string $value): bool
{
    $normalized = normalize_indian_phone($value);
    if ($normalized === null) {
        return false;
    }

    $blocked = [
        '9999999999',
        '1234567890',
        '0000000000',
    ];

    if (in_array($normalized, $blocked, true)) {
        return false;
    }

    if ((bool) preg_match('/^(\d)\1{9}$/', $normalized)) {
        return false;
    }

    return (bool) preg_match('/^[6-9]\d{9}$/', $normalized);
}

function normalize_indian_phone(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $value = preg_replace('/[\s\-()]+/', '', $value) ?? $value;

    if (str_starts_with($value, '+91')) {
        $value = substr($value, 3);
    } elseif (str_starts_with($value, '91') && strlen($value) > 10) {
        $value = substr($value, 2);
    }

    if (!preg_match('/^\d{10}$/', $value)) {
        return null;
    }

    return $value;
}

function is_valid_positive_money(string $value, float $max = 100000): bool
{
    if ($value === '' || !preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
        return false;
    }

    $number = (float) $value;
    return $number >= 0 && $number <= $max;
}

function is_valid_storage_or_http_path(string $value): bool
{
    if ($value === '') {
        return true;
    }

    if (preg_match('#^https?://#i', $value)) {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    return (bool) preg_match('#^/[A-Za-z0-9/_\-.]+$#', $value);
}
