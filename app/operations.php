<?php

function createContractFromReservation($reservationId, $languageCode = 'fr')
{
    return withTransaction(function () use ($reservationId, $languageCode) {
        $reservation = dbFetchOne('SELECT r.*,c.first_name,c.last_name,c.identity_number,c.licence_number,v.registration_number,v.vin,v.brand,v.model FROM reservations r JOIN customers c ON c.id=r.customer_id LEFT JOIN vehicles v ON v.id=r.vehicle_id WHERE r.id=:id FOR UPDATE', ['id'=>$reservationId]);
        if(!$reservation||!in_array($reservation['status'],['confirmed','deposit_paid','ready'],true))throw new DomainException('Only a confirmed or ready reservation can generate a contract.');requireAgencyAccess($reservation['agency_id']);
        $existing=dbFetchOne('SELECT id FROM rental_contracts WHERE reservation_id=:id AND status<>\'cancelled\' LIMIT 1',['id'=>$reservationId]);if($existing)return(int)$existing['id'];
        $number=generateBusinessReference('CTR');$snapshot=['reservation_reference'=>$reservation['reference'],'customer'=>['name'=>$reservation['first_name'].' '.$reservation['last_name'],'identity_number'=>$reservation['identity_number'],'licence_number'=>$reservation['licence_number']],'vehicle'=>['registration_number'=>$reservation['registration_number'],'vin'=>$reservation['vin'],'description'=>$reservation['brand'].' '.$reservation['model']],'period'=>['pickup_at'=>$reservation['pickup_at'],'return_at'=>$reservation['return_at']],'pricing'=>json_decode($reservation['pricing_snapshot_json'],true),'deposit_amount'=>$reservation['deposit_amount'],'currency'=>$reservation['currency']];
        dbExecute('INSERT INTO rental_contracts(reservation_id,contract_number,status,current_version,created_by,updated_by)VALUES(:reservation,:number,\'generated\',1,:user,:user)',['reservation'=>$reservationId,'number'=>$number,'user'=>currentUserId()]);$id=(int)db()->lastInsertId();
        dbExecute('INSERT INTO contract_versions(contract_id,version_number,language_code,snapshot_json,terms_text,change_reason,created_by)VALUES(:contract,1,:language,:snapshot,:terms,\'Initial contract\',:user)',['contract'=>$id,'language'=>validateChoice($languageCode,supportedLanguages(),'fr'),'snapshot'=>json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'terms'=>'The renter accepts the agreed rental period, vehicle condition, mileage, fuel, payment, deposit, and liability terms recorded in this contract.','user'=>currentUserId()]);auditLog('contract.generated','contract',$id,null,['contract_number'=>$number],$reservation['agency_id']);return$id;
    });
}

function amendContract($contractId, $reason, $languageCode = 'fr')
{
    return withTransaction(function()use($contractId,$reason,$languageCode){$contract=dbFetchOne('SELECT rc.*,r.agency_id FROM rental_contracts rc JOIN reservations r ON r.id=rc.reservation_id WHERE rc.id=:id FOR UPDATE',['id'=>$contractId]);if(!$contract)throw new InvalidArgumentException('Contract not found.');requireAgencyAccess($contract['agency_id']);if(trim($reason)==='')throw new InvalidArgumentException('An amendment reason is required.');$previous=dbFetchOne('SELECT snapshot_json,terms_text FROM contract_versions WHERE contract_id=:id ORDER BY version_number DESC LIMIT 1',['id'=>$contractId]);$version=(int)$contract['current_version']+1;dbExecute('INSERT INTO contract_versions(contract_id,version_number,language_code,snapshot_json,terms_text,change_reason,created_by)VALUES(:contract,:version,:language,:snapshot,:terms,:reason,:user)',['contract'=>$contractId,'version'=>$version,'language'=>validateChoice($languageCode,supportedLanguages(),'fr'),'snapshot'=>$previous['snapshot_json'],'terms'=>$previous['terms_text'],'reason'=>trim($reason),'user'=>currentUserId()]);dbExecute('UPDATE rental_contracts SET current_version=:version,status=\'amended\',updated_by=:user WHERE id=:id',['version'=>$version,'user'=>currentUserId(),'id'=>$contractId]);auditLog('contract.amended','contract',$contractId,['version'=>$contract['current_version']],['version'=>$version,'reason'=>$reason],$contract['agency_id']);return$version;});
}

function recordPayment(array $input)
{
    return recordSettledPayment($input);
}

function createInvoiceFromReservation($reservationId, $idempotencyKey = null)
{
    return createAndIssueInvoiceFromReservation([
        'reservation_id'=>(int)$reservationId,
        'language_code'=>language(),
        'idempotency_key'=>$idempotencyKey ?: financeIdempotencyToken(),
    ]);
}

