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
$agencies = dbFetchAll('SELECT id,name FROM agencies WHERE archived_at IS NULL ORDER BY name');
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

$revenueTrend = null;
if ($canViewFinancialMetrics) {
    $revenueRows = dbFetchAll("SELECT DATE(paid_at) day, SUM(amount) total FROM payments WHERE agency_id=:agency AND status='paid' AND paid_at>=DATE_SUB(CURDATE(),INTERVAL 29 DAY) GROUP BY DATE(paid_at)", ['agency'=>$agencyId]);
    $revenueByDay = [];
    foreach ($revenueRows as $row) $revenueByDay[$row['day']] = (float) $row['total'];
    $revenueTrend = ['labels'=>[], 'values'=>[]];
    for ($i = 29; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-{$i} days"));
        $revenueTrend['labels'][] = date('j M', strtotime($day));
        $revenueTrend['values'][] = round($revenueByDay[$day] ?? 0, 2);
    }
}

$pickupRows = dbFetchAll("SELECT DATE(pickup_at) day, COUNT(*) total FROM reservations WHERE agency_id=:agency AND status IN ('confirmed','deposit_paid','ready','active') AND pickup_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 13 DAY) GROUP BY DATE(pickup_at)", ['agency'=>$agencyId]);
$pickupsByDay = [];
foreach ($pickupRows as $row) $pickupsByDay[$row['day']] = (int) $row['total'];
$pickupTrend = ['labels'=>[], 'values'=>[]];
for ($i = 0; $i <= 13; $i++) {
    $day = date('Y-m-d', strtotime("+{$i} days"));
    $pickupTrend['labels'][] = date('D j', strtotime($day));
    $pickupTrend['values'][] = $pickupsByDay[$day] ?? 0;
}

$fleetChart = ['labels'=>[], 'values'=>[]];
foreach ($vehicleCounts as $row) {
    $fleetChart['labels'][] = translatedStatus($row['status']);
    $fleetChart['values'][] = (int) $row['total'];
}

