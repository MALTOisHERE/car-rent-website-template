<?php
require_once __DIR__.'/_layout.php';
requirePermission('contract.view');

if(requestMethod()==='POST'){
    requireCsrfPost();$action=(string)($_POST['action']??'');$redirect='contracts.php';
    try{
        if($action==='create'){
            $id=contractCreateFromReservation([
                'reservation_id'=>(int)($_POST['reservation_id']??0),
                'idempotency_key'=>$_POST['idempotency_key']??'',
            ]);
            flash('success',t('message.contract_created',['id'=>$id]));$redirect='contract_detail.php?id='.$id;
        }else throw new InvalidArgumentException(t('validation.invalid_action'));
    }catch(InvalidArgumentException|DomainException|AuthorizationException$exception){
        flash('danger',$exception->getMessage());
    }catch(Throwable$exception){
        reportDatabaseError($exception,'Contract register operation failed');flash('danger',t('message.contract_failed'));
    }
    safeRedirect($redirect);
}

$filters=['status'=>$_GET['status']??'','reservation_id'=>(int)($_GET['reservation_id']??0)];
$contracts=contractScopedList($filters);$agencyIds=contractScopedAgencyIds();$ph=implode(',',array_fill(0,count($agencyIds),'?'));
$eligible=[];
if(can('contract.create')){
    $eligible=dbFetchAll(
        "SELECT r.id,r.reference,c.first_name,c.last_name
         FROM reservations r JOIN customers c ON c.id=r.customer_id AND c.agency_id=r.agency_id
         LEFT JOIN rental_contracts rc ON rc.reservation_id=r.id AND rc.status IN('draft','issued','signed','active')
         WHERE r.agency_id IN ($ph) AND r.archived_at IS NULL
           AND r.status IN('confirmed','deposit_paid','ready') AND r.vehicle_id IS NOT NULL AND rc.id IS NULL
         ORDER BY r.created_at DESC LIMIT 200",
        $agencyIds
    );
}
backofficeHeader(t('page.contracts.title'),'contracts.php');
pageHeader('page.contracts.title','page.contracts.description',['breadcrumbs'=>[['label'=>'nav.rentals'],['label'=>'nav.contracts']]]);
?>
<?php if(can('contract.create')):?><section class="card" id="new-contract"><h2><?=e(t('section.create_contract'))?></h2>
<?php if($eligible):?><form method="post"><?=csrfField()?><?=contractIdempotencyField('contract.create')?><input type="hidden" name="action" value="create">
<label><?=e(t('field.reservation'))?><select name="reservation_id" required><?php foreach($eligible as$row):?><option value="<?=e($row['id'])?>"><?=e($row['reference'].' — '.$row['first_name'].' '.$row['last_name'])?></option><?php endforeach;?></select></label>
<button class="btn primary"><?=e(t('action.create_contract'))?></button></form>
<?php else:?><?=emptyState('empty.no_eligible_reservations','message.contract_eligibility_help')?><?php endif;?></section><?php endif;?>
<form class="filters" method="get"><label><?=e(t('common.status'))?><select name="status"><option value=""><?=e(t('common.all'))?></option><?php foreach(contractLifecycleStatuses()as$status):?><option value="<?=e($status)?>" <?=$filters['status']===$status?'selected':''?>><?=e(translatedStatus($status))?></option><?php endforeach;?></select></label><button class="btn secondary"><?=e(t('common.filter'))?></button><a class="btn ghost" href="contracts.php"><?=e(t('common.reset'))?></a></form>
<section class="card"><div class="section-heading"><h2><?=e(t('section.contract_register'))?></h2><span><?=e(t('message.record_count',['count'=>count($contracts)]))?></span></div><div class="table-wrap"><table><thead><tr><th><?=e(t('field.number'))?></th><th><?=e(t('field.agency'))?></th><th><?=e(t('field.reservation'))?></th><th><?=e(t('field.customer'))?></th><th><?=e(t('field.vehicle'))?></th><th><?=e(t('field.version'))?></th><th><?=e(t('common.status'))?></th><th><?=e(t('common.actions'))?></th></tr></thead><tbody>
<?php foreach($contracts as$contract):?><tr><td><?=isolatedValue($contract['contract_number'],'reference-value')?></td><td><?=e($contract['agency_name'])?></td><td><?=isolatedValue($contract['reference'],'reference-value')?></td><td><?=e($contract['first_name'].' '.$contract['last_name'])?></td><td><?=isolatedValue($contract['registration_number'],'registration-value')?></td><td><?=e($contract['current_version'])?></td><td><?=statusBadge($contract['status'])?></td><td><?=actionMenu([['label'=>'common.view','href'=>'contract_detail.php?id='.$contract['id']]])?></td></tr><?php endforeach;?>
</tbody></table><?php if(!$contracts)echo emptyState('empty.no_filtered_records','empty.adjust_filters');?></div></section>
<?php backofficeFooter();
