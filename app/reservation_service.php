<?php

function reservationSources()
{
    return ['phone','WhatsApp','website','Instagram','Facebook','agency','hotel','partner','marketplace','other'];
}

function scopedReservationRecord($reservationId, $lock = false)
{
    $ids=currentAgencyIds();if(!$ids)$ids=[0];$ph=implode(',',array_fill(0,count($ids),'?'));
    $sql="SELECT * FROM reservations WHERE id=? AND agency_id IN ($ph) AND archived_at IS NULL".($lock?' FOR UPDATE':'');
    return dbFetchOne($sql,array_merge([(int)$reservationId],$ids));
}

function reservationUnavailableVehicleStatuses()
{
    return ['maintenance','damaged','blocked','sold','retired'];
}

function lockReservationVehicleRows($currentVehicleId, $requestedVehicleId, $originAgencyId)
{
    $ids=array_values(array_unique([(int)$currentVehicleId,(int)$requestedVehicleId]));
    $ids=array_values(array_filter($ids,static function($id){return$id>0;}));sort($ids,SORT_NUMERIC);
    if(!$ids)throw new DomainException(t('validation.vehicle_unavailable'));
    $ph=implode(',',array_fill(0,count($ids),'?'));
    $rows=dbFetchAll("SELECT * FROM vehicles WHERE id IN ($ph) ORDER BY id ASC FOR UPDATE",$ids);$byId=[];
    foreach($rows as $row)$byId[(int)$row['id']]=$row;
    foreach($ids as $id)if(!isset($byId[$id])||(int)$byId[$id]['agency_id']!==(int)$originAgencyId)throw new DomainException(t('validation.vehicle_unavailable'));
    return$byId;
}

function requireReservationVehicleStateUpdate($sql, array $parameters)
{
    if(dbExecute($sql,$parameters)->rowCount()!==1)throw new DomainException(t('validation.vehicle_unavailable'));
}

function authorizedReturnAgency($originAgencyId, $returnAgencyId)
{
    if(currentUserRole()!==ROLE_OWNER){$ids=currentAgencyIds();if(!in_array((int)$originAgencyId,$ids,true)||!in_array((int)$returnAgencyId,$ids,true))throw new AuthorizationException(t('validation.not_authorized'));}
    $agency=dbFetchOne("SELECT id FROM agencies WHERE id=:id AND status='active' AND archived_at IS NULL",['id'=>(int)$returnAgencyId]);
    if(!$agency)throw new DomainException(t('validation.return_agency'));
    return(int)$agency['id'];
}

function pricingSnapshotArray($json)
{
    $snapshot=json_decode((string)$json,true);
    return is_array($snapshot)?$snapshot:[];
}

function nextPricingSnapshot(array $previous, $event, array $pricing, array $metadata = [])
{
    $version=max(0,(int)($previous['snapshot_version']??$previous['version']??0))+1;
    return array_merge($pricing,[
        'schema_version'=>2,
        'snapshot_version'=>$version,
        'event'=>$event,
        'previous_snapshot_hash'=>hash('sha256',json_encode($previous,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)),
        'actor_id'=>currentUserId(),
        'created_at'=>date('c'),
    ],$metadata);
}

function preservedReservationPrice(array $reservation, DateTimeImmutable $pickup, DateTimeImmutable $return, $dailyPrice = null)
{
    if($reservation['tax_rate']===null)throw new DomainException(t('validation.legacy_tax_required'));
    $seconds=$return->getTimestamp()-$pickup->getTimestamp();if($seconds<=0)throw new InvalidArgumentException(t('validation.reservation_period'));
    $days=max(1,(int)ceil($seconds/86400));
    $daily=moneyToCents($dailyPrice??$reservation['daily_price']);$options=moneyToCents($reservation['options_total'])??0;$fees=moneyToCents($reservation['fees_total'])??0;
    if($daily===null||$daily<0)throw new InvalidArgumentException(t('validation.pricing_values'));
    $previous=pricingSnapshotArray($reservation['pricing_snapshot_json']);$fixed=moneyToCents($previous['pricing_rule_adjustment']??'0.00')??0;
    $discountBp=percentageToBasisPoints($reservation['discount_percent']);$taxBp=percentageToBasisPoints($reservation['tax_rate']);
    if($discountBp===null||$taxBp===null)throw new InvalidArgumentException(t('validation.pricing_values'));
    $base=$daily*$days;$subtotal=max(0,$base+$fixed+$options+$fees);$discount=percentageOfCents($subtotal,$discountBp);$taxable=max(0,$subtotal-$discount);$tax=percentageOfCents($taxable,$taxBp);$total=$taxable+$tax;
    return [
        'days'=>$days,'daily_price'=>centsToMoney($daily),'base_total'=>centsToMoney($base),
        'options_total'=>centsToMoney($options),'fees_total'=>centsToMoney($fees),'pricing_rule_adjustment'=>centsToMoney($fixed),
        'discount_percent'=>centsToMoney($discountBp),'discount_amount'=>centsToMoney($discount),
        'tax_rate'=>centsToMoney($taxBp),'tax_amount'=>centsToMoney($tax),'total'=>centsToMoney($total),
        'currency'=>$reservation['currency'],
    ];
}

