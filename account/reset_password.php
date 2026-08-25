<?php
require_once __DIR__ . '/../app/application.php';
if (isset($_GET['lang']) && in_array($_GET['lang'], supportedLanguages(), true)) $_SESSION['lang'] = $_GET['lang'];
$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$error = null; $success = false;
if (requestMethod() === 'POST') {
    verifyCsrfToken();
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');
    try {
        if ($password !== $confirmation) throw new InvalidArgumentException(t('auth.password_confirmation_mismatch'));
        if (!resetPasswordWithToken($token, $password)) throw new InvalidArgumentException(t('auth.reset_link_invalid'));
        $success = true;
    } catch (InvalidArgumentException $exception) { $error = $exception->getMessage(); }
    catch (Throwable $exception) { reportDatabaseError($exception, 'Password reset failed'); $error = t('auth.reset_password_failed'); }
}
?>
<!doctype html><html lang="<?= e(language()) ?>" dir="<?= language() === 'ar' ? 'rtl' : 'ltr' ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e(appConfig('name')) ?> — <?= e(t('auth.reset_password_title')) ?></title><link rel="icon" href="../backoffice/assets/img/favicon.png"><link rel="stylesheet" href="../backoffice/assets/app.css?v=<?= e(assetVersion('backoffice/assets/app.css')) ?>"></head><body>
<div class="auth-shell">
<aside class="auth-brand-panel" aria-hidden="true">
<div class="auth-brand-mark"><img class="auth-brand-logo" src="../backoffice/assets/img/aurevo-logo.png" alt="<?= e(appConfig('name')) ?>"></div>
<div class="auth-brand-copy"><h2><?= e(t('auth.brand_headline')) ?></h2><p><?= e(t('auth.brand_subheadline')) ?></p></div>
<div class="auth-brand-footer">© <?= e(date('Y')) ?> <?= e(appConfig('name')) ?></div>
</aside>
<main class="auth-form-panel">
<div class="auth-card">
<h1><?= e(t('auth.reset_password_title')) ?></h1>
<?php if ($success): ?><div class="alert success"><?= e(t('auth.reset_password_success')) ?></div><a class="btn primary auth-submit" href="login.php?lang=<?= e(language()) ?>"><?= e(t('auth.sign_in')) ?></a>
<?php else: ?><?php if ($error): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?><form method="post"><?= csrfField() ?><input type="hidden" name="token" value="<?= e($token) ?>"><label class="auth-field"><?= e(t('auth.new_password')) ?><input type="password" name="password" required autocomplete="new-password"></label><label class="auth-field"><?= e(t('auth.confirm_password')) ?><input type="password" name="password_confirmation" required autocomplete="new-password"><small class="field-hint"><?= e(t('auth.password_requirements')) ?></small></label><button class="btn primary auth-submit" type="submit"><?= e(t('auth.reset_password_button')) ?></button></form><?php endif; ?>
</div>
</main>
</div>
</body></html>

