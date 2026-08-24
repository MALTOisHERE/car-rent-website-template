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
<link rel="stylesheet" href="../backoffice/assets/app.css"></head><body class="auth-page">
<main class="auth-card"><h1><?= e(appConfig('name')) ?></h1><p><?=e(t('auth.login_title'))?></p>
<?php if ($error): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>
<form method="post" novalidate><?= csrfField() ?>
<label><?=e(t('auth.email'))?><input type="email" name="email" autocomplete="username" required value="<?= e($_POST['email'] ?? '') ?>"></label>
<label><?=e(t('auth.password'))?><input type="password" name="password" autocomplete="current-password" required></label>
<button class="btn primary" type="submit"><?=e(t('auth.sign_in'))?></button></form>
<div class="auth-links"><a href="forgot_password.php?lang=<?= e(language()) ?>"><?=e(t('auth.forgot_password'))?></a><a href="../<?= e(language()) ?>/"><?=e(t('auth.public_website'))?></a></div>
</main></body></html>