function createReservationWorkspace(array $input)
{
    enforcePermission('reservations.manage');
    return withTransaction(function()use($input){
        $agencyId=(int)($input['agency_id']??0);requireAgencyAccess($agencyId);$returnAgency=authorizedReturnAgency($agencyId,(int)($input['return_agency_id']??$agencyId));
        $customer=dbFetchOne("SELECT * FROM customers WHERE id=:id AND agency_id=:agency AND archived_at IS NULL FOR UPDATE",['id'=>(int)($input['customer_id']??0),'agency'=>$agencyId]);
        if(!$customer||in_array($customer['status'],['blocked','archived'],true))throw new DomainException(t('validation.eligible_customer'));
        $vehicle=dbFetchOne('SELECT * FROM vehicles WHERE id=:id AND agency_id=:agency AND archived_at IS NULL FOR UPDATE',['id'=>(int)($input['vehicle_id']??0),'agency'=>$agencyId]);
        if(!$vehicle||in_array($vehicle['status'],['maintenance','damaged','blocked','sold','retired'],true))throw new DomainException(t('validation.vehicle_unavailable'));
        $pickup=validDateTimeValue($input['pickup_at']??'');$return=validDateTimeValue($input['return_at']??'');
        if(!$pickup||!$return||$return<=$pickup)throw new InvalidArgumentException(t('validation.reservation_period'));
        if(vehicleHasConflict($vehicle['id'],$pickup,$return)){auditLog('reservation.conflict','vehicle',$vehicle['id'],null,['period'=>'rejected'],$agencyId);throw new DomainException(t('validation.vehicle_conflict'));}
        $discountBp=percentageToBasisPoints($input['discount_percent']??'0');$taxBp=percentageToBasisPoints($input['tax_rate']??'0');$options=positiveMoney($input['options_total']??'0');$fees=positiveMoney($input['fees_total']??'0');$deposit=positiveMoney($input['deposit_amount']??$vehicle['recommended_deposit']);
        if($discountBp===null||$taxBp===null||$options===null||$fees===null||$deposit===null)throw new InvalidArgumentException(t('validation.pricing_values'));
        if($discountBp>(appConfig('manager_discount_threshold')*100)&&!in_array(currentUserRole(),[ROLE_OWNER,ROLE_AGENCY_MANAGER],true))throw new AuthorizationException(t('validation.discount_authorization'));
        $pricing=calculateRentalPrice(['pickup_at'=>$pickup,'return_at'=>$return,'daily_price'=>$vehicle['base_daily_price'],'agency_id'=>$agencyId,'category_id'=>$vehicle['category_id'],'options_total'=>$options,'fees_total'=>$fees,'discount_percent'=>centsToMoney($discountBp),'tax_rate'=>centsToMoney($taxBp)]);
        $status=validateChoice($input['status']??'pending',['draft','quote','pending','confirmed'],'pending');$reference=generateBusinessReference($status==='quote'?'QTE':'RES');$pending=$status==='pending'?date('Y-m-d H:i:s',time()+appConfig('pending_reservation_minutes')*60):null;
        $snapshot=nextPricingSnapshot([], 'creation', array_merge($pricing,['currency'=>appConfig('currency')]),['applied_rules'=>$pricing['applied_rules']??[]]);
        dbExecute('INSERT INTO reservations(reference,agency_id,return_agency_id,customer_id,vehicle_id,category_id,status,source,pickup_at,return_at,pickup_location,return_location,delivery_location,pending_expires_at,currency,daily_price,rental_days,options_total,fees_total,discount_amount,discount_percent,discount_reason,tax_rate,tax_amount,total_amount,remaining_amount,deposit_amount,pricing_snapshot_json,internal_notes,created_by,updated_by) VALUES(:reference,:agency,:return_agency,:customer,:vehicle,:category,:status,:source,:pickup,:return_at,:pickup_location,:return_location,:delivery,:pending,:currency,:daily,:days,:options,:fees,:discount_amount,:discount_percent,:discount_reason,:tax_rate,:tax_amount,:total,:remaining,:deposit,:snapshot,:notes,:user,:user2)',[
            'reference'=>$reference,'agency'=>$agencyId,'return_agency'=>$returnAgency,'customer'=>$customer['id'],'vehicle'=>$vehicle['id'],'category'=>$vehicle['category_id'],'status'=>$status,'source'=>validateChoice($input['source']??'agency',reservationSources(),'agency'),'pickup'=>$pickup->format('Y-m-d H:i:s'),'return_at'=>$return->format('Y-m-d H:i:s'),'pickup_location'=>trim((string)($input['pickup_location']??'')),'return_location'=>trim((string)($input['return_location']??'')),'delivery'=>trim((string)($input['delivery_location']??'')),'pending'=>$pending,'currency'=>appConfig('currency'),'daily'=>$pricing['daily_price'],'days'=>$pricing['days'],'options'=>$pricing['options_total'],'fees'=>$pricing['fees_total'],'discount_amount'=>$pricing['discount_amount'],'discount_percent'=>$pricing['discount_percent'],'discount_reason'=>trim((string)($input['discount_reason']??'')),'tax_rate'=>$pricing['tax_rate'],'tax_amount'=>$pricing['tax_amount'],'total'=>$pricing['total'],'remaining'=>$pricing['total'],'deposit'=>$deposit,'snapshot'=>json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'notes'=>trim((string)($input['internal_notes']??'')),'user'=>currentUserId(),'user2'=>currentUserId()
        ]);
        $id=(int)db()->lastInsertId();dbExecute('INSERT INTO reservation_status_history(reservation_id,from_status,to_status,changed_by) VALUES(:id,NULL,:status,:user)',['id'=>$id,'status'=>$status,'user'=>currentUserId()]);
        if($status==='confirmed')dbExecute("UPDATE vehicles SET status='reserved',updated_by=:user WHERE id=:id AND status='available'",['user'=>currentUserId(),'id'=>$vehicle['id']]);
        auditLog('reservation.created','reservation',$id,null,['reference'=>$reference,'status'=>$status,'total'=>$pricing['total']],$agencyId);return$id;
    });
}

