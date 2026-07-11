<?php
require_once __DIR__ . '/../app/bootstrap.php';
if (requestMethod() === 'POST') {
    verifyCsrfToken();
    logoutUser();
    safeRedirect('login.php');
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign out</title><link rel="stylesheet" href="../backoffice/assets/app.css"></head><body class="auth-page"><main class="auth-card"><h1>Sign out</h1><p>Confirm that you want to end this session.</p><form method="post"><?= csrfField() ?><button class="btn danger" type="submit">Sign out</button></form><a href="../">Cancel</a></main></body></html>

