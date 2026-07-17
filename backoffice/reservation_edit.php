<?php
require_once __DIR__ . '/_layout.php';
requirePermission('reservations.manage');
if (requestMethod() === 'POST') {
    requireCsrfPost();
    try {
        updateReservationAllocation((int)($_POST['reservation_id']??0), ['vehicle_id'=>(int)($_POST['vehicle_id']??0),'pickup_at'=>$_POST['pickup_at']??'','return_at'=>$_POST['return_at']??'']);
        flash('success','Reservation allocation updated after a concurrency-safe conflict check.');
    } catch (InvalidArgumentException|DomainException $exception) {
        flash('danger',$exception->getMessage());
    } catch (Throwable $exception) {
        reportDatabaseError($exception,'Reservation allocation update failed');
        flash('danger','The allocation could not be updated.');
    }
    safeRedirect('reservation_edit.php');
}
$ids=currentAgencyIds();if(!$ids)$ids=[0];$ph=implode(',',array_fill(0,count($ids),'?'));
$reservations=dbFetchAll("SELECT r.*,c.first_name,c.last_name,v.registration_number FROM reservations r JOIN customers c ON c.id=r.customer_id LEFT JOIN vehicles v ON v.id=r.vehicle_id WHERE r.agency_id IN ($ph) AND r.status IN ('draft','quote','pending','confirmed','deposit_paid','ready','active') ORDER BY r.pickup_at",$ids);
$vehicles=dbFetchAll("SELECT id,registration_number,brand,model,status FROM vehicles WHERE agency_id IN ($ph) AND archived_at IS NULL AND status NOT IN ('maintenance','damaged','blocked','sold','retired') ORDER BY registration_number",$ids);
backofficeHeader('Reservation allocation','reservation_edit.php');
pageHeader('Reservation allocation','Edit dates, extend a rental, or replace its vehicle after a conflict check.',['breadcrumbs'=>[['label'=>'Rentals','href'=>'reservations.php'],['label'=>'Allocation editor']]]);
?>
<section class="card"><p>The server locks the reservation and target vehicle, rechecks overlaps and maintenance, then recalculates totals from the stored agreed daily price or the replacement vehicle price.</p><form method="post"><?=csrfField()?>
<label>Reservation<select name="reservation_id" id="reservation" required data-reservation-allocation><?php foreach($reservations as $reservation):?><option value="<?=e($reservation['id'])?>" data-pickup="<?=e(date('Y-m-d\TH:i',strtotime($reservation['pickup_at'])))?>" data-return="<?=e(date('Y-m-d\TH:i',strtotime($reservation['return_at'])))?>" data-vehicle="<?=e($reservation['vehicle_id'])?>"><?=e($reservation['reference'].' — '.$reservation['first_name'].' '.$reservation['last_name'].' — '.$reservation['status'])?></option><?php endforeach;?></select></label>
<label>Vehicle<select name="vehicle_id" id="vehicle"><?php foreach($vehicles as $vehicle):?><option value="<?=e($vehicle['id'])?>"><?=e($vehicle['registration_number'].' '.$vehicle['brand'].' '.$vehicle['model'].' — '.$vehicle['status'])?></option><?php endforeach;?></select></label>
<label>Pickup<input type="datetime-local" name="pickup_at" id="pickup" required></label><label>Return / extension<input type="datetime-local" name="return_at" id="return" required></label><button class="btn primary">Validate and update allocation</button></form></section>
<?php backofficeFooter();
