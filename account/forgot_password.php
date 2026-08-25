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
<!doctype html><html lang="<?= e(language()) ?>" dir="<?= language() === 'ar' ? 'rtl' : 'ltr' ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e(t('auth.forgot_password_title')) ?></title><link rel="stylesheet" href="../backoffice/assets/app.css?v=<?= e(assetVersion('backoffice/assets/app.css')) ?>"></head><body class="auth-page"><main class="auth-card"><h1><?= e(t('auth.forgot_password_title')) ?></h1>
<?php if ($submitted): ?><div class="alert success"><?= e(t('auth.forgot_password_success')) ?></div><?php else: ?><p><?= e(t('auth.forgot_password_prompt')) ?></p><form method="post"><?= csrfField() ?><label><?= e(t('auth.email')) ?><input type="email" name="email" required autocomplete="email"></label><button class="btn primary" type="submit"><?= e(t('auth.send_instructions')) ?></button></form><?php endif; ?>
<div class="auth-links"><a href="login.php?lang=<?= e(language()) ?>"><?= e(t('auth.return_to_login')) ?></a></div></main></body></html>