function transitionReservationWorkspace($reservationId, $newStatus, $reason = null)
{
    if(in_array($newStatus,['active','completed'],true)){
        throw new DomainException(t('validation.rental_orchestration_required'));
    }
    enforcePermission(in_array($newStatus,['cancelled','no_show'],true)?'reservations.lifecycle':'reservations.manage');
    return withTransaction(function()use($reservationId,$newStatus,$reason){
        $reservation=scopedReservationRecord($reservationId,true);if(!$reservation||!in_array($newStatus,reservationTransitions()[$reservation['status']]??[],true))throw new DomainException(t('validation.reservation_transition'));
        $reason=trim((string)$reason);if(in_array($newStatus,['cancelled','no_show'],true)&&($reason===''||mb_strlen($reason)>255))throw new InvalidArgumentException(t('validation.lifecycle_reason'));
        $vehicle=null;
        if(in_array($newStatus,['confirmed','deposit_paid','ready','active'],true)){
            $vehicles=lockReservationVehicleRows($reservation['vehicle_id'],$reservation['vehicle_id'],$reservation['agency_id']);$vehicle=$vehicles[(int)$reservation['vehicle_id']];
            if($vehicle['archived_at']!==null||in_array($vehicle['status'],reservationUnavailableVehicleStatuses(),true))throw new DomainException(t('validation.vehicle_unavailable'));
            $expectedState=$newStatus==='confirmed'?'available':'reserved';
            if($vehicle['status']!==$expectedState)throw new DomainException(t('validation.vehicle_unavailable'));
            if(vehicleHasConflict($vehicle['id'],new DateTimeImmutable($reservation['pickup_at']),new DateTimeImmutable($reservation['return_at']),$reservation['id']))throw new DomainException(t('validation.vehicle_conflict'));
        }
        if($newStatus==='confirmed')requireReservationVehicleStateUpdate("UPDATE vehicles SET status='reserved',updated_by=:user WHERE id=:id AND agency_id=:agency AND archived_at IS NULL AND status='available'",['user'=>currentUserId(),'id'=>$reservation['vehicle_id'],'agency'=>$reservation['agency_id']]);
        elseif($newStatus==='active')requireReservationVehicleStateUpdate("UPDATE vehicles SET status='rented',updated_by=:user WHERE id=:id AND agency_id=:agency AND archived_at IS NULL AND status='reserved'",['user'=>currentUserId(),'id'=>$reservation['vehicle_id'],'agency'=>$reservation['agency_id']]);
        dbExecute('UPDATE reservations SET status=:status,cancellation_reason=:reason,updated_by=:user WHERE id=:id',['status'=>$newStatus,'reason'=>$newStatus==='cancelled'?$reason:null,'user'=>currentUserId(),'id'=>$reservation['id']]);
        if($newStatus==='completed')dbExecute("UPDATE vehicles SET status='cleaning',updated_by=:user WHERE id=:id",['user'=>currentUserId(),'id'=>$reservation['vehicle_id']]);
        elseif(in_array($newStatus,['cancelled','no_show','expired'],true))dbExecute("UPDATE vehicles SET status='available',updated_by=:user WHERE id=:id AND status='reserved'",['user'=>currentUserId(),'id'=>$reservation['vehicle_id']]);
        dbExecute('INSERT INTO reservation_status_history(reservation_id,from_status,to_status,reason,changed_by) VALUES(:id,:from_status,:to_status,:reason,:user)',['id'=>$reservation['id'],'from_status'=>$reservation['status'],'to_status'=>$newStatus,'reason'=>$reason?:null,'user'=>currentUserId()]);
        auditLog('reservation.status_changed','reservation',$reservation['id'],['status'=>$reservation['status']],['status'=>$newStatus,'reason'=>$reason],$reservation['agency_id']);return true;
    });
}

