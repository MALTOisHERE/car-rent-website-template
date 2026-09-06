<?php
require_once __DIR__ . '/_layout.php';
requirePermission('vehicles.view');
$agencyIds = currentAgencyIds();

if (requestMethod() === 'POST') {
    requirePermission('vehicles.manage');
    requireCsrfPost();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'status') {
            changeVehicleStatus((int)($_POST['id']??0),$_POST['status']??'',$_POST['reason']??'');
            flash('success',t('message.vehicle_status_updated'));
        } elseif ($action === 'archive') {
            withTransaction(function () {
                $vehicle=vehicleRecord((int)($_POST['id']??0),true);
                dbExecute("UPDATE vehicles SET archived_at=NOW(),status='retired',updated_by=:user,updated_at=NOW(6) WHERE id=:id",['user'=>currentUserId(),'id'=>$vehicle['id']]);
                if($vehicle['status']!=='retired') dbExecute("INSERT INTO vehicle_status_history(vehicle_id,from_status,to_status,reason,changed_by) VALUES(:id,:old,'retired',:reason,:user)",['id'=>$vehicle['id'],'old'=>$vehicle['status'],'reason'=>t('message.vehicle_archived'),'user'=>currentUserId()]);
                auditLog('vehicle.archived','vehicle',$vehicle['id'],['status'=>$vehicle['status']],['status'=>'retired'],$vehicle['agency_id']);
            });
            flash('success',t('message.vehicle_archived'));
        }
    } catch(InvalidArgumentException|DomainException $exception) {
        flash('danger',$exception->getMessage());
    } catch(Throwable $exception) {
        reportDatabaseError($exception,'Vehicle operation failed'); flash('danger',t('message.vehicle_failed'));
    }
    safeRedirect('vehicles.php');
}

[$page,$size,$offset]=paginationParameters();
$agencyId=(int)($_GET['agency_id']??($agencyIds[0]??0)); requireAgencyAccess($agencyId);
$status=trim((string)($_GET['status']??''));
$where='v.agency_id=:agency AND v.archived_at IS NULL'; $params=['agency'=>$agencyId];
if(in_array($status,vehicleStatuses(),true)){$where.=' AND v.status=:status';$params['status']=$status;}
$vehicles=dbFetchAll("SELECT v.*,c.name category_name,(SELECT vm.id FROM vehicle_media vm WHERE vm.vehicle_id=v.id AND vm.agency_id=v.agency_id AND vm.archived_at IS NULL AND vm.is_primary=1 LIMIT 1) primary_media_id FROM vehicles v JOIN vehicle_categories c ON c.id=v.category_id WHERE $where ORDER BY v.registration_number LIMIT $size OFFSET $offset",$params);
$agencies=dbFetchAll('SELECT id,name FROM agencies WHERE archived_at IS NULL ORDER BY name');

backofficeHeader(t('page.vehicles.title'),'vehicles.php');
pageHeader('page.vehicles.title', 'page.vehicles.description', ['breadcrumbs'=>[['label'=>'nav.fleet'],['label'=>'nav.vehicles']],'primary'=>can('vehicles.manage')?['label'=>'action.add_vehicle','href'=>'vehicle_form.php']:null]);
?>
<form class="filters"><label><?=e(t('field.agency'))?><select name="agency_id"><?php foreach($agencies as $agency):if(currentUserRole()!==ROLE_OWNER&&!in_array((int)$agency['id'],$agencyIds,true))continue;?><option value="<?=e($agency['id'])?>" <?=$agencyId==(int)$agency['id']?'selected':''?>><?=e($agency['name'])?></option><?php endforeach;?></select></label><label><?=e(t('common.status'))?><select name="status"><option value=""><?=e(t('common.all'))?></option><?php foreach(vehicleStatuses() as $item):?><option value="<?=e($item)?>" <?=$status===$item?'selected':''?>><?=e(translatedStatus($item))?></option><?php endforeach;?></select></label><button class="btn secondary"><?=e(t('common.filter'))?></button></form>
<div class="grid"><section class="card full"><div class="section-card-header"><h2><?=navigationIcon('fleet')?><?=e(t('section.fleet'))?></h2></div><div class="table-wrap"><table><tr><th><?=e(t('field.vehicle'))?></th><th><?=e(t('field.registration'))?></th><th><?=e(t('field.category'))?></th><th><?=e(t('field.price_per_day'))?></th><th><?=e(t('common.status'))?></th><th><?=e(t('common.actions'))?></th></tr><?php foreach($vehicles as $vehicle):?><tr><td><?php if($vehicle['primary_media_id']):?><img class="media-thumbnail" src="vehicle_media.php?id=<?=e($vehicle['primary_media_id'])?>" alt=""><?php endif;?> <?=e($vehicle['brand'].' '.$vehicle['model'])?></td><td><?=isolatedValue($vehicle['registration_number'],'registration-value')?></td><td><?=e($vehicle['category_name'])?></td><td><?=money($vehicle['base_daily_price'])?></td><td><?=statusBadge($vehicle['status'])?></td><td><?=actionMenu([
    ['label'=>'action.view_details','href'=>'vehicle_detail.php?id='.$vehicle['id']],
    ['label'=>'action.change_status','drawer'=>'#vehicle-status-'.$vehicle['id'],'drawer_title'=>'action.change_status','permission'=>'vehicles.manage'],
    ['label'=>'common.archive','form'=>['action'=>'archive','id'=>$vehicle['id']],'confirm'=>'confirm.archive_vehicle','danger'=>true,'permission'=>'vehicles.manage'],
])?><?php if(can('vehicles.manage')):?><template id="vehicle-status-<?=e($vehicle['id'])?>"><form method="post"><?=csrfField()?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?=e($vehicle['id'])?>"><label><?=e(t('common.status'))?><select name="status" required><?php foreach(vehicleStatuses() as $item):?><option value="<?=e($item)?>" <?=$item===$vehicle['status']?'selected':''?>><?=e(translatedStatus($item))?></option><?php endforeach;?></select></label><button class="btn primary"><?=e(t('common.save'))?></button></form></template><?php endif;?></td></tr><?php endforeach;?></table><?php if(!$vehicles):?><p class="empty"><?=e(t('empty.no_filtered_records'))?></p><?php endif;?></div></section></div>
<?php backofficeFooter();
