<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/i18n.php';
if (isset($_GET['lang']) && in_array($_GET['lang'], supportedLanguages(), true)) {
    $_SESSION['lang'] = $_GET['lang'];
}
if (requestMethod() === 'POST') {
    verifyCsrfToken();
    logoutUser();
    safeRedirect('login.php');
}
?>
<!doctype html><html lang="<?=e(language())?>" dir="<?=language()==='ar'?'rtl':'ltr'?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e(t('auth.sign_out_title'))?></title><link rel="stylesheet" href="../backoffice/assets/app.css"></head><body class="auth-page"><main class="auth-card"><h1><?=e(t('auth.sign_out_title'))?></h1><p><?=e(t('auth.sign_out_prompt'))?></p><form method="post"><?= csrfField() ?><button class="btn danger" type="submit"><?=e(t('shell.sign_out'))?></button></form><a href="../"><?=e(t('common.cancel'))?></a></main></body></html>

