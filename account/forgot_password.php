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
<!doctype html><html lang="<?= e(language()) ?>" dir="<?= language() === 'ar' ? 'rtl' : 'ltr' ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Password reset</title><link rel="stylesheet" href="../backoffice/assets/app.css"></head><body class="auth-page"><main class="auth-card"><h1>Password reset</h1>
<?php if ($submitted): ?><div class="alert success">If the account exists, password reset instructions have been sent.</div><?php else: ?><p>Enter your account email address.</p><form method="post"><?= csrfField() ?><label>Email<input type="email" name="email" required autocomplete="email"></label><button class="btn primary" type="submit">Send instructions</button></form><?php endif; ?>
<div class="auth-links"><a href="login.php?lang=<?= e(language()) ?>">Return to login</a></div></main></body></html>

