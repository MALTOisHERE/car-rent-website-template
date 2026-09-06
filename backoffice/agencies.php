<?php
require_once __DIR__ . '/_layout.php';
requirePermission('agencies.view');

$agencies = dbFetchAll(
    'SELECT a.*,
        (SELECT COUNT(*) FROM vehicles v WHERE v.agency_id=a.id AND v.archived_at IS NULL) vehicles,
        (SELECT COUNT(*) FROM user_agencies ua WHERE ua.agency_id=a.id) users
     FROM agencies a WHERE a.archived_at IS NULL ORDER BY a.name'
);
backofficeHeader(t('page.agencies.title'), 'agencies.php');
pageHeader('page.agencies.title', 'page.agencies.description', [
    'breadcrumbs'=>[['label'=>'nav.overview','href'=>'index.php'],['label'=>'nav.agencies']],
    'primary'=>canCreateAgency() ? ['label'=>'action.add_agency','href'=>'agency_form.php'] : null,
]);
?>
<div class="grid">
<section class="card full"><div class="section-card-header"><h2><?=navigationIcon('admin')?><?=e(t('section.agency_register'))?></h2></div><div class="table-wrap" role="region" aria-label="<?=e(t('section.agency_register'))?>" tabindex="0"><table>
<thead><tr><th scope="col"><?=e(t('field.code'))?></th><th scope="col"><?=e(t('field.name'))?></th><th scope="col"><?=e(t('field.subdomain'))?></th><th scope="col"><?=e(t('field.city'))?></th><th scope="col"><?=e(t('nav.fleet'))?></th><th scope="col"><?=e(t('nav.users'))?></th><th scope="col"><?=e(t('common.status'))?></th></tr></thead><tbody>
<?php foreach ($agencies as $agency): ?><tr><td><?=e($agency['code'])?></td><td><?=e($agency['name'])?></td><td><?=isolatedValue($agency['subdomain'], 'code-value')?></td><td><?=e($agency['city'])?></td><td><?=e($agency['vehicles'])?></td><td><?=e($agency['users'])?></td><td><?=statusBadge($agency['status'])?></td></tr><?php endforeach; ?>
</tbody></table><?php if (!$agencies) echo emptyState('empty.no_agencies'); ?></div></section></div>
<?php backofficeFooter();
