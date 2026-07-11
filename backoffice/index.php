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
$metrics = [
    'Revenue this month' => dbFetchOne("SELECT COALESCE(SUM(amount),0) value FROM payments WHERE agency_id=:agency AND status='paid' AND paid_at BETWEEN :start AND :end", ['agency'=>$agencyId,'start'=>$periodStart,'end'=>$periodEnd])['value'],
    'Unpaid balance' => dbFetchOne("SELECT COALESCE(SUM(remaining_amount),0) value FROM reservations WHERE agency_id=:agency AND status NOT IN ('cancelled','expired')", ['agency'=>$agencyId])['value'],
    'Deposits held' => dbFetchOne("SELECT COALESCE(SUM(d.amount-d.retained_amount),0) value FROM deposits d JOIN reservations r ON r.id=d.reservation_id WHERE r.agency_id=:agency AND d.status IN ('received','held','partially_retained')", ['agency'=>$agencyId])['value'],
];
$vehicleCounts = dbFetchAll('SELECT status, COUNT(*) total FROM vehicles WHERE agency_id=:agency AND archived_at IS NULL GROUP BY status', ['agency'=>$agencyId]);
$upcoming = dbFetchAll("SELECT r.reference,r.pickup_at,r.return_at,r.status,c.first_name,c.last_name,v.registration_number FROM reservations r JOIN customers c ON c.id=r.customer_id LEFT JOIN vehicles v ON v.id=r.vehicle_id WHERE r.agency_id=:agency AND r.status IN ('confirmed','deposit_paid','ready','active') AND r.return_at>=NOW() ORDER BY r.pickup_at LIMIT 10", ['agency'=>$agencyId]);
$alerts = dbFetchAll("SELECT v.registration_number,vd.document_type,vd.expires_at FROM vehicle_documents vd JOIN vehicles v ON v.id=vd.vehicle_id WHERE v.agency_id=:agency AND vd.archived_at IS NULL AND vd.expires_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY) ORDER BY vd.expires_at LIMIT 10", ['agency'=>$agencyId]);

backofficeHeader(t('dashboard'), 'index.php'); ?>
<div class="page-head"><h1><?= e(t('dashboard')) ?></h1><span>Agency #<?= e($agencyId) ?></span></div>
<section class="cards">
<?php foreach ($metrics as $label=>$value): ?><article class="card metric"><span><?= e($label) ?></span><strong><?= money($value) ?></strong></article><?php endforeach; ?>
<?php foreach ($vehicleCounts as $row): ?><article class="card metric"><span><?= e(ucfirst($row['status']).' vehicles') ?></span><strong><?= e($row['total']) ?></strong></article><?php endforeach; ?>
</section><div class="grid" style="margin-top:1rem"><section class="card"><h2>Upcoming pickups and returns</h2><?php if (!$upcoming): ?><p class="empty"><?= e(t('no_results')) ?></p><?php else: ?><div class="table-wrap"><table><tr><th>Reference</th><th>Customer</th><th>Vehicle</th><th>Pickup</th><th>Return</th><th>Status</th></tr><?php foreach($upcoming as $row): ?><tr><td><?= e($row['reference']) ?></td><td><?= e($row['first_name'].' '.$row['last_name']) ?></td><td><?= e($row['registration_number']) ?></td><td><?= e($row['pickup_at']) ?></td><td><?= e($row['return_at']) ?></td><td><?= statusBadge($row['status']) ?></td></tr><?php endforeach; ?></table></div><?php endif; ?></section>
<section class="card"><h2>Document alerts (30 days)</h2><?php if(!$alerts): ?><p class="empty">No upcoming expirations.</p><?php else:?><div class="table-wrap"><table><tr><th>Vehicle</th><th>Document</th><th>Expires</th></tr><?php foreach($alerts as $row): ?><tr><td><?=e($row['registration_number'])?></td><td><?=e($row['document_type'])?></td><td><?=e($row['expires_at'])?></td></tr><?php endforeach;?></table></div><?php endif;?></section></div>
<?php backofficeFooter();
