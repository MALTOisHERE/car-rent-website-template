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
    // Longer, higher-entropy session IDs than PHP's default (32 chars / 128
    // bits): 48 chars at 6 bits/char = 288 bits, making the session
    // identifier dramatically harder to guess or brute-force.
    ini_set('session.sid_length', '48');
    ini_set('session.sid_bits_per_character', '6');
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

