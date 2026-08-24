<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['lang'] = 'en';

require __DIR__ . '/../pages/cars.php';
