<?php
require_once __DIR__ . '/_layout.php';
requirePermission('vehicles.view');

$vehicleId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
if (!$vehicleId) { http_response_code(404); exit(t('validation.vehicle_not_found')); }
$tab = vehicleDetailTab($_GET['tab'] ?? 'overview');
$redirectToDetail = function ($targetTab) use ($vehicleId) {
    safeRedirect('vehicle_detail.php?' . http_build_query(['id'=>$vehicleId,'tab'=>vehicleDetailTab($targetTab)]));
};

if (requestMethod() === 'POST') {
    requirePermission('vehicles.manage');
    requireCsrfPost();
    $action = (string) ($_POST['action'] ?? '');
    $targetTab = (string) ($_POST['tab'] ?? $tab);
    try {
        if ($action === 'update_profile') {
            $changes = updateVehicleProfile($vehicleId, $_POST);
            flash('success', $changes ? t('message.vehicle_profile_updated') : t('message.no_changes'));
            $targetTab = 'profile';
        } elseif ($action === 'upload_media') {
            uploadVehicleMedia($vehicleId, $_FILES['media'] ?? []);
            flash('success', t('message.vehicle_media_uploaded'));
            $targetTab = 'media';
        } elseif ($action === 'update_media') {
            updateVehicleMediaText($vehicleId, (int) ($_POST['media_id'] ?? 0), $_POST['caption'] ?? '', $_POST['alt_text'] ?? '');
            flash('success', t('message.vehicle_media_updated'));
            $targetTab = 'media';
        } elseif ($action === 'set_primary') {
            setPrimaryVehicleMedia($vehicleId, (int) ($_POST['media_id'] ?? 0));
            flash('success', t('message.vehicle_primary_updated'));
            $targetTab = 'media';
        } elseif ($action === 'reorder_media') {
            moveVehicleMedia($vehicleId, (int) ($_POST['media_id'] ?? 0), (string) ($_POST['direction'] ?? ''));
            flash('success', t('message.vehicle_media_reordered'));
            $targetTab = 'media';
        } elseif ($action === 'archive_media') {
            archiveVehicleMedia($vehicleId, (int) ($_POST['media_id'] ?? 0));
            flash('success', t('message.vehicle_media_archived'));
            $targetTab = 'media';
        } elseif ($action === 'restore_media') {
            restoreVehicleMedia($vehicleId, (int) ($_POST['media_id'] ?? 0));
            flash('success', t('message.vehicle_media_restored'));
            $targetTab = 'media';
        } else {
            throw new InvalidArgumentException(t('validation.invalid_action'));
        }
    } catch (InvalidArgumentException|DomainException $exception) {
        flash('danger', $exception->getMessage());
    } catch (Throwable $exception) {
        reportDatabaseError($exception, 'Vehicle detail operation failed');
        flash('danger', t('message.vehicle_failed'));
    }
    $redirectToDetail($targetTab);
}

try { $vehicle = vehicleRecord($vehicleId); }
catch (InvalidArgumentException $exception) { http_response_code(404); exit(e($exception->getMessage())); }
$media = vehicleMediaRows($vehicleId, true);
$activeMedia = array_values(array_filter($media, fn($item)=>$item['archived_at'] === null));
$archivedMedia = array_values(array_filter($media, fn($item)=>$item['archived_at'] !== null));
$primaryMedia = null;
foreach ($activeMedia as $item) if ((int) $item['is_primary'] === 1) { $primaryMedia=$item; break; }
$canManage = can('vehicles.manage');
$canReservations = can('reservations.manage');
$canMaintenance = can('maintenance.manage');
$canInspections = can('inspections.manage');
$canDocuments = can('maintenance.manage') || can('vehicle_documents.manage');
$canIncidents = can('vehicles.manage');
$canFinance = canViewFinancialDashboard();
$visibleTabs = ['overview','profile','media'];
if ($canReservations) $visibleTabs[]='reservations';
if ($canMaintenance) $visibleTabs[]='maintenance';
if ($canInspections) $visibleTabs[]='inspections';
if ($canDocuments) $visibleTabs[]='documents';
if ($canIncidents) $visibleTabs[]='incidents';
if ($canFinance) $visibleTabs[]='finance';
$visibleTabs[]='history';
if (!in_array($tab, $visibleTabs, true)) $tab='overview';
$_GET['tab'] = $tab;

