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
        renderGuardErrorPage(function_exists('t') ? t('validation.method_not_allowed') : 'Method not allowed.');
    }
}

/**
 * A small, self-contained styled page for low-level request guards (CSRF
 * expiry, wrong HTTP method) that can fire from any area of the app
 * (backoffice, portal, account, the public site) before that area's own
 * header/layout has rendered -- so, unlike a normal page, it can't rely on
 * any shared stylesheet or layout being loadable. Inlines its own minimal
 * styling instead of a bare text response.
 */
function renderGuardErrorPage($message)
{
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
    }
    $title = function_exists('t') ? t('validation.please_try_again') : 'Please try again';
    $backLabel = function_exists('t') ? t('action.go_back') : 'Go back';
    $lang = function_exists('language') ? language() : 'en';
    $dir = $lang === 'ar' ? 'rtl' : 'ltr';
    echo '<!doctype html><html lang="' . e($lang) . '" dir="' . $dir . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . e($title) . '</title>'
        . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f4f6fb;font:400 15px/1.6 -apple-system,"Segoe UI",Inter,sans-serif;color:#182231}'
        . '.card{width:min(26rem,90vw);background:#fff;border-radius:1.15rem;box-shadow:0 24px 60px rgba(15,23,42,.18);padding:2.5rem;text-align:center}'
        . '.icon{width:3rem;height:3rem;margin:0 auto 1.25rem;border-radius:50%;background:#e7e9f6;color:#011468;display:grid;place-items:center;font-size:1.5rem;font-weight:800}'
        . 'h1{margin:0 0 .5rem;font-size:1.25rem;letter-spacing:-.01em}p{margin:0 0 1.5rem;color:#657387}'
        . 'a.btn{display:inline-flex;align-items:center;justify-content:center;padding:.65rem 1.4rem;border-radius:.55rem;background:#011468;color:#fff;font-weight:700;text-decoration:none;cursor:pointer}'
        . 'a.btn:hover{background:#00104f}</style></head><body>'
        . '<div class="card"><div class="icon">!</div><h1>' . e($title) . '</h1><p>' . e($message) . '</p>'
        . '<a class="btn" href="#" onclick="history.back();return false;">' . e($backLabel) . '</a></div>'
        . '</body></html>';
    exit;
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
