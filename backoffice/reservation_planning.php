<?php
require_once __DIR__ . '/_layout.php';
requirePermission('reservations.manage');

$agencyIds = currentAgencyIds();
if (!$agencyIds) $agencyIds = [0];
$agencyId = (int) ($_GET['agency_id'] ?? $agencyIds[0]);
requireAgencyAccess($agencyId);

$view = validateChoice($_GET['view'] ?? 'week', ['day','week'], 'week');
$today = new DateTimeImmutable('today');
$date = validDateValue($_GET['date'] ?? $today->format('Y-m-d')) ?: $today;
if ($date < $today->modify('-2 years') || $date > $today->modify('+2 years')) $date = $today;
$from = ($view === 'week' ? $date->modify('monday this week') : $date)->setTime(0, 0);
$to = $from->modify($view === 'week' ? '+7 days' : '+1 day');
$vehicleId = max(0, (int) ($_GET['vehicle_id'] ?? 0));
$categoryId = max(0, (int) ($_GET['category_id'] ?? 0));

$data = reservationPlanningData($agencyId, $from, $to, ['vehicle_id'=>$vehicleId,'category_id'=>$categoryId]);
$agencies = dbFetchAll("SELECT id,name FROM agencies WHERE archived_at IS NULL AND status='active' ORDER BY name");
$categories = dbFetchAll('SELECT id,name FROM vehicle_categories WHERE (agency_id IS NULL OR agency_id=:agency) AND archived_at IS NULL ORDER BY name', ['agency'=>$agencyId]);
$vehicles = dbFetchAll('SELECT id,registration_number,brand,model FROM vehicles WHERE agency_id=:agency AND archived_at IS NULL ORDER BY registration_number', ['agency'=>$agencyId]);

$byVehicle = [];
foreach ($data['reservations'] as $item) $byVehicle[$item['vehicle_id']]['reservations'][] = $item;
foreach ($data['maintenance'] as $item) $byVehicle[$item['vehicle_id']]['maintenance'][] = $item;
$span = max(1, $to->getTimestamp() - $from->getTimestamp());
$position = static function ($startsAt, $endsAt) use ($from, $to, $span) {
    $start = max($from->getTimestamp(), strtotime($startsAt));
    $end = min($to->getTimestamp(), strtotime($endsAt));
    $left = max(0, min(100, (($start - $from->getTimestamp()) / $span) * 100));
    return [$left, max(1, min(100 - $left, (($end - $start) / $span) * 100))];
};
$step = $view === 'week' ? '7 days' : '1 day';
$previous = $from->modify('-' . $step)->format('Y-m-d');
$next = $from->modify('+' . $step)->format('Y-m-d');

backofficeHeader(t('page.reservation_planning.title'), 'reservation_planning.php');
pageHeader('page.reservation_planning.title', 'page.reservation_planning.description', [
    'breadcrumbs'=>[['label'=>'nav.rentals'],['label'=>'nav.planning']],
    'primary'=>['label'=>'action.create_reservation','href'=>'reservation_form.php?agency_id='.$agencyId],
    'secondary'=>['label'=>'nav.reservations','href'=>'reservations.php?agency_id='.$agencyId],
]);
?>
<form class="filters" method="get">
    <label><?=e(t('field.agency'))?><select name="agency_id"><?php foreach($agencies as $agency):if(currentUserRole()!==ROLE_OWNER&&!in_array((int)$agency['id'],$agencyIds,true))continue;?><option value="<?=e($agency['id'])?>" <?=$agencyId===(int)$agency['id']?'selected':''?>><?=e($agency['name'])?></option><?php endforeach;?></select></label>
    <label><?=e(t('field.view'))?><select name="view"><option value="day" <?=$view==='day'?'selected':''?>><?=e(t('option.day'))?></option><option value="week" <?=$view==='week'?'selected':''?>><?=e(t('option.week'))?></option></select></label>
    <label><?=e(t('field.date'))?><input type="date" name="date" value="<?=e($date->format('Y-m-d'))?>"></label>
    <label><?=e(t('field.category'))?><select name="category_id"><option value=""><?=e(t('common.all'))?></option><?php foreach($categories as $category):?><option value="<?=e($category['id'])?>" <?=$categoryId===(int)$category['id']?'selected':''?>><?=e($category['name'])?></option><?php endforeach;?></select></label>
    <label><?=e(t('field.vehicle'))?><select name="vehicle_id"><option value=""><?=e(t('common.all'))?></option><?php foreach($vehicles as $vehicle):?><option value="<?=e($vehicle['id'])?>" <?=$vehicleId===(int)$vehicle['id']?'selected':''?>><?=e($vehicle['registration_number'].' — '.$vehicle['brand'].' '.$vehicle['model'])?></option><?php endforeach;?></select></label>
    <button class="btn secondary"><?=e(t('common.filter'))?></button>
