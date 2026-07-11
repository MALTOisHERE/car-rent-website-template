<?php
require_once __DIR__ . '/../app/bootstrap.php';
safeRedirect('../account/reset_password.php?lang=en&token=' . rawurlencode((string)($_GET['token'] ?? '')));
