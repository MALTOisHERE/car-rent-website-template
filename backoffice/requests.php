<?php
require_once __DIR__ . '/_layout.php';
requirePermission('reservations.manage');
if (requestMethod() === 'POST') {
    requireCsrfPost();
    try {
        $request = dbFetchOne('SELECT cr.*,c.agency_id FROM customer_requests cr JOIN customers c ON c.id=cr.customer_id WHERE cr.id=:id FOR UPDATE', ['id'=>(int)($_POST['id']??0)]);
        if (!$request) throw new InvalidArgumentException(t('validation.request_not_found'));
        requireAgencyAccess($request['agency_id']);
        $decision = validateChoice($_POST['decision']??'', ['approved','rejected'], '');
        if (!$decision) throw new InvalidArgumentException(t('validation.select_decision'));
        if ($decision === 'approved' && $request['request_type'] === 'cancellation_request' && $request['reservation_id']) {
            transitionReservation($request['reservation_id'], 'cancelled', 'Approved customer portal request');
        }
        dbExecute('UPDATE customer_requests SET status=:status,resolution_notes=:notes,resolved_at=NOW(),resolved_by=:user WHERE id=:id', ['status'=>$decision,'notes'=>trim((string)($_POST['notes']??'')),'user'=>currentUserId(),'id'=>$request['id']]);
        auditLog('customer_request.resolved','customer_request',$request['id'],['status'=>$request['status']],['status'=>$decision],$request['agency_id']);
        flash('success',t('message.request_resolved'));
    } catch (InvalidArgumentException|DomainException $exception) {
        flash('danger',$exception->getMessage());
    } catch (Throwable $exception) {
        reportDatabaseError($exception,'Customer request resolution failed');
        flash('danger',t('message.request_failed'));
    }
    safeRedirect('requests.php');
}
$ids=currentAgencyIds();if(!$ids)$ids=[0];$ph=implode(',',array_fill(0,count($ids),'?'));
$requests=dbFetchAll("SELECT cr.*,c.first_name,c.last_name,r.reference FROM customer_requests cr JOIN customers c ON c.id=cr.customer_id LEFT JOIN reservations r ON r.id=cr.reservation_id WHERE c.agency_id IN ($ph) ORDER BY FIELD(cr.status,'pending','approved','rejected'),cr.created_at DESC",$ids);
backofficeHeader(t('page.requests.title'),'requests.php');
pageHeader('page.requests.title','page.requests.description',['breadcrumbs'=>[['label'=>'nav.overview'],['label'=>'nav.portal_requests']]]);
?>
<div class="table-wrap" role="region" aria-label="<?=e(t('page.requests.title'))?>" tabindex="0"><table><thead><tr><th scope="col"><?=e(t('field.created'))?></th><th scope="col"><?=e(t('field.customer'))?></th><th scope="col"><?=e(t('field.reservation'))?></th><th scope="col"><?=e(t('field.type'))?></th><th scope="col"><?=e(t('field.message'))?></th><th scope="col"><?=e(t('common.status'))?></th><th scope="col"><?=e(t('field.action'))?></th></tr></thead><tbody>
<?php foreach($requests as $request):?><tr><td><?=formattedDateTime($request['created_at'])?></td><td><?=e($request['first_name'].' '.$request['last_name'])?></td><td><?=isolatedValue($request['reference'],'reference-value')?></td><td><?=e(translatedStatus($request['request_type']))?></td><td style="white-space:normal"><?=e($request['message'])?></td><td><?=statusBadge($request['status'])?></td><td><?php if($request['status']==='pending'):?><form method="post"><?=csrfField()?><input type="hidden" name="id" value="<?=e($request['id'])?>"><select name="decision"><?php foreach(['approved','rejected'] as $decision):?><option value="<?=e($decision)?>"><?=e(translatedStatus($decision))?></option><?php endforeach;?></select><input name="notes" placeholder="<?=e(t('field.notes'))?>"><button class="btn primary"><?=e(t('action.resolve'))?></button></form><?php else:?><?=e($request['resolution_notes'])?><?php endif;?></td></tr><?php endforeach;?></tbody></table><?php if(!$requests) echo emptyState('empty.no_requests'); ?></div>
<?php backofficeFooter();