function updateReservationAllocationWorkspace($reservationId, array $input)
{
    enforcePermission('reservations.manage');
    $overrideProvided=trim((string)($input['override_daily_price']??''))!==''||trim((string)($input['override_reason']??''))!=='';
    if($overrideProvided)enforcePermission('reservations.commercial_override');
    $operation=function()use($reservationId,$input,$overrideProvided){
        $reservation=scopedReservationRecord($reservationId,true);if(!$reservation||in_array($reservation['status'],['completed','cancelled','no_show','expired'],true))throw new DomainException(t('validation.reservation_locked'));
        $expected=(string)($input['updated_at']??'');if($expected!==''&&!hash_equals((string)$reservation['updated_at'],$expected))throw new DomainException(t('validation.stale_reservation'));
        $vehicleId=(int)($input['vehicle_id']??$reservation['vehicle_id']);$vehicleChanged=$vehicleId!==(int)$reservation['vehicle_id'];
        $lockedVehicles=lockReservationVehicleRows($reservation['vehicle_id'],$vehicleId,$reservation['agency_id']);$currentVehicle=$lockedVehicles[(int)$reservation['vehicle_id']];$vehicle=$lockedVehicles[$vehicleId];
        if($vehicle['archived_at']!==null||in_array($vehicle['status'],reservationUnavailableVehicleStatuses(),true))throw new DomainException(t('validation.vehicle_unavailable'));
        if($vehicleChanged&&$vehicle['status']!=='available')throw new DomainException(t('validation.vehicle_unavailable'));
        if($vehicleChanged&&in_array($reservation['status'],['confirmed','deposit_paid','ready'],true)&&$currentVehicle['status']!=='reserved')throw new DomainException(t('validation.vehicle_unavailable'));
        if($vehicleChanged&&$reservation['status']==='active'&&$currentVehicle['status']!=='rented')throw new DomainException(t('validation.vehicle_unavailable'));
        $pickup=validDateTimeValue($input['pickup_at']??date('Y-m-d\TH:i',strtotime($reservation['pickup_at'])));$return=validDateTimeValue($input['return_at']??date('Y-m-d\TH:i',strtotime($reservation['return_at'])));
        if(!$pickup||!$return||$return<=$pickup)throw new InvalidArgumentException(t('validation.reservation_period'));
        $mode=validateChoice($input['mode']??'edit',['edit','extend','replace'],'edit');if($mode==='extend'&&$return<=new DateTimeImmutable($reservation['return_at']))throw new DomainException(t('validation.extension_period'));
        if(vehicleHasConflict($vehicleId,$pickup,$return,$reservation['id'])){auditLog('reservation.edit_conflict','reservation',$reservation['id'],null,['vehicle_id'=>$vehicleId,'period'=>'rejected'],$reservation['agency_id']);throw new DomainException(t('validation.vehicle_conflict'));}
        $datesChanged=$pickup->format('Y-m-d H:i:s')!==$reservation['pickup_at']||$return->format('Y-m-d H:i:s')!==$reservation['return_at'];
        $overrideReason=trim((string)($input['override_reason']??''));$daily=$reservation['daily_price'];
        if($overrideProvided){$daily=positiveMoney($input['override_daily_price']??'');if($daily===null||(float)$daily<=0||$overrideReason===''||mb_strlen($overrideReason)>255)throw new InvalidArgumentException(t('validation.commercial_override'));}
        if(($datesChanged||$overrideProvided)&&$reservation['tax_rate']===null)throw new DomainException(t('validation.legacy_tax_required'));
        $previous=pricingSnapshotArray($reservation['pricing_snapshot_json']);
        if($datesChanged||$overrideProvided){$pricing=preservedReservationPrice($reservation,$pickup,$return,$daily);$total=$pricing['total'];}
        else{$pricing=['days'=>(int)$reservation['rental_days'],'daily_price'=>$reservation['daily_price'],'base_total'=>centsToMoney((moneyToCents($reservation['daily_price'])??0)*(int)$reservation['rental_days']),'options_total'=>$reservation['options_total'],'fees_total'=>$reservation['fees_total'],'pricing_rule_adjustment'=>$previous['pricing_rule_adjustment']??'0.00','discount_percent'=>$reservation['discount_percent'],'discount_amount'=>$reservation['discount_amount'],'tax_rate'=>$reservation['tax_rate'],'tax_amount'=>$reservation['tax_amount'],'total'=>$reservation['total_amount'],'currency'=>$reservation['currency']];$total=$reservation['total_amount'];}
        $event=$overrideProvided?'commercial_override':($vehicleChanged?'replacement':($mode==='extend'?'extension':'allocation_edit'));
        $snapshot=nextPricingSnapshot($previous,$event,$pricing,['old_period'=>[$reservation['pickup_at'],$reservation['return_at']],'new_period'=>[$pickup->format('Y-m-d H:i:s'),$return->format('Y-m-d H:i:s')],'old_vehicle_id'=>(int)$reservation['vehicle_id'],'new_vehicle_id'=>$vehicleId,'override_reason'=>$overrideProvided?$overrideReason:null]);
        if($vehicleChanged){
            if($reservation['status']==='active'){
                requireReservationVehicleStateUpdate("UPDATE vehicles SET status='cleaning',updated_by=:user WHERE id=:id AND agency_id=:agency AND status='rented'",['user'=>currentUserId(),'id'=>$reservation['vehicle_id'],'agency'=>$reservation['agency_id']]);
                requireReservationVehicleStateUpdate("UPDATE vehicles SET status='rented',updated_by=:user WHERE id=:id AND agency_id=:agency AND archived_at IS NULL AND status='available'",['user'=>currentUserId(),'id'=>$vehicleId,'agency'=>$reservation['agency_id']]);
            }elseif(in_array($reservation['status'],['confirmed','deposit_paid','ready'],true)){
                requireReservationVehicleStateUpdate("UPDATE vehicles SET status='available',updated_by=:user WHERE id=:id AND agency_id=:agency AND status='reserved'",['user'=>currentUserId(),'id'=>$reservation['vehicle_id'],'agency'=>$reservation['agency_id']]);
                requireReservationVehicleStateUpdate("UPDATE vehicles SET status='reserved',updated_by=:user WHERE id=:id AND agency_id=:agency AND archived_at IS NULL AND status='available'",['user'=>currentUserId(),'id'=>$vehicleId,'agency'=>$reservation['agency_id']]);
            }
        }
        dbExecute('UPDATE reservations SET vehicle_id=:vehicle,category_id=:category,pickup_at=:pickup,return_at=:return_at,daily_price=:daily,rental_days=:days,discount_amount=:discount,tax_rate=:tax_rate,tax_amount=:tax,total_amount=:total,remaining_amount=GREATEST(0,:total2-advance_amount),pricing_snapshot_json=:snapshot,updated_by=:user WHERE id=:id',['vehicle'=>$vehicleId,'category'=>$vehicle['category_id'],'pickup'=>$pickup->format('Y-m-d H:i:s'),'return_at'=>$return->format('Y-m-d H:i:s'),'daily'=>$pricing['daily_price'],'days'=>$pricing['days'],'discount'=>$pricing['discount_amount'],'tax_rate'=>$pricing['tax_rate'],'tax'=>$pricing['tax_amount'],'total'=>$total,'total2'=>$total,'snapshot'=>json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'user'=>currentUserId(),'id'=>$reservation['id']]);
        $beforeAudit=['vehicle_id'=>(int)$reservation['vehicle_id'],'pickup_at'=>$reservation['pickup_at'],'return_at'=>$reservation['return_at'],'daily_price'=>$reservation['daily_price'],'rental_days'=>(int)$reservation['rental_days'],'options_total'=>$reservation['options_total'],'fees_total'=>$reservation['fees_total'],'pricing_rule_adjustment'=>$previous['pricing_rule_adjustment']??'0.00','discount_percent'=>$reservation['discount_percent'],'discount_amount'=>$reservation['discount_amount'],'tax_rate'=>$reservation['tax_rate'],'tax_amount'=>$reservation['tax_amount'],'total'=>$reservation['total_amount'],'currency'=>$reservation['currency'],'snapshot_hash'=>hash('sha256',(string)$reservation['pricing_snapshot_json'])];
        $afterAudit=['vehicle_id'=>$vehicleId,'pickup_at'=>$pickup->format('Y-m-d H:i:s'),'return_at'=>$return->format('Y-m-d H:i:s'),'daily_price'=>$pricing['daily_price'],'rental_days'=>$pricing['days'],'options_total'=>$pricing['options_total'],'fees_total'=>$pricing['fees_total'],'pricing_rule_adjustment'=>$pricing['pricing_rule_adjustment'],'discount_percent'=>$pricing['discount_percent'],'discount_amount'=>$pricing['discount_amount'],'tax_rate'=>$pricing['tax_rate'],'tax_amount'=>$pricing['tax_amount'],'total'=>$total,'currency'=>$reservation['currency'],'event'=>$event,'reason'=>$overrideProvided?$overrideReason:null,'snapshot_version'=>$snapshot['snapshot_version'],'snapshot_hash'=>hash('sha256',json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))];
        auditLog($overrideProvided?'reservation.commercial_override':'reservation.allocation_updated','reservation',$reservation['id'],$beforeAudit,$afterAudit,$reservation['agency_id']);return true;
    };
    for($attempt=0;$attempt<3;$attempt++){
        try{return withTransaction($operation);}catch(PDOException$exception){
            $driverCode=(int)($exception->errorInfo[1]??0);$retryable=$exception->getCode()==='40001'||in_array($driverCode,[1205,1213],true);
            if(!$retryable)throw$exception;
            if($attempt===2)throw new DomainException(t('validation.vehicle_conflict'));
        }
    }
    throw new DomainException(t('validation.vehicle_conflict'));
}

