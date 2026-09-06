<?php
require_once __DIR__.'/_layout.php';
requirePermission('contract.view');
requirePermission('contract.create');

if(requestMethod()==='POST'){
    requireCsrfPost();$action=(string)($_POST['action']??'');$redirect='contract_form.php';
    try{
        if($action==='create'){
            $rawReservationId=$_POST['reservation_id']??null;if(!is_string($rawReservationId)||!preg_match('/^[1-9]\d*$/',$rawReservationId))throw new InvalidArgumentException(t('validation.contract_reservation_not_found'));
            $id=contractCreateFromReservation([
                'reservation_id'=>(int)$rawReservationId,
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

$agencyIds=contractScopedAgencyIds();$ph=implode(',',array_fill(0,count($agencyIds),'?'));
$eligible=dbFetchAll(
    "SELECT r.id,r.reference,c.first_name,c.last_name
     FROM reservations r JOIN customers c ON c.id=r.customer_id AND c.agency_id=r.agency_id
     LEFT JOIN rental_contracts rc ON rc.reservation_id=r.id AND rc.status IN('draft','issued','signed','active')
     WHERE r.agency_id IN ($ph) AND r.archived_at IS NULL
       AND r.status IN('confirmed','deposit_paid','ready') AND r.vehicle_id IS NOT NULL AND rc.id IS NULL
     ORDER BY r.created_at DESC LIMIT 200",
    $agencyIds
);

backofficeHeader(t('page.contract_create.title'),'contracts.php');
pageHeader('page.contract_create.title','page.contract_create.description',[
    'breadcrumbs'=>[['label'=>'nav.rentals'],['label'=>'nav.contracts','href'=>'contracts.php'],['label'=>'page.contract_create.title']],
    'secondary'=>['label'=>'common.cancel','href'=>'contracts.php'],
]);
?>
<section class="card form-card">
<?php if($eligible):?><form method="post"><?=csrfField()?><?=contractIdempotencyField('contract.create')?><input type="hidden" name="action" value="create">
<label><?=e(t('field.reservation'))?><select name="reservation_id" required><?php foreach($eligible as$row):?><option value="<?=e($row['id'])?>"><?=e($row['reference'].' - '.$row['first_name'].' '.$row['last_name'])?></option><?php endforeach;?></select></label>
<div class="form-actions"><button class="btn primary"><?=e(t('action.create_contract'))?></button><a class="btn secondary" href="contracts.php"><?=e(t('common.cancel'))?></a></div></form>
<?php else:?><?=emptyState('empty.no_eligible_reservations','message.contract_eligibility_help')?><?php endif;?>
</section>
<?php backofficeFooter();
