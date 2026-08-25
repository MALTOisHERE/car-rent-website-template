<?php
require_once __DIR__ . '/_layout.php';
requirePermission('vehicles.view');
$agencyIds = currentAgencyIds();

if (requestMethod() === 'POST') {
    requirePermission('vehicles.manage');
    requireCsrfPost();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'create') {
            $agencyId=(int)($_POST['agency_id']??0); requireAgencyAccess($agencyId);
            $categoryId=(int)($_POST['category_id']??0);
            $category=dbFetchOne('SELECT id FROM vehicle_categories WHERE id=:id AND (agency_id=:agency OR agency_id IS NULL) AND archived_at IS NULL',['id'=>$categoryId,'agency'=>$agencyId]);
            if(!$category) throw new InvalidArgumentException(t('validation.valid_vehicle_category'));
            $registration=strtoupper(trim((string)($_POST['registration_number']??'')));
            $brand=trim((string)($_POST['brand']??'')); $model=trim((string)($_POST['model']??''));
            $price=positiveMoney($_POST['base_daily_price']??'');
            if($registration===''||$brand===''||$model===''||$price===null||moneyToCents($price)<=0) throw new InvalidArgumentException(t('validation.vehicle_required_fields'));
            $stored = null;
            try {
                withTransaction(function () use ($agencyId,$categoryId,$registration,$brand,$model,$price,&$stored) {
                    if(isset($_FILES['image'])&&($_FILES['image']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) $stored=storeValidatedImage($_FILES['image'],'vehicle_media');
                    dbExecute('INSERT INTO vehicles (agency_id,category_id,registration_number,vin,brand,model,version,model_year,colour,fuel,transmission,seats,doors,luggage_capacity,current_mileage,base_daily_price,recommended_deposit,status,primary_image_path,created_by,updated_by) VALUES (:agency,:category,:registration,:vin,:brand,:model,:version,:year,:colour,:fuel,:transmission,:seats,:doors,:luggage,:mileage,:daily_price,:deposit,\'available\',:image,:creator,:updater)',[
                        'agency'=>$agencyId,'category'=>$categoryId,'registration'=>$registration,'vin'=>strtoupper(trim((string)($_POST['vin']??'')))?:null,'brand'=>$brand,'model'=>$model,'version'=>trim((string)($_POST['version']??''))?:null,'year'=>($_POST['model_year']??'')!==''?(int)$_POST['model_year']:null,'colour'=>trim((string)($_POST['colour']??''))?:null,'fuel'=>validateChoice($_POST['fuel']??'', ['petrol','diesel','hybrid','electric','other'],null),'transmission'=>validateChoice($_POST['transmission']??'', ['manual','automatic'],'manual'),'seats'=>(int)($_POST['seats']??0)?:null,'doors'=>(int)($_POST['doors']??0)?:null,'luggage'=>(int)($_POST['luggage_capacity']??0)?:null,'mileage'=>max(0,(int)($_POST['current_mileage']??0)),'daily_price'=>$price,'deposit'=>positiveMoney($_POST['recommended_deposit']??'0')??'0.00','image'=>$stored['path']??null,'creator'=>currentUserId(),'updater'=>currentUserId()
                    ]);
                    $id=(int)db()->lastInsertId();
                    if($stored) insertVehicleMedia(['id'=>$id,'agency_id'=>$agencyId],$stored,10,true);
                    dbExecute("INSERT INTO vehicle_status_history(vehicle_id,to_status,reason,changed_by) VALUES(:id,'available',:reason,:user)",['id'=>$id,'reason'=>t('message.vehicle_created'),'user'=>currentUserId()]);
                    auditLog('vehicle.created','vehicle',$id,null,['registration'=>$registration],$agencyId);
                });
            } catch (Throwable $exception) {
                if ($stored) removeNewStoredUpload($stored['path']);
                throw $exception;
            }
            flash('success',t('message.vehicle_created'));
        } elseif ($action === 'status') {
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
$categories=dbFetchAll('SELECT id,name,agency_id FROM vehicle_categories WHERE archived_at IS NULL ORDER BY name');

backofficeHeader(t('page.vehicles.title'),'vehicles.php');
pageHeader('page.vehicles.title', 'page.vehicles.description', ['breadcrumbs'=>[['label'=>'nav.fleet'],['label'=>'nav.vehicles']],'primary'=>can('vehicles.manage')?['label'=>'action.add_vehicle','href'=>'#new-vehicle']:null]);
?>
<form class="filters"><label><?=e(t('field.agency'))?><select name="agency_id"><?php foreach($agencies as $agency):if(currentUserRole()!==ROLE_OWNER&&!in_array((int)$agency['id'],$agencyIds,true))continue;?><option value="<?=e($agency['id'])?>" <?=$agencyId==(int)$agency['id']?'selected':''?>><?=e($agency['name'])?></option><?php endforeach;?></select></label><label><?=e(t('common.status'))?><select name="status"><option value=""><?=e(t('common.all'))?></option><?php foreach(vehicleStatuses() as $item):?><option value="<?=e($item)?>" <?=$status===$item?'selected':''?>><?=e(translatedStatus($item))?></option><?php endforeach;?></select></label><button class="btn secondary"><?=e(t('common.filter'))?></button></form>
<div class="grid"><?php if(can('vehicles.manage')):?><section class="card" id="new-vehicle"><div class="section-card-header"><h2><?=navigationIcon('fleet')?><?=e(t('section.new_vehicle'))?></h2></div><form method="post" enctype="multipart/form-data"><?=csrfField()?><input type="hidden" name="action" value="create"><div class="grid"><label><?=e(t('field.agency'))?><select name="agency_id" required><?php foreach($agencies as $agency):if(currentUserRole()!==ROLE_OWNER&&!in_array((int)$agency['id'],$agencyIds,true))continue;?><option value="<?=e($agency['id'])?>"><?=e($agency['name'])?></option><?php endforeach;?></select></label><label><?=e(t('field.category'))?><select name="category_id" required><?php foreach($categories as $category):?><option value="<?=e($category['id'])?>"><?=e($category['name'])?></option><?php endforeach;?></select></label><label><?=e(t('field.registration'))?><input name="registration_number" required></label><label><?=e(t('field.vin'))?><input name="vin"></label><label><?=e(t('field.brand'))?><input name="brand" required></label><label><?=e(t('field.model'))?><input name="model" required></label><label><?=e(t('field.version'))?><input name="version"></label><label><?=e(t('field.year'))?><input type="number" name="model_year" min="1900" max="<?=e((int)date('Y')+1)?>"></label><label><?=e(t('field.colour'))?><input name="colour"></label><label><?=e(t('field.fuel'))?><select name="fuel"><?php foreach(['petrol','diesel','hybrid','electric','other'] as $fuel):?><option value="<?=e($fuel)?>"><?=e(t('option.'.$fuel))?></option><?php endforeach;?></select></label><label><?=e(t('field.transmission'))?><select name="transmission"><?php foreach(['manual','automatic'] as $transmission):?><option value="<?=e($transmission)?>"><?=e(t('option.'.$transmission))?></option><?php endforeach;?></select></label><label><?=e(t('field.seats'))?><input type="number" name="seats" min="1" max="99"></label><label><?=e(t('field.doors'))?><input type="number" name="doors" min="1" max="10"></label><label><?=e(t('field.luggage'))?><input type="number" name="luggage_capacity" min="0" max="99"></label><label><?=e(t('field.mileage'))?><input type="number" name="current_mileage" min="0"></label><label><?=e(t('field.daily_price'))?><input name="base_daily_price" inputmode="decimal" required></label><label><?=e(t('field.deposit'))?><input name="recommended_deposit" inputmode="decimal"></label><label class="full"><?=e(t('field.vehicle_image',['size'=>formatFileSize(appConfig('upload_max_bytes'))]))?><input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label></div><button class="btn primary"><?=e(t('common.save'))?></button></form></section><?php endif;?>
<section class="card"><div class="section-card-header"><h2><?=navigationIcon('fleet')?><?=e(t('section.fleet'))?></h2></div><div class="table-wrap"><table><tr><th><?=e(t('field.vehicle'))?></th><th><?=e(t('field.registration'))?></th><th><?=e(t('field.category'))?></th><th><?=e(t('field.price_per_day'))?></th><th><?=e(t('common.status'))?></th><th><?=e(t('common.actions'))?></th></tr><?php foreach($vehicles as $vehicle):?><tr><td><?php if($vehicle['primary_media_id']):?><img class="media-thumbnail" src="vehicle_media.php?id=<?=e($vehicle['primary_media_id'])?>" alt=""><?php endif;?> <?=e($vehicle['brand'].' '.$vehicle['model'])?></td><td><?=isolatedValue($vehicle['registration_number'],'registration-value')?></td><td><?=e($vehicle['category_name'])?></td><td><?=money($vehicle['base_daily_price'])?></td><td><?=statusBadge($vehicle['status'])?></td><td><div class="inline-actions"><a class="btn secondary compact" href="vehicle_detail.php?id=<?=e($vehicle['id'])?>"><?=e(t('action.view_details'))?></a><?php if(can('vehicles.manage')):?><form method="post"><?=csrfField()?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?=e($vehicle['id'])?>"><select name="status" onchange="this.form.submit()"><?php foreach(vehicleStatuses() as $item):?><option value="<?=e($item)?>" <?=$item===$vehicle['status']?'selected':''?>><?=e(translatedStatus($item))?></option><?php endforeach;?></select></form><form method="post" data-confirm="<?=e(t('confirm.archive_vehicle'))?>"><?=csrfField()?><input type="hidden" name="action" value="archive"><input type="hidden" name="id" value="<?=e($vehicle['id'])?>"><button class="btn danger compact"><?=e(t('common.archive'))?></button></form><?php endif;?></div></td></tr><?php endforeach;?></table><?php if(!$vehicles):?><p class="empty"><?=e(t('empty.no_filtered_records'))?></p><?php endif;?></div></section></div>
<?php backofficeFooter();
