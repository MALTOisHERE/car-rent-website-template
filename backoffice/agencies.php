<?php
require_once __DIR__ . '/_layout.php';
requirePermission('agencies.view');

if (requestMethod() === 'POST' && !canCreateAgency()) {
    http_response_code(403);
    exit('Only an owner can create an agency.');
}
if (requestMethod() === 'POST') {
    requireCsrfPost();
    try {
        $name = trim((string) ($_POST['name'] ?? ''));
        $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
        if ($name === '' || !preg_match('/^[A-Z0-9_-]{2,30}$/', $code)) {
            throw new InvalidArgumentException('Agency name and a valid unique code are required.');
        }
        dbExecute(
            "INSERT INTO agencies(name,code,email,phone,address,city,country_code,currency,timezone,status)
             VALUES(:name,:code,:email,:phone,:address,:city,:country,:currency,:timezone,'active')",
            ['name'=>$name,'code'=>$code,'email'=>normalizedEmail($_POST['email']??''),'phone'=>trim((string)($_POST['phone']??'')),'address'=>trim((string)($_POST['address']??'')),'city'=>trim((string)($_POST['city']??'')),'country'=>strtoupper(substr(trim((string)($_POST['country_code']??'MA')),0,2)),'currency'=>strtoupper(substr(trim((string)($_POST['currency']??'MAD')),0,3)),'timezone'=>trim((string)($_POST['timezone']??'Africa/Casablanca'))]
        );
        $id = (int) db()->lastInsertId();
        auditLog('agency.created', 'agency', $id, null, ['name'=>$name,'code'=>$code], $id);
        flash('success', 'Agency created.');
    } catch (InvalidArgumentException $exception) {
        flash('danger', $exception->getMessage());
    } catch (Throwable $exception) {
        reportDatabaseError($exception, 'Agency operation failed');
        flash('danger', 'The agency could not be created.');
    }
    safeRedirect('agencies.php');
}

$agencies = dbFetchAll(
    'SELECT a.*,
        (SELECT COUNT(*) FROM vehicles v WHERE v.agency_id=a.id AND v.archived_at IS NULL) vehicles,
        (SELECT COUNT(*) FROM user_agencies ua WHERE ua.agency_id=a.id) users
     FROM agencies a WHERE a.archived_at IS NULL ORDER BY a.name'
);
backofficeHeader('Agencies', 'agencies.php');
pageHeader('Agencies', 'Manage agency identities, locations, and operational access.', [
    'breadcrumbs'=>[['label'=>'Overview','href'=>'index.php'],['label'=>'Agencies']],
    'primary'=>canCreateAgency() ? ['label'=>'Add agency','href'=>'#new-agency'] : null,
]);
?>
<div class="grid">
<?php if (canCreateAgency()): ?>
<section class="card" id="new-agency"><h2>New agency</h2><form method="post"><?=csrfField()?>
<label>Name<input name="name" required></label><label>Code<input name="code" required pattern="[A-Za-z0-9_-]{2,30}"></label>
<label>Email<input type="email" name="email"></label><label>Phone<input name="phone"></label><label>Address<input name="address"></label>
<label>City<input name="city"></label><label>Country code<input name="country_code" value="MA" maxlength="2"></label>
<label>Currency<input name="currency" value="MAD" maxlength="3"></label><label>Timezone<input name="timezone" value="Africa/Casablanca"></label>
<button class="btn primary">Create agency</button></form></section>
<?php endif; ?>
<section class="card"><h2>Agency register</h2><div class="table-wrap" role="region" aria-label="Agency register" tabindex="0"><table>
<thead><tr><th scope="col">Code</th><th scope="col">Name</th><th scope="col">City</th><th scope="col">Fleet</th><th scope="col">Users</th><th scope="col">Status</th></tr></thead><tbody>
<?php foreach ($agencies as $agency): ?><tr><td><?=e($agency['code'])?></td><td><?=e($agency['name'])?></td><td><?=e($agency['city'])?></td><td><?=e($agency['vehicles'])?></td><td><?=e($agency['users'])?></td><td><?=statusBadge($agency['status'])?></td></tr><?php endforeach; ?>
</tbody></table><?php if (!$agencies) echo emptyState('No agencies found'); ?></div></section></div>
<?php backofficeFooter();
