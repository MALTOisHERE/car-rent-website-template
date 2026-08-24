<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['lang'] = 'ar';

require __DIR__ . '/../pages/selection.php';
