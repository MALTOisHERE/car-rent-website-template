<?php
require_once __DIR__ . '/../app/bootstrap.php';
safeRedirect('../account/reset_password.php?lang=ar&token=' . rawurlencode((string)($_GET['token'] ?? '')));
