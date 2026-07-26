<?php
require_once __DIR__.'/_layout.php';
requirePermission('inspections.manage');
if(requestMethod()==='POST'){
    requireCsrfPost();flash('danger',t('validation.inspection_cutover_read_only'));safeRedirect('inspections.php');
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
backofficeHeader(t('page.inspections.title'),'inspections.php');
pageHeader('page.inspections.title','page.inspections.description',['breadcrumbs'=>[['label'=>'nav.rentals'],['label'=>'nav.inspections']]]);
?>
<div class="alert alert-warning" role="status"><span><?=e(t('validation.inspection_cutover_read_only'))?></span></div>
<section class="card"><h2><?=e(t('section.inspection_history'))?></h2><div class="table-wrap"><table><thead><tr><th><?=e(t('field.contract'))?></th><th><?=e(t('field.vehicle'))?></th><th><?=e(t('field.type'))?></th><th><?=e(t('field.date_time'))?></th><th><?=e(t('field.mileage'))?></th><th><?=e(t('field.fuel'))?></th><th><?=e(t('field.photos'))?></th><th><?=e(t('common.status'))?></th></tr></thead><tbody><?php foreach($inspections as$inspection):?><tr><td><?=isolatedValue($inspection['contract_number'],'reference-value')?></td><td><?=isolatedValue($inspection['registration_number'],'registration-value')?></td><td><?=e(t('option.'.$inspection['inspection_type']))?></td><td><?=formattedDateTime($inspection['inspected_at'])?></td><td><?=e($inspection['mileage'])?></td><td><?=e($inspection['fuel_level'])?>%</td><td><?=e($inspection['photo_count'])?>/6</td><td><?=statusBadge($inspection['status'])?></td></tr><?php endforeach;?></tbody></table><?php if(!$inspections)echo emptyState('empty.no_filtered_records');?></div></section>
<?php backofficeFooter();
