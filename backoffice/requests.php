<?php
require_once __DIR__ . '/_layout.php';
requirePermission('reservations.manage');
if (requestMethod() === 'POST') {
    requireCsrfPost();
    try {
        $request = dbFetchOne('SELECT cr.*,c.agency_id FROM customer_requests cr JOIN customers c ON c.id=cr.customer_id WHERE cr.id=:id FOR UPDATE', ['id'=>(int)($_POST['id']??0)]);
        if (!$request) throw new InvalidArgumentException('Request not found.');
        requireAgencyAccess($request['agency_id']);
        $decision = validateChoice($_POST['decision']??'', ['approved','rejected'], '');
        if (!$decision) throw new InvalidArgumentException('Select a decision.');
        if ($decision === 'approved' && $request['request_type'] === 'cancellation_request' && $request['reservation_id']) {
            transitionReservation($request['reservation_id'], 'cancelled', 'Approved customer portal request');
        }
        dbExecute('UPDATE customer_requests SET status=:status,resolution_notes=:notes,resolved_at=NOW(),resolved_by=:user WHERE id=:id', ['status'=>$decision,'notes'=>trim((string)($_POST['notes']??'')),'user'=>currentUserId(),'id'=>$request['id']]);
        auditLog('customer_request.resolved','customer_request',$request['id'],['status'=>$request['status']],['status'=>$decision],$request['agency_id']);
        flash('success','Customer request resolved.');
    } catch (InvalidArgumentException|DomainException $exception) {
        flash('danger',$exception->getMessage());
    } catch (Throwable $exception) {
        reportDatabaseError($exception,'Customer request resolution failed');
        flash('danger','The request could not be resolved.');
    }
    safeRedirect('requests.php');
}
$ids=currentAgencyIds();if(!$ids)$ids=[0];$ph=implode(',',array_fill(0,count($ids),'?'));
$requests=dbFetchAll("SELECT cr.*,c.first_name,c.last_name,r.reference FROM customer_requests cr JOIN customers c ON c.id=cr.customer_id LEFT JOIN reservations r ON r.id=cr.reservation_id WHERE c.agency_id IN ($ph) ORDER BY FIELD(cr.status,'pending','approved','rejected'),cr.created_at DESC",$ids);
backofficeHeader('Portal requests','requests.php');
pageHeader('Customer portal requests','Review and resolve customer modification and cancellation requests.',['breadcrumbs'=>[['label'=>'Overview'],['label'=>'Portal requests']]]);
?>
<div class="table-wrap" role="region" aria-label="Customer portal requests" tabindex="0"><table><thead><tr><th scope="col">Created</th><th scope="col">Customer</th><th scope="col">Reservation</th><th scope="col">Type</th><th scope="col">Message</th><th scope="col">Status</th><th scope="col">Decision</th></tr></thead><tbody>
<?php foreach($requests as $request):?><tr><td><?=e($request['created_at'])?></td><td><?=e($request['first_name'].' '.$request['last_name'])?></td><td><?=e($request['reference'])?></td><td><?=e($request['request_type'])?></td><td style="white-space:normal"><?=e($request['message'])?></td><td><?=statusBadge($request['status'])?></td><td><?php if($request['status']==='pending'):?><form method="post"><?=csrfField()?><input type="hidden" name="id" value="<?=e($request['id'])?>"><select name="decision"><option>approved</option><option>rejected</option></select><input name="notes" placeholder="Resolution notes"><button class="btn primary">Resolve</button></form><?php else:?><?=e($request['resolution_notes'])?><?php endif;?></td></tr><?php endforeach;?></tbody></table><?php if(!$requests) echo emptyState('No portal requests found'); ?></div>
<?php backofficeFooter();
