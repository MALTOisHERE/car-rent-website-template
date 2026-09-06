<?php

function csrfToken()
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrfField()
{
    return '<input type="hidden" name="_csrf" value="' . e(csrfToken()) . '">';
}

function verifyCsrfToken($token = null)
{
    $token = $token ?? ($_POST['_csrf'] ?? '');
    if (!is_string($token) || !hash_equals((string) ($_SESSION['_csrf_token'] ?? ''), $token)) {
        if (!headers_sent()) {
            http_response_code(419);
        }
        renderGuardErrorPage(function_exists('t') ? t('auth.session_expired') : 'Your session has expired. Please sign in again.');
    }
}

function requireCsrfPost()
{
    requirePost();
    verifyCsrfToken();
}