$categories = $canManage ? dbFetchAll('SELECT id,name FROM vehicle_categories WHERE (agency_id=:agency OR agency_id IS NULL) AND archived_at IS NULL ORDER BY name', ['agency'=>$vehicle['agency_id']]) : [];
$reservations = $canReservations ? dbFetchAll('SELECT r.id,r.reference,r.status,r.pickup_at,r.return_at,r.total_amount,r.currency,c.first_name,c.last_name FROM reservations r JOIN customers c ON c.id=r.customer_id WHERE r.vehicle_id=:vehicle AND r.agency_id=:agency AND r.archived_at IS NULL ORDER BY r.pickup_at DESC LIMIT 10', ['vehicle'=>$vehicleId,'agency'=>$vehicle['agency_id']]) : [];
$reservationCount = $canReservations ? (int) dbFetchOne('SELECT COUNT(*) total FROM reservations WHERE vehicle_id=:vehicle AND agency_id=:agency AND archived_at IS NULL',['vehicle'=>$vehicleId,'agency'=>$vehicle['agency_id']])['total'] : 0;
$maintenance = $canMaintenance ? dbFetchAll('SELECT id,maintenance_type,status,scheduled_date,entry_at,estimated_exit_at,cost FROM maintenance_records WHERE vehicle_id=:vehicle AND archived_at IS NULL ORDER BY COALESCE(entry_at,CONCAT(scheduled_date,\' 00:00:00\')) DESC LIMIT 10',['vehicle'=>$vehicleId]) : [];
$maintenanceCount = $canMaintenance ? (int) dbFetchOne('SELECT COUNT(*) total FROM maintenance_records WHERE vehicle_id=:vehicle AND archived_at IS NULL',['vehicle'=>$vehicleId])['total'] : 0;
$inspections = $canInspections ? dbFetchAll('SELECT vi.id,vi.inspection_type,vi.inspected_at,vi.mileage,vi.fuel_level,vi.status,rc.contract_number FROM vehicle_inspections vi JOIN rental_contracts rc ON rc.id=vi.contract_id WHERE vi.vehicle_id=:vehicle ORDER BY vi.inspected_at DESC LIMIT 10',['vehicle'=>$vehicleId]) : [];
$inspectionCount = $canInspections ? (int) dbFetchOne('SELECT COUNT(*) total FROM vehicle_inspections WHERE vehicle_id=:vehicle',['vehicle'=>$vehicleId])['total'] : 0;
$documents = $canDocuments ? dbFetchAll('SELECT id,document_type,document_number,issued_at,expires_at,original_name FROM vehicle_documents WHERE vehicle_id=:vehicle AND archived_at IS NULL ORDER BY expires_at IS NULL,expires_at LIMIT 10',['vehicle'=>$vehicleId]) : [];
$documentCount = $canDocuments ? (int) dbFetchOne('SELECT COUNT(*) total FROM vehicle_documents WHERE vehicle_id=:vehicle AND archived_at IS NULL',['vehicle'=>$vehicleId])['total'] : 0;
$damages = $canIncidents ? dbFetchAll('SELECT id,inspection_id,zone,damage_type,description,severity,status,created_at,resolved_at FROM vehicle_damages WHERE vehicle_id=:vehicle ORDER BY created_at DESC LIMIT 10',['vehicle'=>$vehicleId]) : [];
$fines = $canIncidents ? dbFetchAll('SELECT id,fine_type,status,amount,occurred_at FROM fines WHERE vehicle_id=:vehicle ORDER BY occurred_at DESC LIMIT 10',['vehicle'=>$vehicleId]) : [];
$accidents = $canIncidents ? dbFetchAll('SELECT id,status,occurred_at,location,estimated_cost FROM accidents WHERE vehicle_id=:vehicle ORDER BY occurred_at DESC LIMIT 10',['vehicle'=>$vehicleId]) : [];
$incidentCount = $canIncidents ? (int) dbFetchOne('SELECT (SELECT COUNT(*) FROM vehicle_damages WHERE vehicle_id=:vehicle1)+(SELECT COUNT(*) FROM fines WHERE vehicle_id=:vehicle2)+(SELECT COUNT(*) FROM accidents WHERE vehicle_id=:vehicle3) total',['vehicle1'=>$vehicleId,'vehicle2'=>$vehicleId,'vehicle3'=>$vehicleId])['total'] : 0;
$finance = $canFinance ? dbFetchOne("SELECT COALESCE((SELECT SUM(p.amount) FROM payments p JOIN reservations r ON r.id=p.reservation_id WHERE r.vehicle_id=:vehicle1 AND r.agency_id=:agency1 AND p.status='paid'),0) revenue,COALESCE((SELECT SUM(e.amount) FROM expenses e WHERE e.vehicle_id=:vehicle2 AND e.agency_id=:agency2 AND e.status='approved' AND e.archived_at IS NULL),0) expenses",['vehicle1'=>$vehicleId,'agency1'=>$vehicle['agency_id'],'vehicle2'=>$vehicleId,'agency2'=>$vehicle['agency_id']]) : null;
$statusHistory = dbFetchAll('SELECT vsh.*,u.fullname AS full_name FROM vehicle_status_history vsh LEFT JOIN users u ON u.id=vsh.changed_by WHERE vsh.vehicle_id=:vehicle ORDER BY vsh.changed_at DESC LIMIT 20',['vehicle'=>$vehicleId]);
$auditHistory = dbFetchAll("SELECT al.action,al.entity_type,al.entity_id,al.created_at,u.fullname AS full_name FROM audit_logs al LEFT JOIN users u ON u.id=al.user_id WHERE al.agency_id=:agency AND ((al.entity_type='vehicle' AND CAST(al.entity_id AS UNSIGNED)=:vehicle) OR (al.entity_type='vehicle_media' AND CAST(al.entity_id AS UNSIGNED) IN (SELECT id FROM vehicle_media WHERE vehicle_id=:vehicle2 AND agency_id=:agency2)) OR (al.entity_type='vehicle_damage' AND CAST(al.entity_id AS UNSIGNED) IN (SELECT id FROM vehicle_damages WHERE vehicle_id=:vehicle3))) ORDER BY al.created_at DESC LIMIT 30",['agency'=>$vehicle['agency_id'],'vehicle'=>$vehicleId,'vehicle2'=>$vehicleId,'agency2'=>$vehicle['agency_id'],'vehicle3'=>$vehicleId]);
$nextReservation = $canReservations ? dbFetchOne("SELECT reference,status,pickup_at,return_at FROM reservations WHERE vehicle_id=:vehicle AND agency_id=:agency AND status IN ('pending','confirmed','deposit_paid','ready','active') AND return_at>=NOW() ORDER BY pickup_at LIMIT 1",['vehicle'=>$vehicleId,'agency'=>$vehicle['agency_id']]) : null;
$nextMaintenance = $canMaintenance ? dbFetchOne("SELECT maintenance_type,status,scheduled_date,next_maintenance_date FROM maintenance_records WHERE vehicle_id=:vehicle AND archived_at IS NULL AND status IN ('scheduled','in_progress') ORDER BY COALESCE(scheduled_date,next_maintenance_date) LIMIT 1",['vehicle'=>$vehicleId]) : null;
$openDamage = $canIncidents ? (int) dbFetchOne("SELECT COUNT(*) total FROM vehicle_damages WHERE vehicle_id=:vehicle AND status='open'",['vehicle'=>$vehicleId])['total'] : null;

