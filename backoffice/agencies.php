<?php
require_once __DIR__ . '/_layout.php';
requirePermission('agencies.view');

if (requestMethod() === 'POST' && !canCreateAgency()) {
    http_response_code(403);
    exit(t('validation.owner_agency_create'));
}
if (requestMethod() === 'POST') {
    requireCsrfPost();
    try {
        $name = trim((string) ($_POST['name'] ?? ''));
        $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
        if ($name === '' || !preg_match('/^[A-Z0-9_-]{2,30}$/', $code)) {
            throw new InvalidArgumentException(t('validation.agency_required_fields'));
        }
        dbExecute(
            "INSERT INTO agencies(name,code,email,phone,address,city,country_code,currency,timezone,status)
             VALUES(:name,:code,:email,:phone,:address,:city,:country,:currency,:timezone,'active')",
            ['name'=>$name,'code'=>$code,'email'=>normalizedEmail($_POST['email']??''),'phone'=>trim((string)($_POST['phone']??'')),'address'=>trim((string)($_POST['address']??'')),'city'=>trim((string)($_POST['city']??'')),'country'=>strtoupper(substr(trim((string)($_POST['country_code']??'MA')),0,2)),'currency'=>strtoupper(substr(trim((string)($_POST['currency']??'MAD')),0,3)),'timezone'=>trim((string)($_POST['timezone']??'Africa/Casablanca'))]
        );
        $id = (int) db()->lastInsertId();
        auditLog('agency.created', 'agency', $id, null, ['name'=>$name,'code'=>$code], $id);
        flash('success', t('message.agency_created'));
    } catch (InvalidArgumentException $exception) {
        flash('danger', $exception->getMessage());
    } catch (Throwable $exception) {
        reportDatabaseError($exception, 'Agency operation failed');
        flash('danger', t('message.agency_failed'));
    }
    safeRedirect('agencies.php');
}

$agencies = dbFetchAll(
    'SELECT a.*,
        (SELECT COUNT(*) FROM vehicles v WHERE v.agency_id=a.id AND v.archived_at IS NULL) vehicles,
        (SELECT COUNT(*) FROM user_agencies ua WHERE ua.agency_id=a.id) users
     FROM agencies a WHERE a.archived_at IS NULL ORDER BY a.name'
);
backofficeHeader(t('page.agencies.title'), 'agencies.php');
pageHeader('page.agencies.title', 'page.agencies.description', [
    'breadcrumbs'=>[['label'=>'nav.overview','href'=>'index.php'],['label'=>'nav.agencies']],
    'primary'=>canCreateAgency() ? ['label'=>'action.add_agency','href'=>'#new-agency'] : null,
]);
?>
<div class="grid">
<?php if (canCreateAgency()): ?>
<section class="card" id="new-agency"><h2><?=e(t('section.new_agency'))?></h2><form method="post"><?=csrfField()?>
<label><?=e(t('field.name'))?><input name="name" required></label><label><?=e(t('field.code'))?><input name="code" required pattern="[A-Za-z0-9_-]{2,30}"></label>
<label><?=e(t('field.email'))?><input type="email" name="email"></label><label><?=e(t('field.phone'))?><input name="phone"></label><label><?=e(t('field.address'))?><input name="address"></label>
<label><?=e(t('field.city'))?><input name="city"></label><label><?=e(t('field.country_code'))?><input name="country_code" value="MA" maxlength="2"></label>
<label><?=e(t('field.currency'))?><input name="currency" value="MAD" maxlength="3"></label><label><?=e(t('field.timezone'))?><input name="timezone" value="Africa/Casablanca"></label>
<button class="btn primary"><?=e(t('action.add_agency'))?></button></form></section>
<?php endif; ?>
<section class="card"><h2><?=e(t('section.agency_register'))?></h2><div class="table-wrap" role="region" aria-label="<?=e(t('section.agency_register'))?>" tabindex="0"><table>
<thead><tr><th scope="col"><?=e(t('field.code'))?></th><th scope="col"><?=e(t('field.name'))?></th><th scope="col"><?=e(t('field.city'))?></th><th scope="col"><?=e(t('nav.fleet'))?></th><th scope="col"><?=e(t('nav.users'))?></th><th scope="col"><?=e(t('common.status'))?></th></tr></thead><tbody>
<?php foreach ($agencies as $agency): ?><tr><td><?=e($agency['code'])?></td><td><?=e($agency['name'])?></td><td><?=e($agency['city'])?></td><td><?=e($agency['vehicles'])?></td><td><?=e($agency['users'])?></td><td><?=statusBadge($agency['status'])?></td></tr><?php endforeach; ?>
</tbody></table><?php if (!$agencies) echo emptyState('empty.no_agencies'); ?></div></section></div>
<?php backofficeFooter();
