<?php
require_once __DIR__ . '/_layout.php';
requirePermission('dashboard.view');

if (!tableExists('agencies')) {
    backofficeHeader('Database setup required', 'index.php');
    echo '<div class="alert danger">Apply the ordered database migrations before using the professional back office.</div>';
    backofficeFooter();
    exit;
}

$agencyIds = currentAgencyIds();
$agencyId = (int) ($_GET['agency_id'] ?? ($agencyIds[0] ?? 0));
requireAgencyAccess($agencyId);
$periodStart = date('Y-m-01 00:00:00');
$periodEnd = date('Y-m-t 23:59:59');
$metrics = [];
$canViewFinancialMetrics = canViewFinancialDashboard();
if ($canViewFinancialMetrics) {
    $metrics = [
        t('nav.payments') => dbFetchOne("SELECT COALESCE(SUM(amount),0) value FROM payments WHERE agency_id=:agency AND status='paid' AND paid_at BETWEEN :start AND :end", ['agency'=>$agencyId,'start'=>$periodStart,'end'=>$periodEnd])['value'],
        t('field.balance') => dbFetchOne("SELECT COALESCE(SUM(remaining_amount),0) value FROM reservations WHERE agency_id=:agency AND status NOT IN ('cancelled','expired')", ['agency'=>$agencyId])['value'],
        t('nav.deposits') => dbFetchOne("SELECT COALESCE(SUM(d.amount-d.retained_amount),0) value FROM deposits d JOIN reservations r ON r.id=d.reservation_id WHERE r.agency_id=:agency AND d.status IN ('received','held','partially_retained')", ['agency'=>$agencyId])['value'],
    ];
}
$vehicleCounts = dbFetchAll('SELECT status, COUNT(*) total FROM vehicles WHERE agency_id=:agency AND archived_at IS NULL GROUP BY status', ['agency'=>$agencyId]);
$upcoming = dbFetchAll("SELECT r.reference,r.pickup_at,r.return_at,r.status,c.first_name,c.last_name,v.registration_number FROM reservations r JOIN customers c ON c.id=r.customer_id LEFT JOIN vehicles v ON v.id=r.vehicle_id WHERE r.agency_id=:agency AND r.status IN ('confirmed','deposit_paid','ready','active') AND r.return_at>=NOW() ORDER BY r.pickup_at LIMIT 10", ['agency'=>$agencyId]);
$alerts = dbFetchAll("SELECT v.registration_number,vd.document_type,vd.expires_at FROM vehicle_documents vd JOIN vehicles v ON v.id=vd.vehicle_id WHERE v.agency_id=:agency AND vd.archived_at IS NULL AND vd.expires_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY) ORDER BY vd.expires_at LIMIT 10", ['agency'=>$agencyId]);

backofficeHeader(t('page.dashboard.title'), 'index.php');
pageHeader('page.dashboard.title', 'page.dashboard.description', [
    'breadcrumbs'=>[['label'=>'nav.overview'],['label'=>'nav.dashboard']],
    'metadata'=>t('shell.agency_context', ['id'=>$agencyId]),
]); ?>
<section class="stat-grid">
<?php
$financeIconByIndex = ['finance', 'commercial', 'finance'];
$financeColorByIndex = ['', 'warning', 'info'];
$metricIndex = 0;
foreach ($metrics as $label=>$value):
?><article class="stat-card"><span class="stat-icon <?= e($financeColorByIndex[$metricIndex] ?? '') ?>"><?= navigationIcon($financeIconByIndex[$metricIndex] ?? 'finance') ?></span><div class="stat-body"><span><?= e($label) ?></span><strong><?= money($value) ?></strong></div></article>
<?php $metricIndex++; endforeach; ?>
<?php
$vehicleStatusColor = ['available'=>'success', 'reserved'=>'info', 'rented'=>'info', 'maintenance'=>'warning', 'damaged'=>'warning'];
foreach ($vehicleCounts as $row):
?><article class="stat-card"><span class="stat-icon <?= e($vehicleStatusColor[$row['status']] ?? 'neutral') ?>"><?= navigationIcon('fleet') ?></span><div class="stat-body"><span><?= e(translatedStatus($row['status']) . ' · ' . t('nav.vehicles')) ?></span><strong><?= e($row['total']) ?></strong></div></article>
<?php endforeach; ?>
</section><div class="grid"><section class="card"><div class="section-card-header"><h2><?= navigationIcon('rentals') ?><?=e(t('section.upcoming_operations'))?></h2></div><?php if (!$upcoming): ?><p class="empty"><?= e(t('empty.no_filtered_records')) ?></p><?php else: ?><div class="table-wrap"><table><tr><th><?=e(t('field.reference'))?></th><th><?=e(t('field.customer'))?></th><th><?=e(t('field.vehicle'))?></th><th><?=e(t('field.pickup'))?></th><th><?=e(t('field.return'))?></th><th><?=e(t('common.status'))?></th></tr><?php foreach($upcoming as $row): ?><tr><td><?= isolatedValue($row['reference'],'reference-value') ?></td><td><?= e($row['first_name'].' '.$row['last_name']) ?></td><td><?= isolatedValue($row['registration_number'],'registration-value') ?></td><td><?= formattedDateTime($row['pickup_at']) ?></td><td><?= formattedDateTime($row['return_at']) ?></td><td><?= statusBadge($row['status']) ?></td></tr><?php endforeach; ?></table></div><?php endif; ?></section>
<section class="card"><div class="section-card-header"><h2><?= navigationIcon('document') ?><?=e(t('section.document_alerts'))?></h2></div><?php if(!$alerts): ?><p class="empty"><?=e(t('empty.no_expirations'))?></p><?php else:?><div class="table-wrap"><table><tr><th><?=e(t('field.vehicle'))?></th><th><?=e(t('field.documents'))?></th><th><?=e(t('field.expires'))?></th></tr><?php foreach($alerts as $row): ?><tr><td><?=isolatedValue($row['registration_number'],'registration-value')?></td><td><?=e(translatedStatus($row['document_type']))?></td><td><?=formattedDate($row['expires_at'])?></td></tr><?php endforeach;?></table></div><?php endif;?></section></div>
<?php backofficeFooter();
