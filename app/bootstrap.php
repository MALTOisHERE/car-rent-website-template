<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/http.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';

ini_set('display_errors', '0');
ini_set('log_errors', '1');

set_exception_handler(function (Throwable $exception) {
    error_log(sprintf('[application] Unhandled %s: %s in %s:%d', get_class($exception), $exception->getMessage(), $exception->getFile(), $exception->getLine()));
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
    }
    echo 'An unexpected error occurred. Please try again later.';
});

sendSecurityHeaders();
startSecureSession();
appConfig();

