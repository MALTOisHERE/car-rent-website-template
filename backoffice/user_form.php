<?php
require_once __DIR__ . '/_layout.php';
requirePermission('users.manage');
$allowedRoles = [ROLE_AGENCY_MANAGER, ROLE_RENTAL_AGENT, ROLE_ACCOUNTANT, ROLE_FLEET_AGENT];
if (currentUserRole() === ROLE_OWNER) array_unshift($allowedRoles, ROLE_OWNER);

if (requestMethod() === 'POST') {
    requireCsrfPost();
    try {
        $name = trim((string) ($_POST['fullname'] ?? ''));
        $email = normalizedEmail($_POST['email'] ?? '');
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $role = validateChoice($_POST['role'] ?? '', $allowedRoles, null);
        $password = (string) ($_POST['password'] ?? '');
        $agencyIds = array_values(array_unique(array_map('intval', $_POST['agency_ids'] ?? [])));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$role) {
            throw new InvalidArgumentException(t('validation.user_required_fields'));
        }
        $passwordErrors = passwordValidationErrors($password);
        if ($passwordErrors) {
            throw new InvalidArgumentException(implode(' ', $passwordErrors));
        }
        if (!$agencyIds && $role !== ROLE_OWNER) {
            throw new InvalidArgumentException(t('validation.assign_agency'));
        }
        foreach ($agencyIds as $agencyId) requireAgencyAccess($agencyId);
        $id = withTransaction(function () use ($name, $email, $phone, $role, $password, $agencyIds) {
            dbExecute('INSERT INTO users(fullname,email,email_normalized,phone,password_hash,role,status,password_changed_at)VALUES(:name,:email,:normalized,:phone,:hash,:role,\'active\',NOW())', ['name'=>$name,'email'=>$email,'normalized'=>$email,'phone'=>$phone,'hash'=>password_hash($password,PASSWORD_DEFAULT),'role'=>$role]);
            $id = (int) db()->lastInsertId();
            foreach ($agencyIds as $index => $agencyId) {
                dbExecute('INSERT INTO user_agencies(user_id,agency_id,is_primary,created_by)VALUES(:user,:agency,:primary,:creator)', ['user'=>$id,'agency'=>$agencyId,'primary'=>$index===0?1:0,'creator'=>currentUserId()]);
            }
            return $id;
        });
        auditLog('user.created', 'user', $id, null, ['email'=>$email,'role'=>$role]);
        flash('success', t('message.employee_created'));
        safeRedirect('users.php');
    } catch (InvalidArgumentException|DomainException $exception) {
        flash('danger', $exception->getMessage());
    } catch (Throwable $exception) {
        reportDatabaseError($exception, 'User management failed');
        flash('danger', t('message.user_failed'));
    }
    safeRedirect('user_form.php');
}

$agencies = dbFetchAll('SELECT id,name FROM agencies WHERE archived_at IS NULL ORDER BY name');

backofficeHeader(t('page.user_create.title'), 'users.php');
pageHeader('page.user_create.title', 'page.user_create.description', [
    'breadcrumbs' => [['label'=>'nav.administration'],['label'=>'nav.users','href'=>'users.php'],['label'=>'page.user_create.title']],
    'secondary' => ['label'=>'common.cancel','href'=>'users.php'],
]);
?>
<section class="card form-card">
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create">
        <div class="grid">
            <label><?= e(t('field.full_name')) ?><input name="fullname" required></label>
            <label><?= e(t('field.email')) ?><input type="email" name="email" required></label>
            <label><?= e(t('field.phone')) ?><input name="phone"></label>
            <label><?= e(t('field.role')) ?><select name="role"><?php foreach ($allowedRoles as $role): ?><option value="<?= e($role) ?>"><?= e(translatedRole($role)) ?></option><?php endforeach; ?></select></label>
            <label><?= e(t('field.temporary_password')) ?><input type="password" name="password" required autocomplete="new-password"><small class="field-hint"><?= e(t('message.password_policy')) ?></small></label>
        </div>
        <fieldset><legend><?= e(t('field.assigned_agencies')) ?></legend><?php foreach ($agencies as $agency): if (currentUserRole() !== ROLE_OWNER && !in_array((int) $agency['id'], currentAgencyIds(), true)) continue; ?><label class="checkbox-label"><input type="checkbox" name="agency_ids[]" value="<?= e($agency['id']) ?>"> <?= e($agency['name']) ?></label><?php endforeach; ?></fieldset>
        <div class="form-actions">
            <button class="btn primary"><?= e(t('action.add_user')) ?></button>
            <a class="btn secondary" href="users.php"><?= e(t('common.cancel')) ?></a>
        </div>
    </form>
</section>
<?php backofficeFooter();
