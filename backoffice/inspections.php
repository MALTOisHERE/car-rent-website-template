<?php
require_once __DIR__.'/_layout.php';
requirePermission('inspections.manage');
if(requestMethod()==='POST'){
    requireCsrfPost();
    try {
        $action=(string)($_POST['action']??'');
        if($action==='photo_bundle'){
            $inspectionId=(int)($_POST['inspection_id']??0);$raw=$_FILES['photos']??[];$expectedUploadFields=['name','type','tmp_name','error','size'];if(!is_array($raw)||array_keys($raw)!==$expectedUploadFields||!is_array($raw['name'])||array_keys($raw['name'])!==inspectionPhotoSlots())throw new InvalidArgumentException(t('validation.inspection_photo_bundle'));$uploads=[];
            foreach(inspectionPhotoSlots() as $slot)$uploads[$slot]=['name'=>$raw['name'][$slot]??null,'type'=>$raw['type'][$slot]??null,'tmp_name'=>$raw['tmp_name'][$slot]??null,'error'=>$raw['error'][$slot]??UPLOAD_ERR_NO_FILE,'size'=>$raw['size'][$slot]??0];
            $staged=inspectionPhotoStageBundle($uploads);inspectionPhotoPersistBundle($inspectionId,$staged,(string)($_POST['idempotency_key']??''));flash('success',t('message.inspection_photo_bundle_stored'));
        } elseif($action==='photo_archive') {
            inspectionPhotoArchive((int)($_POST['photo_id']??0),(string)($_POST['reason']??''));flash('success',t('message.inspection_photo_archived'));
        } else throw new DomainException(t('validation.inspection_cutover_read_only'));
    } catch(Throwable $e) { flash('danger',$e instanceof AuthorizationException?t('validation.not_authorized'):$e->getMessage()); }
    safeRedirect('inspections.php');
}
$agencyIds=currentAgencyIds();if(!$agencyIds)$agencyIds=[0];$ph=implode(',',array_fill(0,count($agencyIds),'?'));
$inspections=tableExists('contract_status_history')?dbFetchAll(
    "SELECT vi.*,rc.contract_number,v.registration_number,c.first_name,c.last_name,
            (SELECT COUNT(*) FROM inspection_photos ip WHERE ip.inspection_id=vi.id AND ip.archived_at IS NULL) photo_count
     FROM vehicle_inspections vi
     JOIN rental_contracts rc ON rc.id=vi.contract_id AND rc.agency_id=vi.agency_id
     JOIN vehicles v ON v.id=vi.vehicle_id AND v.agency_id=vi.agency_id
     JOIN customers c ON c.id=vi.customer_id AND c.agency_id=vi.agency_id
     WHERE vi.agency_id IN ($ph) ORDER BY vi.inspected_at DESC LIMIT 100",
    $agencyIds
):[];
$photosByInspection=[];if($inspections&&can('inspection_photos.view')){foreach(dbFetchAll("SELECT id,inspection_id,photo_slot,archived_at FROM inspection_photos WHERE inspection_id IN (".implode(',',array_fill(0,count($inspections),'?')).") ORDER BY photo_slot,id",array_column($inspections,'id')) as $photo)$photosByInspection[(int)$photo['inspection_id']][]=$photo;}
backofficeHeader(t('page.inspections.title'),'inspections.php');
pageHeader('page.inspections.title','page.inspections.description',['breadcrumbs'=>[['label'=>'nav.rentals'],['label'=>'nav.inspections']]]);
?>
<div class="alert alert-warning" role="status"><span><?=e(t('validation.inspection_photo_draft_only'))?></span></div>
<section class="card"><h2><?=e(t('section.inspection_history'))?></h2><div class="table-wrap"><table><thead><tr><th><?=e(t('field.contract'))?></th><th><?=e(t('field.vehicle'))?></th><th><?=e(t('field.type'))?></th><th><?=e(t('field.date_time'))?></th><th><?=e(t('field.mileage'))?></th><th><?=e(t('field.fuel'))?></th><th><?=e(t('field.photos'))?></th><th><?=e(t('common.status'))?></th></tr></thead><tbody><?php foreach($inspections as$inspection):?><tr><td><?=isolatedValue($inspection['contract_number'],'reference-value')?></td><td><?=isolatedValue($inspection['registration_number'],'registration-value')?></td><td><?=e(t('option.'.$inspection['inspection_type']))?></td><td><?=formattedDateTime($inspection['inspected_at'])?></td><td><?=e($inspection['mileage'])?></td><td><?=e($inspection['fuel_level'])?>%</td><td><?=e($inspection['photo_count'])?>/6<?php foreach($photosByInspection[(int)$inspection['id']]??[] as $photo):if(!$photo['archived_at']):?> <a href="inspection_photo.php?id=<?=e($photo['id'])?>"><?=e(t('photo.slot_'.$photo['photo_slot']))?></a><?php endif;endforeach;?></td><td><?=statusBadge($inspection['status'])?></td></tr><?php if($inspection['status']==='draft'&&can('inspection_photos.upload')):?><tr><td colspan="8"><form method="post" enctype="multipart/form-data"><?=csrfField()?><input type="hidden" name="action" value="photo_bundle"><input type="hidden" name="inspection_id" value="<?=e($inspection['id'])?>"><input type="hidden" name="idempotency_key" value="<?=e(bin2hex(random_bytes(32)))?>"><p><?=e(t('message.inspection_photo_guidance',['bytes'=>appConfig('upload_max_bytes')]))?></p><div class="grid"><?php foreach(inspectionPhotoSlots() as $slot):?><label><?=e(t('photo.slot_'.$slot))?><input type="file" name="photos[<?=e($slot)?>]" accept="image/jpeg,image/png,image/webp" required></label><?php endforeach;?></div><button class="btn primary"><?=e(t('action.upload_inspection_photos'))?></button></form></td></tr><?php endif;?><?php endforeach;?></tbody></table><?php if(!$inspections)echo emptyState('empty.no_filtered_records');?></div></section>
<?php backofficeFooter();