function resolveLegacyReservationTaxRate($reservationId, $taxRate, $reason, $expectedUpdatedAt)
{
    enforcePermission('reservations.commercial_override');$basisPoints=percentageToBasisPoints($taxRate);$reason=trim((string)$reason);
    if($basisPoints===null||$reason===''||mb_strlen($reason)>255)throw new InvalidArgumentException(t('validation.tax_resolution'));
    return withTransaction(function()use($reservationId,$basisPoints,$reason,$expectedUpdatedAt){
        $reservation=scopedReservationRecord($reservationId,true);if(!$reservation)throw new InvalidArgumentException(t('validation.reservation_not_found'));
        if(!hash_equals((string)$reservation['updated_at'],(string)$expectedUpdatedAt))throw new DomainException(t('validation.stale_reservation'));
        if($reservation['tax_rate']!==null)throw new DomainException(t('validation.tax_already_resolved'));
        $previous=pricingSnapshotArray($reservation['pricing_snapshot_json']);$pricing=['days'=>(int)$reservation['rental_days'],'daily_price'=>$reservation['daily_price'],'options_total'=>$reservation['options_total'],'fees_total'=>$reservation['fees_total'],'discount_percent'=>$reservation['discount_percent'],'discount_amount'=>$reservation['discount_amount'],'tax_rate'=>centsToMoney($basisPoints),'tax_amount'=>$reservation['tax_amount'],'total'=>$reservation['total_amount'],'currency'=>$reservation['currency']];
        $snapshot=nextPricingSnapshot($previous,'legacy_tax_resolution',$pricing,['totals_unchanged'=>true,'reason'=>$reason]);
        dbExecute('UPDATE reservations SET tax_rate=:rate,pricing_snapshot_json=:snapshot,updated_by=:user WHERE id=:id',['rate'=>centsToMoney($basisPoints),'snapshot'=>json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'user'=>currentUserId(),'id'=>$reservation['id']]);
        auditLog('reservation.legacy_tax_resolved','reservation',$reservation['id'],['tax_rate'=>null,'pricing_snapshot_hash'=>hash('sha256',(string)$reservation['pricing_snapshot_json'])],['tax_rate'=>centsToMoney($basisPoints),'pricing_snapshot_hash'=>hash('sha256',json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)),'reason'=>$reason],$reservation['agency_id']);return true;
    });
}

