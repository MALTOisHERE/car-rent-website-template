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
<!doctype html><html lang="<?=e(language())?>" dir="<?=language()==='ar'?'rtl':'ltr'?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e(appConfig('name'))?> — <?=e(t('auth.sign_out_title'))?></title><link rel="icon" href="../backoffice/assets/img/favicon.png"><link rel="stylesheet" href="../backoffice/assets/app.css?v=<?= e(assetVersion('backoffice/assets/app.css')) ?>"></head><body>
<div class="auth-shell">
<aside class="auth-brand-panel" aria-hidden="true">
<div class="auth-brand-mark"><img class="auth-brand-logo" src="../backoffice/assets/img/aurevo-logo.png" alt="<?=e(appConfig('name'))?>"></div>
<div class="auth-brand-copy"><h2><?= e(t('auth.brand_headline')) ?></h2><p><?= e(t('auth.brand_subheadline')) ?></p></div>
<div class="auth-brand-footer">© <?=e(date('Y'))?> <?=e(appConfig('name'))?></div>
</aside>
<main class="auth-form-panel">
<div class="auth-card">
<h1><?=e(t('auth.sign_out_title'))?></h1>
<p class="auth-lead"><?=e(t('auth.sign_out_prompt'))?></p>
<form method="post"><?= csrfField() ?><button class="btn danger auth-submit" type="submit"><?=e(t('shell.sign_out'))?></button></form>
<div class="auth-links"><a href="../"><?=e(t('common.cancel'))?></a></div>
</div>
</main>
</div>
</body></html>

