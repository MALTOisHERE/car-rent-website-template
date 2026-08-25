<?php
require_once __DIR__ . '/_layout.php';
requirePermission('dashboard.view');

$userId = currentUserId();
$user = dbFetchOne('SELECT * FROM users WHERE id=:id', ['id' => $userId]);
if (!$user) {
    safeRedirect('index.php');
}

if (requestMethod() === 'POST') {
    requireCsrfPost();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'update_profile') {
            updateOwnProfile($userId, $_POST['fullname'] ?? '', $_POST['phone'] ?? '');
            flash('success', t('message.profile_updated'));
        } elseif ($action === 'change_password') {
            $newPassword = (string) ($_POST['password'] ?? '');
            $confirmation = (string) ($_POST['password_confirmation'] ?? '');
            if ($newPassword !== $confirmation) {
                throw new InvalidArgumentException(t('auth.password_confirmation_mismatch'));
            }
            changeOwnPassword($userId, $_POST['current_password'] ?? '', $newPassword);
            flash('success', t('message.password_changed'));
        }
    } catch (InvalidArgumentException|DomainException $exception) {
        flash('danger', $exception->getMessage());
    } catch (Throwable $exception) {
        reportDatabaseError($exception, 'Profile update failed');
        flash('danger', t('message.profile_failed'));
    }
    safeRedirect('profile.php');
}

backofficeHeader(t('page.profile.title'), 'profile.php');
pageHeader('page.profile.title', 'page.profile.description', [
    'breadcrumbs' => [['label' => 'nav.overview'], ['label' => 'nav.profile']],
]);
?>
<div class="grid">
<section class="card">
<div class="section-card-header"><h2><?= navigationIcon('profile') ?><?= e(t('section.profile_details')) ?></h2></div>
<form method="post"><?= csrfField() ?><input type="hidden" name="action" value="update_profile">
<label><?= e(t('field.full_name')) ?><input name="fullname" value="<?= e($user['fullname']) ?>" required></label>
<label><?= e(t('auth.email')) ?><input value="<?= e($user['email']) ?>" disabled></label>
<label><?= e(t('field.phone')) ?><input name="phone" value="<?= e($user['phone'] ?? '') ?>"></label>
<label><?= e(t('field.role')) ?><input value="<?= e(translatedRole($user['role'])) ?>" disabled></label>
<button class="btn primary"><?= e(t('common.save')) ?></button>
</form>
</section>
<section class="card">
<div class="section-card-header"><h2><?= navigationIcon('security') ?><?= e(t('section.change_password')) ?></h2></div>
<form method="post"><?= csrfField() ?><input type="hidden" name="action" value="change_password">
<label><?= e(t('field.current_password')) ?><input type="password" name="current_password" required autocomplete="current-password"></label>
<label><?= e(t('auth.new_password')) ?><input type="password" name="password" required autocomplete="new-password"><small class="field-hint"><?= e(t('auth.password_requirements')) ?></small></label>
<label><?= e(t('auth.confirm_password')) ?><input type="password" name="password_confirmation" required autocomplete="new-password"></label>
<button class="btn primary"><?= e(t('action.update_password')) ?></button>
</form>
</section>
</div>
<?php backofficeFooter();
