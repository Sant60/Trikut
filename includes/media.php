<?php

function normalize_storage_path(?string $path): string
{
    $path = trim((string) $path);
    if ($path === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    return '/' . ltrim(str_replace('\\', '/', $path), '/');
}

function upload_image_file(array $file, string $targetDir, string $publicDir, string $prefix, array &$errors): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = 'Image upload failed.';
        return null;
    }

    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
        $errors[] = 'Failed to create upload directory.';
        return null;
    }

    $tmpName = $file['tmp_name'] ?? '';
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        $errors[] = 'Invalid uploaded file.';
        return null;
    }

    $mime = null;
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpName);
    } elseif (function_exists('mime_content_type')) {
        $mime = mime_content_type($tmpName);
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowed[$mime])) {
        $errors[] = 'Only jpg, png, and webp images are allowed.';
        return null;
    }

    $ext = $allowed[$mime];
    try {
        $fileName = $prefix . '_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    } catch (Throwable $e) {
        $fileName = $prefix . '_' . time() . '_' . uniqid('', true) . '.' . $ext;
    }

    $destination = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . $fileName;
    if (!move_uploaded_file($tmpName, $destination)) {
        $errors[] = 'Failed to save uploaded image.';
        return null;
    }

    return normalize_storage_path(trim($publicDir, '/\\') . '/' . $fileName);
}

function remove_local_project_file(?string $storedPath, string $projectRoot): void
{
    $storedPath = trim((string) $storedPath);
    if ($storedPath === '' || preg_match('#^https?://#i', $storedPath)) {
        return;
    }

    $relative = ltrim(str_replace('\\', '/', $storedPath), '/');
    $fullPath = rtrim($projectRoot, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}