</form>
<div class="planning-nav">
    <a class="btn secondary" href="?agency_id=<?=e($agencyId)?>&view=<?=$view?>&date=<?=$previous?>&category_id=<?=e($categoryId)?>&vehicle_id=<?=e($vehicleId)?>"><?=e(t('common.previous'))?></a>
    <a class="btn ghost" href="?agency_id=<?=e($agencyId)?>&view=<?=$view?>&date=<?=$today->format('Y-m-d')?>"><?=e(t('action.today'))?></a>
    <strong><?=formattedDate($from->format('Y-m-d'))?> — <?=formattedDate($to->modify('-1 second')->format('Y-m-d'))?></strong>
    <a class="btn secondary" href="?agency_id=<?=e($agencyId)?>&view=<?=$view?>&date=<?=$next?>&category_id=<?=e($categoryId)?>&vehicle_id=<?=e($vehicleId)?>"><?=e(t('common.next'))?></a>
</div>
<section class="card planning-board" data-planning-board>
    <div class="planning-header"><span><?=e(t('field.vehicle'))?></span><span><?=e(t('section.timeline'))?></span></div>
    <?php foreach($data['vehicles'] as $vehicle):
        $reservations = $byVehicle[$vehicle['id']]['reservations'] ?? [];
        $maintenance = $byVehicle[$vehicle['id']]['maintenance'] ?? [];
        $unavailable = in_array($vehicle['status'], ['cleaning','maintenance','damaged','blocked'], true);
        $mobileEvents = [];
        foreach ($reservations as $item) $mobileEvents[] = ['at'=>$item['pickup_at'],'type'=>'reservation','item'=>$item];
        foreach ($maintenance as $item) $mobileEvents[] = ['at'=>$item['starts_at'],'type'=>'maintenance','item'=>$item];
        usort($mobileEvents, static fn($a,$b)=>strcmp($a['at'],$b['at']));
    ?>
    <div class="planning-row">
        <div class="planning-vehicle"><strong><?=isolatedValue($vehicle['registration_number'],'registration-value')?></strong><small><?=e($vehicle['brand'].' '.$vehicle['model'].' · '.$vehicle['category_name'])?></small></div>
        <div class="planning-track" role="list" aria-label="<?=e($vehicle['registration_number'])?>">
            <?php if($unavailable):?><span role="listitem" class="planning-block unavailable-block" style="inset-inline-start:0;inline-size:100%" aria-label="<?=e(translatedStatus($vehicle['status']))?>"><?=e(translatedStatus($vehicle['status']))?></span><?php endif;?>
            <?php foreach($reservations as $item):[$left,$width]=$position($item['pickup_at'],$item['return_at']);?><a role="listitem" class="planning-block reservation-block" style="inset-inline-start:<?=$left?>%;inline-size:<?=$width?>%" href="reservation_detail.php?id=<?=e($item['id'])?>" aria-label="<?=e($item['reference'].' — '.$item['first_name'].' '.$item['last_name'].' — '.$item['pickup_at'].' — '.$item['return_at'].' — '.translatedStatus($item['status']))?>"><bdi><?=e($item['reference'])?></bdi><small><?=e($item['first_name'].' '.$item['last_name'])?></small></a><?php endforeach;?>
            <?php foreach($maintenance as $item):[$left,$width]=$position($item['starts_at'],$item['ends_at']);?><span role="listitem" class="planning-block maintenance-block" style="inset-inline-start:<?=$left?>%;inline-size:<?=$width?>%" aria-label="<?=e(t('nav.maintenance').' — '.$item['maintenance_type'].' — '.$item['starts_at'].' — '.$item['ends_at'])?>"><?=e(t('nav.maintenance'))?></span><?php endforeach;?>
        </div>
        <div class="planning-mobile-list">
            <?php if($unavailable):?><span><?=e(translatedStatus($vehicle['status']))?></span><?php endif;?>
            <?php foreach($mobileEvents as $event):$item=$event['item'];if($event['type']==='reservation'):?><a href="reservation_detail.php?id=<?=e($item['id'])?>"><bdi><?=e($item['reference'])?></bdi> · <?=formattedDateTime($item['pickup_at'])?> → <?=formattedDateTime($item['return_at'])?></a><?php else:?><span><?=e(t('nav.maintenance').' · '.$item['maintenance_type'])?> · <?=formattedDateTime($item['starts_at'])?> → <?=formattedDateTime($item['ends_at'])?></span><?php endif;endforeach;?>
            <?php if(!$unavailable&&!$mobileEvents):?><span><?=e(t('common.available'))?></span><?php endif;?>
        </div>
    </div>
    <?php endforeach;?>
    <?php if(!$data['vehicles']) echo emptyState('empty.no_filtered_records','empty.adjust_filters');?>
</section>
<?php backofficeFooter();