function updateDepositStatus($depositId,$newStatus,$retainedAmount='0.00',$reason=null)
{
    $deposit=dbFetchOne('SELECT * FROM deposits WHERE id=:id',['id'=>(int)$depositId]);if(!$deposit)throw new InvalidArgumentException(t('validation.finance_not_found'));
    $eventMap=['received'=>'received','held'=>'held','partially_retained'=>'partially_retained','fully_retained'=>'fully_retained','returned'=>'returned'];$event=$eventMap[$newStatus]??null;if(!$event)throw new DomainException(t('validation.deposit_event'));
    $amount=$event==='held'?'0.00':($event==='received'?centsToMoney(max(0,(moneyToCents($deposit['amount'])??0)-(moneyToCents($deposit['received_amount'])??0))):($event==='returned'?centsToMoney(max(0,(moneyToCents($deposit['received_amount'])??0)-(moneyToCents($deposit['retained_amount'])??0)-(moneyToCents($deposit['returned_amount'])??0))):centsToMoney(max(0,(moneyToCents($retainedAmount)??0)-(moneyToCents($deposit['retained_amount'])??0)))));
    return appendDepositEvent(['deposit_id'=>(int)$depositId,'event_type'=>$event,'amount'=>$amount,'method'=>'cash','reason'=>$reason,'idempotency_key'=>financeIdempotencyToken()]);
}

function createInspection(array $input)
{
    $inspectedAt=validDateTimeValue($input['inspected_at']??'');
    if(!$inspectedAt)throw new InvalidArgumentException('A valid inspection date and time are required.');
    $input['inspected_at']=$inspectedAt->format('Y-m-d H:i:s');
    return withTransaction(function()use($input){$contract=dbFetchOne('SELECT rc.*,r.agency_id,r.customer_id,r.vehicle_id FROM rental_contracts rc JOIN reservations r ON r.id=rc.reservation_id WHERE rc.id=:id FOR UPDATE',['id'=>(int)$input['contract_id']]);if(!$contract||!in_array($contract['status'],['generated','signed','active','amended'],true))throw new DomainException('Select an eligible contract.');requireAgencyAccess($contract['agency_id']);$type=validateChoice($input['inspection_type']??'',['checkout','return'],null);if(!$type)throw new InvalidArgumentException('Inspection type is invalid.');$fuel=(float)($input['fuel_level']??-1);if($fuel<0||$fuel>100)throw new InvalidArgumentException('Fuel level must be between 0 and 100 percent.');dbExecute('INSERT INTO vehicle_inspections(contract_id,vehicle_id,customer_id,inspection_type,inspected_at,mileage,fuel_level,cleanliness,accessories_json,notes,customer_signature_status,employee_signature_status,status,created_by)VALUES(:contract,:vehicle,:customer,:type,:at,:mileage,:fuel,:cleanliness,:accessories,:notes,:customer_signature,:employee_signature,\'validated\',:user)',['contract'=>$contract['id'],'vehicle'=>$contract['vehicle_id'],'customer'=>$contract['customer_id'],'type'=>$type,'at'=>$input['inspected_at'],'mileage'=>max(0,(int)$input['mileage']),'fuel'=>$fuel,'cleanliness'=>validateChoice($input['cleanliness']??'',['clean','acceptable','dirty'],'acceptable'),'accessories'=>json_encode(array_values(array_filter(array_map('trim',explode(',',(string)($input['accessories']??''))))),JSON_UNESCAPED_UNICODE),'notes'=>trim((string)($input['notes']??'')),'customer_signature'=>validateChoice($input['customer_signature_status']??'',['pending','signed','refused'],'pending'),'employee_signature'=>'signed','user'=>currentUserId()]);$id=(int)db()->lastInsertId();dbExecute('UPDATE vehicles SET current_mileage=GREATEST(current_mileage,:mileage),status=:status,updated_by=:user WHERE id=:vehicle',['mileage'=>max(0,(int)$input['mileage']),'status'=>$type==='checkout'?'rented':'cleaning','user'=>currentUserId(),'vehicle'=>$contract['vehicle_id']]);if($type==='checkout')dbExecute("UPDATE rental_contracts SET status='active',activated_at=NOW(),updated_by=:user WHERE id=:id",['user'=>currentUserId(),'id'=>$contract['id']]);auditLog('inspection.validated','inspection',$id,null,['type'=>$type,'mileage'=>$input['mileage'],'fuel'=>$fuel],$contract['agency_id']);return$id;});
}