backofficeHeader(t('page.dashboard.title'), 'index.php');
pageHeader('page.dashboard.title', 'page.dashboard.description', [
    'breadcrumbs'=>[['label'=>'nav.overview'],['label'=>'nav.dashboard']],
]); ?>
<form class="filters"><label><?=e(t('field.agency'))?><select name="agency_id" onchange="this.form.submit()"><?php foreach($agencies as $a):if(currentUserRole()!==ROLE_OWNER&&!in_array((int)$a['id'],$agencyIds,true))continue;?><option value="<?=e($a['id'])?>" <?=$agencyId===(int)$a['id']?'selected':''?>><?=e($a['name'])?></option><?php endforeach;?></select></label><noscript><button class="btn primary"><?=e(t('action.apply'))?></button></noscript></form>
<section class="stat-grid">
<?php
$statCurrency = strtoupper((string) appConfig('currency'));
$financeIconByIndex = ['finance', 'commercial', 'finance'];
$financeColorByIndex = ['', 'warning', 'info'];
$metricIndex = 0;
foreach ($metrics as $label=>$value):
?><article class="stat-card"><span class="stat-icon <?= e($financeColorByIndex[$metricIndex] ?? '') ?>"><?= navigationIcon($financeIconByIndex[$metricIndex] ?? 'finance') ?></span><div class="stat-body"><span><?= e($label) ?> (<?= e($statCurrency) ?>)</span><strong><?= statMoney($value) ?></strong></div></article>
<?php $metricIndex++; endforeach; ?>
<?php
$vehicleStatusColor = ['available'=>'success', 'reserved'=>'info', 'rented'=>'info', 'maintenance'=>'warning', 'damaged'=>'warning'];
foreach ($vehicleCounts as $row):
?><article class="stat-card"><span class="stat-icon <?= e($vehicleStatusColor[$row['status']] ?? 'neutral') ?>"><?= navigationIcon('fleet') ?></span><div class="stat-body"><span><?= e(translatedStatus($row['status']) . ' · ' . t('nav.vehicles')) ?></span><strong><?= e($row['total']) ?></strong></div></article>
<?php endforeach; ?>
</section>
<section class="chart-grid">
<?php if ($revenueTrend): ?><article class="card chart-card"><div class="section-card-header"><h2><?= navigationIcon('finance') ?><?=e(t('section.revenue_trend'))?></h2></div><div class="chart-wrap"><canvas id="chart-revenue" role="img" aria-label="<?=e(t('section.revenue_trend'))?>"></canvas></div></article><?php endif; ?>
<article class="card chart-card"><div class="section-card-header"><h2><?= navigationIcon('fleet') ?><?=e(t('section.fleet_status'))?></h2></div><?php if ($fleetChart['labels']): ?><div class="chart-wrap"><canvas id="chart-fleet" role="img" aria-label="<?=e(t('section.fleet_status'))?>"></canvas></div><?php else: ?><p class="empty"><?=e(t('empty.no_filtered_records'))?></p><?php endif; ?></article>
<article class="card chart-card"><div class="section-card-header"><h2><?= navigationIcon('rentals') ?><?=e(t('section.pickup_forecast'))?></h2></div><div class="chart-wrap"><canvas id="chart-pickups" role="img" aria-label="<?=e(t('section.pickup_forecast'))?>"></canvas></div></article>
</section>
<div class="grid"><section class="card"><div class="section-card-header"><h2><?= navigationIcon('rentals') ?><?=e(t('section.upcoming_operations'))?></h2></div><?php if (!$upcoming): ?><p class="empty"><?= e(t('empty.no_filtered_records')) ?></p><?php else: ?><div class="table-wrap"><table><tr><th><?=e(t('field.reference'))?></th><th><?=e(t('field.customer'))?></th><th><?=e(t('field.vehicle'))?></th><th><?=e(t('field.pickup'))?></th><th><?=e(t('field.return'))?></th><th><?=e(t('common.status'))?></th></tr><?php foreach($upcoming as $row): ?><tr><td><?= isolatedValue($row['reference'],'reference-value') ?></td><td><?= e($row['first_name'].' '.$row['last_name']) ?></td><td><?= isolatedValue($row['registration_number'],'registration-value') ?></td><td><?= formattedDateTime($row['pickup_at']) ?></td><td><?= formattedDateTime($row['return_at']) ?></td><td><?= statusBadge($row['status']) ?></td></tr><?php endforeach; ?></table></div><?php endif; ?></section>
<section class="card"><div class="section-card-header"><h2><?= navigationIcon('document') ?><?=e(t('section.document_alerts'))?></h2></div><?php if(!$alerts): ?><p class="empty"><?=e(t('empty.no_expirations'))?></p><?php else:?><div class="table-wrap"><table><tr><th><?=e(t('field.vehicle'))?></th><th><?=e(t('field.documents'))?></th><th><?=e(t('field.expires'))?></th></tr><?php foreach($alerts as $row): ?><tr><td><?=isolatedValue($row['registration_number'],'registration-value')?></td><td><?=e(translatedStatus($row['document_type']))?></td><td><?=formattedDate($row['expires_at'])?></td></tr><?php endforeach;?></table></div><?php endif;?></section></div>
<?php
$chartPayload = [
    'currency' => $statCurrency,
    'revenue' => $revenueTrend,
    'fleet' => $fleetChart['labels'] ? $fleetChart : null,
    'pickups' => $pickupTrend,
];
?>
<script src="assets/lib/chartjs/chart.umd.min.js"></script>
<script>
(function () {
    var data = <?= json_encode($chartPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var style = getComputedStyle(document.documentElement);
    var textMuted = style.getPropertyValue('--text-muted').trim() || '#657387';
    var border = style.getPropertyValue('--border').trim() || '#e1e6ee';
    var brand = style.getPropertyValue('--brand').trim() || '#011468';
    Chart.defaults.color = textMuted;
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;

    if (data.revenue) {
        var revenueCanvas = document.getElementById('chart-revenue');
        if (revenueCanvas) new Chart(revenueCanvas, {
            type: 'line',
            data: { labels: data.revenue.labels, datasets: [{ label: data.currency, data: data.revenue.values, borderColor: brand, backgroundColor: brand + '22', fill: true, tension: 0.35, pointRadius: 0, borderWidth: 2 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (ctx) { return ctx.parsed.y.toLocaleString() + ' ' + data.currency; } } } }, scales: { x: { grid: { display: false }, ticks: { maxTicksLimit: 8 } }, y: { beginAtZero: true, grid: { color: border } } } }
        });
    }
    if (data.fleet) {
        var fleetCanvas = document.getElementById('chart-fleet');
        var palette = ['#16a34a', '#2563eb', '#f59e0b', '#dc2626', '#7c3aed', '#6b7280'];
        if (fleetCanvas) new Chart(fleetCanvas, {
            type: 'doughnut',
            data: { labels: data.fleet.labels, datasets: [{ data: data.fleet.values, backgroundColor: palette, borderWidth: 0 }] },
            options: { responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12 } } } }
        });
    }
    if (data.pickups) {
        var pickupsCanvas = document.getElementById('chart-pickups');
        if (pickupsCanvas) new Chart(pickupsCanvas, {
            type: 'bar',
            data: { labels: data.pickups.labels, datasets: [{ data: data.pickups.values, backgroundColor: brand, borderRadius: 4, maxBarThickness: 28 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: border } } } }
        });
    }
})();
</script>
<?php backofficeFooter();