function vehicleDetailLink($vehicleId, $tab) { return 'vehicle_detail.php?' . http_build_query(['id'=>$vehicleId,'tab'=>$tab]); }
function vehicleSummaryEmpty() { ?><p class="empty compact-empty"><?=e(t('empty.no_records'))?></p><?php }

backofficeHeader(t('page.vehicle_detail.title'),'vehicles.php');
pageHeader(t('page.vehicle_detail.title'), t('page.vehicle_detail.description'), ['breadcrumbs'=>[['label'=>'nav.fleet'],['label'=>'nav.vehicles','href'=>'vehicles.php'],['label'=>$vehicle['registration_number']]],'secondary'=>['label'=>'action.back_to_fleet','href'=>'vehicles.php']]);
?>
<section class="detail-header vehicle-hero">
  <div class="vehicle-hero-main">
    <?php if($primaryMedia):?><img class="vehicle-hero-image" src="vehicle_media.php?id=<?=e($primaryMedia['id'])?>" alt="<?=e($primaryMedia['alt_text'] ?: $vehicle['brand'].' '.$vehicle['model'])?>"><?php else:?><div class="vehicle-hero-placeholder" aria-hidden="true">🚗</div><?php endif;?>
    <div><div class="section-card-header"><h2><?=navigationIcon('fleet')?><?=e($vehicle['brand'].' '.$vehicle['model'])?></h2></div><p><?=isolatedValue($vehicle['registration_number'],'registration-value')?> · <?=e($vehicle['category_name'])?></p><div class="vehicle-hero-badges"><?=statusBadge($vehicle['status'])?><span><?=e(number_format((int)$vehicle['current_mileage']))?> <?=e(t('unit.km'))?></span><span><?=money($vehicle['base_daily_price'])?> / <?=e(t('unit.day'))?></span></div></div>
  </div>
  <div class="meta-block"><small><?=e(t('field.agency'))?></small><strong><?=e($vehicle['agency_name'])?></strong></div>
