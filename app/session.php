<?php

function startSecureSession()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        enforceSessionLifetime();
        return;
    }

    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if (requestIsHttps()) {
        ini_set('session.cookie_secure', '1');
    }

    session_name('rental_agency_session');
    session_start();
    enforceSessionLifetime();
}

function enforceSessionLifetime()
{
    $now = time();
    $createdAt = (int) ($_SESSION['_created_at'] ?? $now);
    $lastActivity = (int) ($_SESSION['_last_activity'] ?? $now);

    if (($now - $lastActivity) > appConfig('session_idle_timeout')
        || ($now - $createdAt) > appConfig('session_absolute_timeout')) {
        clearSession();
        session_start();
        $_SESSION['_created_at'] = $now;
    }

    if (!isset($_SESSION['_created_at'])) {
        $_SESSION['_created_at'] = $now;
    }

    $lastRegeneration = (int) ($_SESSION['_regenerated_at'] ?? 0);
    if (($now - $lastRegeneration) >= appConfig('session_regenerate_interval')) {
        session_regenerate_id(true);
        $_SESSION['_regenerated_at'] = $now;
    }

    $_SESSION['_last_activity'] = $now;
}

function clearSession()
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}

