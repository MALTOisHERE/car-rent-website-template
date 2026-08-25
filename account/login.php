<?php
require_once __DIR__ . '/../app/application.php';

if (isset($_GET['lang']) && in_array($_GET['lang'], supportedLanguages(), true)) {
    $_SESSION['lang'] = $_GET['lang'];
}
if (isAuthenticated()) {
    safeRedirect(currentUserRole() === ROLE_CUSTOMER ? '../portal/' : '../backoffice/');
}

$error = null;
if (requestMethod() === 'POST') {
    verifyCsrfToken();
    $email = normalizedEmail($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    try {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !authenticateCredentials($email, $password)) {
            $error = t('auth.incorrect_credentials');
        } else {
            safeRedirect(currentUserRole() === ROLE_CUSTOMER ? '../portal/' : '../backoffice/');
        }
    } catch (Throwable $exception) {
        reportDatabaseError($exception, 'Authentication failed');
        $error = t('auth.temporarily_unavailable');
    }
}
$rtl = language() === 'ar';
?>
<!doctype html><html lang="<?= e(language()) ?>" dir="<?= $rtl ? 'rtl' : 'ltr' ?>"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e(appConfig('name')) ?> — <?=e(t('auth.sign_in'))?></title>
<link rel="stylesheet" href="../backoffice/assets/app.css?v=<?= e(assetVersion('backoffice/assets/app.css')) ?>"><script src="../backoffice/assets/app.js?v=<?= e(assetVersion('backoffice/assets/app.js')) ?>" defer></script></head><body>
<?php if (($_GET['reason'] ?? '') === 'session'): ?><div class="toast-stack" data-toast-stack aria-live="polite"><div class="alert alert-warning" role="status"><span><?= e(t('auth.session_expired')) ?></span><button type="button" class="alert-close icon-button" data-dismiss-alert aria-label="<?= e(t('shell.dismiss_alert')) ?>">&times;</button></div></div><?php endif; ?>
<div class="auth-shell">
<aside class="auth-brand-panel" aria-hidden="true">
<div class="auth-brand-mark"><span class="product-logo"><?= e(mb_substr((string) appConfig('name'), 0, 1)) ?></span><strong><?= e(appConfig('name')) ?></strong></div>
<div class="auth-brand-copy"><h2><?= e(t('auth.brand_headline')) ?></h2><p><?= e(t('auth.brand_subheadline')) ?></p></div>
<div class="auth-brand-footer">© <?= e(date('Y')) ?> <?= e(appConfig('name')) ?></div>
</aside>
<main class="auth-form-panel">
<div class="auth-card">
<h1><?=e(t('auth.login_title'))?></h1>
<p class="auth-lead"><?=e(t('auth.login_lead'))?></p>
<?php if ($error): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>
<form method="post" novalidate><?= csrfField() ?>
<label class="auth-field"><?=e(t('auth.email'))?><input type="email" name="email" autocomplete="username" required value="<?= e($_POST['email'] ?? '') ?>"></label>
<label class="auth-field"><?=e(t('auth.password'))?><input type="password" name="password" autocomplete="current-password" required></label>
<button class="btn primary auth-submit" type="submit"><?=e(t('auth.sign_in'))?></button></form>
<div class="auth-links"><a href="forgot_password.php?lang=<?= e(language()) ?>"><?=e(t('auth.forgot_password'))?></a><a href="../<?= e(language()) ?>/"><?=e(t('auth.public_website'))?></a></div>
</div>
</main>
</div>
</body></html>