</section>
<nav class="tabs vehicle-tabs" aria-label="<?=e(t('section.vehicle_workspace'))?>"><?php foreach($visibleTabs as $item):?><a class="tab <?=$tab===$item?'active':''?>" <?=$tab===$item?'aria-current="page"':''?> href="<?=e(vehicleDetailLink($vehicleId,$item))?>"><?=e(t('vehicle_tab.'.$item))?></a><?php endforeach;?></nav>

<?php if($tab==='overview'):?>
<div class="cards vehicle-metrics"><article class="card metric"><span><?=e(t('field.mileage'))?></span><strong><?=e(number_format((int)$vehicle['current_mileage']))?> <?=e(t('unit.km'))?></strong></article><article class="card metric"><span><?=e(t('field.daily_price'))?></span><strong><?=money($vehicle['base_daily_price'])?></strong></article><article class="card metric"><span><?=e(t('field.gallery_images'))?></span><strong><?=e(count($activeMedia))?></strong></article><?php if($canReservations):?><article class="card metric"><span><?=e(t('field.rentals'))?></span><strong><?=e($reservationCount)?></strong></article><?php endif;?></div>
<div class="grid vehicle-overview-grid">
 <?php if($canReservations):?><section class="card"><div class="section-card-header"><h2><?=navigationIcon('rentals')?><?=e(t('section.next_reservation'))?></h2></div><?php if($nextReservation):?><p><strong><bdi><?=e($nextReservation['reference'])?></bdi></strong> <?=statusBadge($nextReservation['status'])?></p><p><?=formattedDateTime($nextReservation['pickup_at'])?> → <?=formattedDateTime($nextReservation['return_at'])?></p><a href="<?=e(vehicleDetailLink($vehicleId,'reservations'))?>"><?=e(t('action.view_details'))?></a><?php else:vehicleSummaryEmpty();endif;?></section><?php endif;?>
 <?php if($canMaintenance):?><section class="card"><div class="section-card-header"><h2><?=navigationIcon('fleet')?><?=e(t('section.next_maintenance'))?></h2></div><?php if($nextMaintenance):?><p><strong><?=e(t('option.'.$nextMaintenance['maintenance_type']))?></strong> <?=statusBadge($nextMaintenance['status'])?></p><p><?=formattedDate($nextMaintenance['scheduled_date'] ?: $nextMaintenance['next_maintenance_date'])?></p><a href="<?=e(vehicleDetailLink($vehicleId,'maintenance'))?>"><?=e(t('action.view_details'))?></a><?php else:vehicleSummaryEmpty();endif;?></section><?php endif;?>
 <?php if($canDocuments):?><section class="card"><div class="section-card-header"><h2><?=navigationIcon('fleet')?><?=e(t('section.vehicle_documents'))?></h2></div><p><?=e(t('message.record_count',['count'=>$documentCount]))?></p><a href="<?=e(vehicleDetailLink($vehicleId,'documents'))?>"><?=e(t('action.view_details'))?></a></section><?php endif;?>
 <?php if($canIncidents):?><section class="card"><div class="section-card-header"><h2><?=navigationIcon('fleet')?><?=e(t('section.open_damage'))?></h2></div><p><?=e(t('message.record_count',['count'=>$openDamage]))?></p><a href="<?=e(vehicleDetailLink($vehicleId,'incidents'))?>"><?=e(t('action.view_details'))?></a></section><?php endif;?>
