<?php

require_once __DIR__ . '/vendor/autoload.php';

// Prevent uncaught runtime details from reaching the browser on any page.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