function reservationWorkspaceData($reservationId)
{
    $reservation=scopedReservationRecord($reservationId,false);if(!$reservation)throw new InvalidArgumentException(t('validation.reservation_not_found'));
    $id=$reservation['id'];$data=['reservation'=>$reservation,'snapshot'=>pricingSnapshotArray($reservation['pricing_snapshot_json'])];
    $data['customer']=dbFetchOne('SELECT * FROM customers WHERE id=:id AND agency_id=:agency',['id'=>$reservation['customer_id'],'agency'=>$reservation['agency_id']]);
    $data['vehicle']=dbFetchOne('SELECT v.*,vc.name category_name FROM vehicles v LEFT JOIN vehicle_categories vc ON vc.id=v.category_id WHERE v.id=:id AND v.agency_id=:agency',['id'=>$reservation['vehicle_id'],'agency'=>$reservation['agency_id']]);
    $data['status_history']=dbFetchAll('SELECT h.*,u.fullname changed_by_name FROM reservation_status_history h LEFT JOIN users u ON u.id=h.changed_by WHERE h.reservation_id=:id ORDER BY h.changed_at DESC,h.id DESC',['id'=>$id]);
    $data['contracts']=can('contract.view')?dbFetchAll('SELECT * FROM rental_contracts WHERE reservation_id=:id AND agency_id=:agency ORDER BY created_at DESC',['id'=>$id,'agency'=>$reservation['agency_id']]):[];
    $data['inspections']=can('inspections.manage')?dbFetchAll('SELECT vi.id,vi.inspection_type,vi.inspected_at,vi.status FROM vehicle_inspections vi JOIN rental_contracts rc ON rc.id=vi.contract_id WHERE rc.reservation_id=:id ORDER BY vi.inspected_at DESC',['id'=>$id]):[];
    $data['maintenance']=can('maintenance.manage')?dbFetchAll('SELECT id,maintenance_type,scheduled_date,status FROM maintenance_records WHERE vehicle_id=:vehicle ORDER BY COALESCE(entry_at,scheduled_date) DESC LIMIT 20',['vehicle'=>$reservation['vehicle_id']]):[];
    $data['documents']=(can('vehicles.manage')||can('vehicle_documents.manage'))?dbFetchAll('SELECT id,document_type,expires_at FROM vehicle_documents WHERE vehicle_id=:vehicle AND archived_at IS NULL ORDER BY expires_at LIMIT 20',['vehicle'=>$reservation['vehicle_id']]):[];
    $data['incidents']=can('vehicles.manage')?['fines'=>(int)dbFetchOne('SELECT COUNT(*) n FROM fines WHERE vehicle_id=:vehicle',['vehicle'=>$reservation['vehicle_id']])['n'],'accidents'=>(int)dbFetchOne('SELECT COUNT(*) n FROM accidents WHERE vehicle_id=:vehicle',['vehicle'=>$reservation['vehicle_id']])['n']]:[];
    $data['payments']=canViewFinanceHistory()?dbFetchAll('SELECT * FROM payments WHERE reservation_id=:id ORDER BY paid_at DESC',['id'=>$id]):[];
    $data['deposits']=canViewFinanceHistory()?dbFetchAll('SELECT * FROM deposits WHERE reservation_id=:id ORDER BY created_at DESC',['id'=>$id]):[];
    $data['invoices']=canViewInvoiceSections()?dbFetchAll('SELECT * FROM invoices WHERE reservation_id=:id ORDER BY issued_at DESC',['id'=>$id]):[];
    $data['requests']=dbFetchAll('SELECT * FROM customer_requests WHERE reservation_id=:id ORDER BY created_at DESC',['id'=>$id]);
    $data['audit']=dbFetchAll("SELECT a.action,a.before_json,a.after_json,a.created_at,u.fullname changed_by_name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id WHERE a.entity_type='reservation' AND a.entity_id=:id AND a.agency_id=:agency ORDER BY a.created_at DESC LIMIT 50",['id'=>(string)$id,'agency'=>$reservation['agency_id']]);
    return$data;
}

