<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['lang'] = 'fr';

require __DIR__ . '/../pages/confirm_reservation.php';