</div>
<?php elseif($tab==='profile'):?>
<section class="card"><div class="section-card-header"><h2><?=navigationIcon('fleet')?><?=e(t('section.vehicle_profile'))?></h2></div><?php if(!$canManage):?><p class="alert alert-info"><?=e(t('message.vehicle_read_only'))?></p><?php endif;?><form <?=$canManage?'method="post"':''?>><?php if($canManage):?><?=csrfField()?><input type="hidden" name="action" value="update_profile"><input type="hidden" name="tab" value="profile"><input type="hidden" name="updated_at" value="<?=e($vehicle['updated_at'])?>"><?php endif;?><fieldset <?=$canManage?'':'disabled'?>><div class="grid">
<label><?=e(t('field.category'))?><select name="category_id" required><?php foreach($categories ?: [['id'=>$vehicle['category_id'],'name'=>$vehicle['category_name']]] as $category):?><option value="<?=e($category['id'])?>" <?=(int)$category['id']===(int)$vehicle['category_id']?'selected':''?>><?=e($category['name'])?></option><?php endforeach;?></select></label>
<?php $fields=['registration_number'=>'registration','vin'=>'vin','brand'=>'brand','model'=>'model','version'=>'version','model_year'=>'year','colour'=>'colour','seats'=>'seats','doors'=>'doors','luggage_capacity'=>'luggage','current_mileage'=>'mileage','mileage_allowance'=>'mileage_allowance','purchase_date'=>'purchase_date','purchase_price'=>'purchase_price','monthly_finance_amount'=>'monthly_finance','base_daily_price'=>'daily_price','recommended_deposit'=>'deposit']; foreach($fields as $name=>$key):$type=in_array($name,['model_year','seats','doors','luggage_capacity','current_mileage','mileage_allowance'],true)?'number':($name==='purchase_date'?'date':'text');?><label><?=e(t('field.'.$key))?><input type="<?=$type?>" name="<?=e($name)?>" value="<?=e($vehicle[$name])?>" <?=$name==='registration_number'||$name==='brand'||$name==='model'||$name==='current_mileage'||$name==='base_daily_price'?'required':''?> <?=$name==='purchase_price'||$name==='monthly_finance_amount'||$name==='base_daily_price'||$name==='recommended_deposit'?'inputmode="decimal"':''?>></label><?php endforeach;?>
<label><?=e(t('field.fuel'))?><select name="fuel"><option value=""></option><?php foreach(['petrol','diesel','hybrid','electric','other'] as $item):?><option value="<?=$item?>" <?=$vehicle['fuel']===$item?'selected':''?>><?=e(t('option.'.$item))?></option><?php endforeach;?></select></label><label><?=e(t('field.transmission'))?><select name="transmission"><?php foreach(['manual','automatic'] as $item):?><option value="<?=$item?>" <?=$vehicle['transmission']===$item?'selected':''?>><?=e(t('option.'.$item))?></option><?php endforeach;?></select></label><?php $displayFinancing=vehicleFinancingTypeForDisplay($vehicle['financing_type']);?><label><?=e(t('field.financing_type'))?><select name="financing_type" required><?php foreach(vehicleFinancingTypes() as $item):?><option value="<?=$item?>" <?=$displayFinancing===$item?'selected':''?>><?=e(t('option.'.$item))?></option><?php endforeach;?></select></label><label class="full"><?=e(t('field.mileage_correction_reason'))?><textarea name="mileage_correction_reason" aria-describedby="mileage-help"></textarea><small id="mileage-help"><?=e(t('message.mileage_correction_help'))?></small></label>
</div></fieldset><?php if($canManage):?><button class="btn primary" type="submit"><?=e(t('action.save_profile'))?></button><?php endif;?></form></section>
<?php elseif($tab==='media'):?>
<?php if($canManage):?><section class="card"><div class="section-card-header"><h2><?=navigationIcon('fleet')?><?=e(t('section.upload_gallery'))?></h2></div><form method="post" enctype="multipart/form-data"><?=csrfField()?><input type="hidden" name="action" value="upload_media"><input type="hidden" name="tab" value="media"><label><?=e(t('field.gallery_files'))?><input type="file" name="media[]" accept="image/jpeg,image/png,image/webp" multiple required><small><?=e(t('message.gallery_limits',['files'=>10,'total'=>50]))?></small></label><button class="btn primary"><?=e(t('action.upload_images'))?></button></form></section><?php endif;?>
<section class="card"><div class="section-card-header"><h2><?=navigationIcon('fleet')?><?=e(t('section.active_gallery'))?></h2></div><?php if(!$activeMedia):vehicleSummaryEmpty();else:?><div class="media-gallery"><?php foreach($activeMedia as $index=>$item):?><article class="media-card"><img src="vehicle_media.php?id=<?=e($item['id'])?>" alt="<?=e($item['alt_text'] ?: $vehicle['brand'].' '.$vehicle['model'])?>"><div class="media-card-body"><?php if((int)$item['is_primary']===1):?><?=statusBadge('primary')?><?php endif;?><p><?=e($item['caption'] ?: t('message.no_caption'))?></p><small><?=e($item['width'] ?: '?')?>×<?=e($item['height'] ?: '?')?> · <?=e(number_format((int)$item['file_size']/1024,1))?> KB</small><?php if($canManage):?><form method="post"><?=csrfField()?><input type="hidden" name="action" value="update_media"><input type="hidden" name="tab" value="media"><input type="hidden" name="media_id" value="<?=e($item['id'])?>"><label><?=e(t('field.caption'))?><input name="caption" maxlength="255" value="<?=e($item['caption'])?>"></label><label><?=e(t('field.alt_text'))?><input name="alt_text" maxlength="255" value="<?=e($item['alt_text'])?>"></label><button class="btn secondary compact"><?=e(t('common.save'))?></button></form><div class="media-actions"><form method="post"><?=csrfField()?><input type="hidden" name="action" value="reorder_media"><input type="hidden" name="tab" value="media"><input type="hidden" name="media_id" value="<?=e($item['id'])?>"><button class="btn quiet compact" name="direction" value="up" <?=$index===0?'disabled':''?>><?=e(t('action.move_up'))?></button><button class="btn quiet compact" name="direction" value="down" <?=$index===count($activeMedia)-1?'disabled':''?>><?=e(t('action.move_down'))?></button></form><?php if(!(int)$item['is_primary']):?><form method="post"><?=csrfField()?><input type="hidden" name="action" value="set_primary"><input type="hidden" name="tab" value="media"><input type="hidden" name="media_id" value="<?=e($item['id'])?>"><button class="btn secondary compact"><?=e(t('action.make_primary'))?></button></form><?php endif;?><form method="post" data-confirm="<?=e(t('confirm.archive_media'))?>"><?=csrfField()?><input type="hidden" name="action" value="archive_media"><input type="hidden" name="tab" value="media"><input type="hidden" name="media_id" value="<?=e($item['id'])?>"><button class="btn danger compact"><?=e(t('common.archive'))?></button></form></div><?php endif;?></div></article><?php endforeach;?></div><?php endif;?></section>
<?php if($archivedMedia):?><section class="card"><div class="section-card-header"><h2><?=navigationIcon('fleet')?><?=e(t('section.archived_gallery'))?></h2></div><div class="media-gallery archived"><?php foreach($archivedMedia as $item):?><article class="media-card"><div class="archived-image"><span><?=e(t('status.archived'))?></span></div><div class="media-card-body"><p><?=e($item['caption'] ?: $item['original_name'])?></p><?php if($canManage):?><form method="post"><?=csrfField()?><input type="hidden" name="action" value="restore_media"><input type="hidden" name="tab" value="media"><input type="hidden" name="media_id" value="<?=e($item['id'])?>"><button class="btn secondary compact"><?=e(t('common.restore'))?></button></form><?php endif;?></div></article><?php endforeach;?></div></section><?php endif;?>
<?php elseif($tab==='reservations'&&$canReservations):?>
<section class="card"><div class="section-heading"><div class="section-card-header"><h2><?=navigationIcon('rentals')?><?=e(t('section.reservation_summary'))?> (<?=e($reservationCount)?>)</h2></div><a class="btn secondary" href="reservations.php?agency_id=<?=e($vehicle['agency_id'])?>&vehicle_id=<?=e($vehicleId)?>"><?=e(t('action.open_module'))?></a></div><div class="table-wrap"><table><tr><th><?=e(t('field.reference'))?></th><th><?=e(t('field.customer'))?></th><th><?=e(t('field.period'))?></th><th><?=e(t('field.total'))?></th><th><?=e(t('common.status'))?></th></tr><?php foreach($reservations as $r):?><tr><td><bdi><?=e($r['reference'])?></bdi></td><td><?=e($r['first_name'].' '.$r['last_name'])?></td><td><?=formattedDateTime($r['pickup_at'])?> → <?=formattedDateTime($r['return_at'])?></td><td><?=money($r['total_amount'],$r['currency'])?></td><td><?=statusBadge($r['status'])?></td></tr><?php endforeach;?></table><?php if(!$reservations)vehicleSummaryEmpty();?></div></section>
<?php elseif($tab==='maintenance'&&$canMaintenance):?>
<section class="card"><div class="section-heading"><div class="section-card-header"><h2><?=navigationIcon('fleet')?><?=e(t('section.maintenance_summary'))?> (<?=e($maintenanceCount)?>)</h2></div><a class="btn secondary" href="maintenance.php?vehicle_id=<?=e($vehicleId)?>"><?=e(t('action.open_module'))?></a></div><div class="table-wrap"><table><tr><th><?=e(t('field.type'))?></th><th><?=e(t('field.schedule'))?></th><th><?=e(t('field.cost'))?></th><th><?=e(t('common.status'))?></th></tr><?php foreach($maintenance as $r):?><tr><td><?=e(t('option.'.$r['maintenance_type']))?></td><td><?=formattedDate($r['scheduled_date'])?> <?=formattedDateTime($r['entry_at'])?></td><td><?=money($r['cost'])?></td><td><?=statusBadge($r['status'])?></td></tr><?php endforeach;?></table><?php if(!$maintenance)vehicleSummaryEmpty();?></div></section>
<?php elseif($tab==='inspections'&&$canInspections):?>
<section class="card"><div class="section-heading"><div class="section-card-header"><h2><?=navigationIcon('fleet')?><?=e(t('section.inspection_summary'))?> (<?=e($inspectionCount)?>)</h2></div><a class="btn secondary" href="inspections.php?vehicle_id=<?=e($vehicleId)?>"><?=e(t('action.open_module'))?></a></div><div class="table-wrap"><table><tr><th><?=e(t('field.contract'))?></th><th><?=e(t('field.type'))?></th><th><?=e(t('field.date_time'))?></th><th><?=e(t('field.mileage'))?></th><th><?=e(t('common.status'))?></th></tr><?php foreach($inspections as $r):?><tr><td><bdi><?=e($r['contract_number'])?></bdi></td><td><?=e(t('option.'.$r['inspection_type']))?></td><td><?=formattedDateTime($r['inspected_at'])?></td><td><?=e($r['mileage'])?></td><td><?=statusBadge($r['status'])?></td></tr><?php endforeach;?></table><?php if(!$inspections)vehicleSummaryEmpty();?></div></section>
<?php elseif($tab==='documents'&&$canDocuments):?>
<section class="card"><div class="section-heading"><div class="section-card-header"><h2><?=navigationIcon('document')?><?=e(t('section.document_summary'))?> (<?=e($documentCount)?>)</h2></div><a class="btn secondary" href="maintenance.php?vehicle_id=<?=e($vehicleId)?>#vehicle-documents"><?=e(t('action.open_module'))?></a></div><div class="table-wrap"><table><tr><th><?=e(t('field.document_type'))?></th><th><?=e(t('field.number'))?></th><th><?=e(t('field.issue_date'))?></th><th><?=e(t('field.expiry_date'))?></th></tr><?php foreach($documents as $r):?><tr><td><?=e(t('option.'.$r['document_type']))?></td><td><bdi><?=e($r['document_number'])?></bdi></td><td><?=formattedDate($r['issued_at'])?></td><td><?=formattedDate($r['expires_at'])?></td></tr><?php endforeach;?></table><?php if(!$documents)vehicleSummaryEmpty();?></div></section>
<?php elseif($tab==='incidents'&&$canIncidents):?>
<section class="card"><div class="section-heading"><div class="section-card-header"><h2><?=navigationIcon('fleet')?><?=e(t('section.incident_summary'))?> (<?=e($incidentCount)?>)</h2></div><a class="btn secondary" href="incidents.php?vehicle_id=<?=e($vehicleId)?>"><?=e(t('action.open_module'))?></a></div><h3><?=e(t('section.damages'))?></h3><div class="table-wrap"><table><tr><th><?=e(t('field.damage_source'))?></th><th><?=e(t('field.zone'))?></th><th><?=e(t('field.type'))?></th><th><?=e(t('field.description'))?></th><th><?=e(t('field.severity'))?></th><th><?=e(t('field.date_time'))?></th><th><?=e(t('common.status'))?></th></tr><?php foreach($damages as $r):?><tr><td><?=isolatedValue('#'.$r['inspection_id'],'reference-value')?></td><td><?=e($r['zone'])?></td><td><?=e($r['damage_type'])?></td><td><?=e($r['description'])?></td><td><?=e(t('option.'.$r['severity']))?></td><td><?=formattedDateTime($r['created_at'])?></td><td><?=statusBadge($r['status'])?></td></tr><?php endforeach;?></table></div><h3><?=e(t('section.fines'))?></h3><div class="table-wrap"><table><tr><th><?=e(t('field.type'))?></th><th><?=e(t('field.date_time'))?></th><th><?=e(t('field.amount'))?></th><th><?=e(t('common.status'))?></th></tr><?php foreach($fines as $r):?><tr><td><?=e($r['fine_type'])?></td><td><?=formattedDateTime($r['occurred_at'])?></td><td><?=money($r['amount'])?></td><td><?=statusBadge($r['status'])?></td></tr><?php endforeach;?></table></div><h3><?=e(t('section.accidents_claims'))?></h3><div class="table-wrap"><table><tr><th><?=e(t('field.date_time'))?></th><th><?=e(t('field.location'))?></th><th><?=e(t('field.estimated_cost'))?></th><th><?=e(t('common.status'))?></th></tr><?php foreach($accidents as $r):?><tr><td><?=formattedDateTime($r['occurred_at'])?></td><td><?=e($r['location'])?></td><td><?=money($r['estimated_cost'])?></td><td><?=statusBadge($r['status'])?></td></tr><?php endforeach;?></table></div></section>
<?php elseif($tab==='finance'&&$canFinance):$profit=moneyToCents($finance['revenue'])-moneyToCents($finance['expenses']);?>
<section class="card"><div class="section-heading"><div class="section-card-header"><h2><?=navigationIcon('fleet')?><?=e(t('section.estimated_profitability'))?></h2></div><div class="page-actions"><a class="btn secondary" href="finance.php?vehicle_id=<?=e($vehicleId)?>"><?=e(t('nav.payments'))?></a><a class="btn secondary" href="expenses.php?agency_id=<?=e($vehicle['agency_id'])?>&vehicle_id=<?=e($vehicleId)?>"><?=e(t('expenses'))?></a></div></div><div class="cards"><article class="card metric"><span><?=e(t('field.revenue'))?></span><strong><?=money($finance['revenue'])?></strong></article><article class="card metric"><span><?=e(t('field.expenses'))?></span><strong><?=money($finance['expenses'])?></strong></article><article class="card metric"><span><?=e(t('field.estimated_profit'))?></span><strong><?=money(centsToMoney($profit))?></strong></article></div><p><?=e(t('message.profitability_note'))?></p></section>
<?php elseif($tab==='history'):?>
<div class="grid"><section class="card"><div class="section-card-header"><h2><?=navigationIcon('fleet')?><?=e(t('section.status_history'))?></h2></div><div class="table-wrap"><table><tr><th><?=e(t('field.date_time'))?></th><th><?=e(t('field.workflow'))?></th><th><?=e(t('field.reason'))?></th><th><?=e(t('field.user'))?></th></tr><?php foreach($statusHistory as $r):?><tr><td><?=formattedDateTime($r['changed_at'])?></td><td><?=e($r['from_status']?translatedStatus($r['from_status']).' → ':'')?><?=e(translatedStatus($r['to_status']))?></td><td><?=e($r['reason'])?></td><td><?=e($r['full_name'])?></td></tr><?php endforeach;?></table><?php if(!$statusHistory)vehicleSummaryEmpty();?></div></section><section class="card"><div class="section-card-header"><h2><?=navigationIcon('fleet')?><?=e(t('section.audit_history'))?></h2></div><div class="table-wrap"><table><tr><th><?=e(t('field.date_time'))?></th><th><?=e(t('field.action'))?></th><th><?=e(t('field.user'))?></th></tr><?php foreach($auditHistory as $r):?><tr><td><?=formattedDateTime($r['created_at'])?></td><td><?=e(t('audit.'.$r['action']))?></td><td><?=e($r['full_name'])?></td></tr><?php endforeach;?></table><?php if(!$auditHistory)vehicleSummaryEmpty();?></div></section></div>
<?php endif;?>
<?php backofficeFooter();
