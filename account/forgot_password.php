<?php
require_once __DIR__ . '/../app/application.php';
if (isset($_GET['lang']) && in_array($_GET['lang'], supportedLanguages(), true)) $_SESSION['lang'] = $_GET['lang'];
$submitted = false;
if (requestMethod() === 'POST') {
    verifyCsrfToken();
    $email = normalizedEmail($_POST['email'] ?? '');
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        try { createPasswordResetRequest($email, language()); } catch (Throwable $exception) { reportDatabaseError($exception, 'Password reset request failed'); }
    }
    $submitted = true;
}
?>
<!doctype html><html lang="<?= e(language()) ?>" dir="<?= language() === 'ar' ? 'rtl' : 'ltr' ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e(appConfig('name')) ?> — <?= e(t('auth.forgot_password_title')) ?></title><link rel="icon" href="../backoffice/assets/img/favicon.png"><link rel="stylesheet" href="../backoffice/assets/app.css?v=<?= e(assetVersion('backoffice/assets/app.css')) ?>"></head><body>
<div class="auth-shell">
<aside class="auth-brand-panel" aria-hidden="true">
<div class="auth-brand-mark"><img class="auth-brand-logo" src="../backoffice/assets/img/aurevo-logo.png" alt="<?= e(appConfig('name')) ?>"></div>
<div class="auth-brand-copy"><h2><?= e(t('auth.brand_headline')) ?></h2><p><?= e(t('auth.brand_subheadline')) ?></p></div>
<div class="auth-brand-footer">© <?= e(date('Y')) ?> <?= e(appConfig('name')) ?></div>
</aside>
<main class="auth-form-panel">
<div class="auth-card">
<h1><?= e(t('auth.forgot_password_title')) ?></h1>
<?php if ($submitted): ?><div class="alert success"><?= e(t('auth.forgot_password_success')) ?></div><?php else: ?><p class="auth-lead"><?= e(t('auth.forgot_password_prompt')) ?></p><form method="post"><?= csrfField() ?><label class="auth-field"><?= e(t('auth.email')) ?><input type="email" name="email" required autocomplete="email"></label><button class="btn primary auth-submit" type="submit"><?= e(t('auth.send_instructions')) ?></button></form><?php endif; ?>
<div class="auth-links"><a href="login.php?lang=<?= e(language()) ?>"><?= e(t('auth.return_to_login')) ?></a></div>
</div>
</main>
</div>
</body></html>

