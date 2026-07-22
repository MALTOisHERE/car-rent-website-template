<?php

function protectedDocumentMimeExtensions()
{
    return [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
}

function authorizedProtectedFile(array $metadata)
{
    $required = ['id','storage_path','mime_type'];
    foreach ($required as $field) {
        if (!array_key_exists($field, $metadata)) throw new InvalidArgumentException('not found');
    }
    $path = storedUploadAbsolutePath($metadata['storage_path']);
    if ($path === null) throw new InvalidArgumentException('not found');
    $root = realpath(dirname(__DIR__) . '/storage/uploads');
    $resolved = realpath($path);
    if ($root === false || $resolved === false || !is_file($resolved)) throw new InvalidArgumentException('not found');
    $root = rtrim(str_replace('\\', '/', $root), '/') . '/';
    $resolvedNormalized = str_replace('\\', '/', $resolved);
    if (!str_starts_with($resolvedNormalized, $root)) throw new InvalidArgumentException('not found');
    $allowed = protectedDocumentMimeExtensions();
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $actualMime = (string) $finfo->file($resolved);
    if (!isset($allowed[$actualMime]) || !hash_equals((string)$metadata['mime_type'], $actualMime)) {
        throw new InvalidArgumentException('not found');
    }
    return ['path'=>$resolved,'mime_type'=>$actualMime,'extension'=>$allowed[$actualMime],'size'=>(int)filesize($resolved)];
}

function deliverAuthorizedProtectedFile(array $metadata, $prefix = 'customer-document')
{
    $file = authorizedProtectedFile($metadata);
    $safePrefix = preg_replace('/[^a-z0-9-]/i', '-', (string)$prefix) ?: 'document';
    $filename = $safePrefix . '-' . (int)$metadata['id'] . '.' . $file['extension'];
    header('Content-Type: ' . $file['mime_type']);
    header('Content-Length: ' . (string)$file['size']);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    readfile($file['path']);
}

function protectedFileNotFound()
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Not found.';
}
