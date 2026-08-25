<?php

function requestIsHttps()
{
    return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        || (envValue('TRUST_PROXY_HEADERS', '0') === '1'
            && isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
}

function sendSecurityHeaders()
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(self)');
    header("Content-Security-Policy: default-src 'self' https: data:; script-src 'self' https: 'unsafe-inline'; style-src 'self' https: 'unsafe-inline'; img-src 'self' https: data: blob:; font-src 'self' https: data:; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
    if (requestIsHttps()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Cache-busting token for a static asset, so browsers refetch it after every edit instead of serving a stale cached copy. */
function assetVersion($projectRelativePath)
{
    $path = dirname(__DIR__) . '/' . ltrim($projectRelativePath, '/');
    $mtime = @filemtime($path);

    return $mtime !== false ? (string) $mtime : '1';
}

function requestMethod()
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
}

function requirePost()
{
    if (requestMethod() !== 'POST') {
        if (!headers_sent()) {
            http_response_code(405);
            header('Allow: POST');
        }
        exit('Method not allowed.');
    }
}

function safeRedirect($location, $status = 302)
{
    $location = str_replace(["\r", "\n"], '', (string) $location);
    $parts = parse_url($location);
    if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || str_starts_with($location, '//')) {
        $location = '/';
    }

    header('Location: ' . $location, true, $status);
    exit();
}

function flash($type, $message)
{
    $_SESSION['_flash'][$type][] = (string) $message;
}

function consumeFlashes()
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $messages;
}
