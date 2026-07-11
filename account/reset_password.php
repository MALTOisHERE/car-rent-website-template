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
        if ($password !== $confirmation) throw new InvalidArgumentException('Password confirmation does not match.');
        if (!resetPasswordWithToken($token, $password)) throw new InvalidArgumentException('The reset link is invalid or expired.');
        $success = true;
    } catch (InvalidArgumentException $exception) { $error = $exception->getMessage(); }
    catch (Throwable $exception) { reportDatabaseError($exception, 'Password reset failed'); $error = 'Password reset is temporarily unavailable.'; }
}
?>
<!doctype html><html lang="<?= e(language()) ?>" dir="<?= language() === 'ar' ? 'rtl' : 'ltr' ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Choose a new password</title><link rel="stylesheet" href="../backoffice/assets/app.css"></head><body class="auth-page"><main class="auth-card"><h1>Choose a new password</h1>
<?php if ($success): ?><div class="alert success">Your password has been changed. Existing sessions were invalidated.</div><a class="btn primary" href="login.php?lang=<?= e(language()) ?>">Sign in</a>
<?php else: ?><?php if ($error): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?><form method="post"><?= csrfField() ?><input type="hidden" name="token" value="<?= e($token) ?>"><label>New password<input type="password" name="password" required autocomplete="new-password"></label><label>Confirm password<input type="password" name="password_confirmation" required autocomplete="new-password"></label><small>At least 12 characters with upper/lowercase, a number, and a symbol.</small><button class="btn primary" type="submit">Reset password</button></form><?php endif; ?>
</main></body></html>