function reservationPlanningData($agencyId, DateTimeImmutable $from, DateTimeImmutable $to, array $filters = [])
{
    requireAgencyAccess($agencyId);$where=['v.agency_id=:agency','v.archived_at IS NULL'];$params=['agency'=>$agencyId];
    if(!empty($filters['vehicle_id'])){$where[]='v.id=:vehicle';$params['vehicle']=(int)$filters['vehicle_id'];}
    if(!empty($filters['category_id'])){$where[]='v.category_id=:category';$params['category']=(int)$filters['category_id'];}
    $vehicles=dbFetchAll('SELECT v.id,v.registration_number,v.brand,v.model,v.status,vc.name category_name FROM vehicles v LEFT JOIN vehicle_categories vc ON vc.id=v.category_id WHERE '.implode(' AND ',$where).' ORDER BY v.registration_number',$params);
    $vehicleIds=array_map('intval',array_column($vehicles,'id'));if(!$vehicleIds)return['vehicles'=>[],'reservations'=>[],'maintenance'=>[]];
    $ph=implode(',',array_fill(0,count($vehicleIds),'?'));
    $reservations=dbFetchAll("SELECT r.id,r.reference,r.vehicle_id,r.pickup_at,r.return_at,r.status,c.first_name,c.last_name FROM reservations r JOIN customers c ON c.id=r.customer_id WHERE r.vehicle_id IN ($ph) AND r.archived_at IS NULL AND r.status IN ('pending','confirmed','deposit_paid','ready','active') AND r.pickup_at<? AND r.return_at>? ORDER BY r.pickup_at",array_merge($vehicleIds,[$to->format('Y-m-d H:i:s'),$from->format('Y-m-d H:i:s')]));
    $maintenance=dbFetchAll("SELECT id,vehicle_id,maintenance_type,COALESCE(entry_at,CONCAT(scheduled_date,' 00:00:00')) starts_at,COALESCE(actual_exit_at,estimated_exit_at,CONCAT(scheduled_date,' 23:59:59')) ends_at,status FROM maintenance_records WHERE vehicle_id IN ($ph) AND status IN ('scheduled','in_progress') AND COALESCE(entry_at,CONCAT(scheduled_date,' 00:00:00'))<? AND COALESCE(actual_exit_at,estimated_exit_at,'9999-12-31 23:59:59')>? ORDER BY starts_at",array_merge($vehicleIds,[$to->format('Y-m-d H:i:s'),$from->format('Y-m-d H:i:s')]));
    return compact('vehicles','reservations','maintenance');
}
