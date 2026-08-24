<?php

/** Phase 5C.1 authoritative vehicle-damage lifecycle boundary. */
final class VehicleDamageReplay extends RuntimeException
{
    public function __construct(private int $result) { parent::__construct('Completed vehicle-damage command replay.'); }
    public function result(): int { return $this->result; }
}

function vehicleDamageSeverities(): array { return ['minor','moderate','major']; }

function vehicleDamageHook(string $operation,string $stage,array $context=[]): void
{
    if(PHP_SAPI==='cli'&&defined('VEHICLE_DAMAGE_TEST_HOOK')&&VEHICLE_DAMAGE_TEST_HOOK===true&&function_exists('vehicleDamageCliTestHook')){
        vehicleDamageCliTestHook($operation,$stage,$context);
    }
}

function vehicleDamagePositiveId($value,string $key): int
{
    if(is_int($value)){
        if($value<=0)throw new InvalidArgumentException(t($key));
        return$value;
    }
    if(!is_string($value)||!preg_match('/^[1-9]\d*$/',$value))throw new InvalidArgumentException(t($key));
    $maximum=(string)PHP_INT_MAX;
    if(strlen($value)>strlen($maximum)||(strlen($value)===strlen($maximum)&&strcmp($value,$maximum)>0))throw new InvalidArgumentException(t($key));
    return(int)$value;
}

function vehicleDamagePlainText($value,int $maximum,string $key): string
{
    if(!is_string($value))throw new InvalidArgumentException(t($key));
    $value=trim($value);
    if($value===''||!mb_check_encoding($value,'UTF-8')||mb_strlen($value)>$maximum||strip_tags($value)!==$value||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',$value)===1){
        throw new InvalidArgumentException(t($key));
    }
    return$value;
}

function vehicleDamageSeverity($value): string
{
    if(!is_string($value)||!in_array($value,vehicleDamageSeverities(),true))throw new InvalidArgumentException(t('validation.vehicle_damage_severity'));
    return$value;
}

function vehicleDamageScopedInspection(int $inspectionId,bool $lock=false): ?array
{
    $ids=contractScopedAgencyIds();$marks=implode(',',array_fill(0,count($ids),'?'));
    return dbFetchOne("SELECT vi.* FROM vehicle_inspections vi WHERE vi.id=? AND vi.agency_id IN ($marks)".($lock?' FOR UPDATE':''),array_merge([$inspectionId],$ids));
}

function vehicleDamageScopedRecord(int $damageId,bool $lock=false): ?array
{
    $ids=contractScopedAgencyIds();$marks=implode(',',array_fill(0,count($ids),'?'));
    return dbFetchOne(
        "SELECT vd.*,vi.agency_id,vi.reservation_id,vi.contract_id,vi.customer_id,vi.inspection_type,vi.post_return_vehicle_state,
                v.registration_number,v.status vehicle_status
         FROM vehicle_damages vd
         JOIN vehicle_inspections vi ON vi.id=vd.inspection_id AND vi.vehicle_id=vd.vehicle_id
         JOIN vehicles v ON v.id=vd.vehicle_id AND v.agency_id=vi.agency_id
         WHERE vd.id=? AND vi.agency_id IN ($marks)".($lock?' FOR UPDATE':''),
        array_merge([$damageId],$ids)
    );
}

function vehicleDamageCompleteIdempotency(int $id,int $damageId): void
{
    $changed=dbExecute(
        "UPDATE rental_operation_idempotency_keys
         SET status='completed',result_entity_type='vehicle_damage',result_entity_id=:result,completed_at=NOW(6)
         WHERE id=:id AND status='in_progress'",
        ['result'=>$damageId,'id'=>$id]
    );
    if($changed->rowCount()!==1)throw new DomainException(t('validation.vehicle_damage_stale'));
}

function vehicleDamageCreate(array $command): int
{
    contractRequireCutover();enforcePermission('vehicle_damages.create');
    $inspectionId=vehicleDamagePositiveId($command['inspection_id']??null,'validation.vehicle_damage_not_found');
    $vehicleHint=array_key_exists('vehicle_id',$command)?vehicleDamagePositiveId($command['vehicle_id'],'validation.vehicle_damage_not_found'):null;
    $zone=vehicleDamagePlainText($command['zone']??null,60,'validation.vehicle_damage_zone');
    $damageType=vehicleDamagePlainText($command['damage_type']??null,60,'validation.vehicle_damage_type');
    $description=vehicleDamagePlainText($command['description']??null,255,'validation.vehicle_damage_description');
    $severity=vehicleDamageSeverity($command['severity']??null);
    if(array_key_exists('discovered_at',$command)&&$command['discovered_at']!==null&&$command['discovered_at']!=='')throw new InvalidArgumentException(t('validation.vehicle_damage_time'));
    $visible=vehicleDamageScopedInspection($inspectionId);if(!$visible)throw new InvalidArgumentException(t('validation.vehicle_damage_not_found'));
    $agency=(int)$visible['agency_id'];$expectedVehicle=(int)$visible['vehicle_id'];$actorId=(int)currentUserId();
    try{
        return contractWithRetry(function()use($command,$inspectionId,$vehicleHint,$zone,$damageType,$description,$severity,$agency,$expectedVehicle,$actorId){
            $payload=['agency_id'=>$agency,'inspection_id'=>$inspectionId,'vehicle_id'=>$expectedVehicle,'zone'=>$zone,'damage_type'=>$damageType,'description'=>$description,'severity'=>$severity,'actor_id'=>$actorId];
            $idem=contractAcquireIdempotency($agency,'vehicle_damage_create',$command['idempotency_key']??'',$payload);
            if($idem['completed'])throw new VehicleDamageReplay($idem['result_id']);
            vehicleDamageHook('create','after_idempotency');

            /* Lock order: idempotency, inspection, vehicle, reservation, contract, source damage rows. */
            $inspection=dbFetchOne('SELECT * FROM vehicle_inspections WHERE id=:id AND agency_id=:agency FOR UPDATE',['id'=>$inspectionId,'agency'=>$agency]);
            if(!$inspection||$inspection['inspection_type']!=='return'||$inspection['status']!=='completed'||$inspection['archived_at']!==null
                ||$inspection['post_return_vehicle_state']!=='damaged'||trim((string)$inspection['damage_notes'])===''){
                throw new DomainException(t('validation.vehicle_damage_source'));
            }
            $vehicleId=(int)$inspection['vehicle_id'];
            if($vehicleId!==$expectedVehicle)throw new DomainException(t('validation.vehicle_damage_source'));
            if($vehicleHint!==null&&$vehicleHint!==$vehicleId)throw new DomainException(t('validation.vehicle_damage_source'));
            $vehicle=dbFetchOne('SELECT id,status FROM vehicles WHERE id=:id AND agency_id=:agency AND archived_at IS NULL FOR UPDATE',['id'=>$vehicleId,'agency'=>$agency]);
            if(!$vehicle||$vehicle['status']!=='damaged')throw new DomainException(t('validation.vehicle_damage_vehicle'));
            $reservation=dbFetchOne('SELECT id,vehicle_id,customer_id,status FROM reservations WHERE id=:id AND agency_id=:agency AND archived_at IS NULL FOR UPDATE',['id'=>$inspection['reservation_id'],'agency'=>$agency]);
            if(!$reservation||$reservation['status']!=='completed'||(int)$reservation['vehicle_id']!==$vehicleId||(int)$reservation['customer_id']!==(int)$inspection['customer_id'])throw new DomainException(t('validation.vehicle_damage_source'));
            $contract=dbFetchOne('SELECT id,reservation_id,status FROM rental_contracts WHERE id=:id AND agency_id=:agency FOR UPDATE',['id'=>$inspection['contract_id'],'agency'=>$agency]);
            if(!$contract||$contract['status']!=='completed'||(int)$contract['reservation_id']!==(int)$reservation['id'])throw new DomainException(t('validation.vehicle_damage_source'));
            $actor=contractAcknowledgementActor($agency);
            $existing=dbFetchOne('SELECT id FROM vehicle_damages WHERE inspection_id=:inspection ORDER BY id LIMIT 1 FOR UPDATE',['inspection'=>$inspectionId]);
            if($existing)throw new DomainException(t('validation.vehicle_damage_duplicate'));
            dbExecute(
                "INSERT INTO vehicle_damages(inspection_id,vehicle_id,zone,damage_type,description,severity,status,payment_status,created_by)
                 VALUES(:inspection,:vehicle,:zone,:damage_type,:description,:severity,'open','pending',:actor)",
                ['inspection'=>$inspectionId,'vehicle'=>$vehicleId,'zone'=>$zone,'damage_type'=>$damageType,'description'=>$description,'severity'=>$severity,'actor'=>$actor['id']]
            );
            $damageId=(int)db()->lastInsertId();vehicleDamageHook('create','after_insert',['damage_id'=>$damageId]);
            vehicleDamageHook('create','before_audit',['damage_id'=>$damageId]);
            auditLog('vehicle_damage.created','vehicle_damage',$damageId,null,['inspection_id'=>$inspectionId,'vehicle_id'=>$vehicleId,'reservation_id'=>(int)$reservation['id'],'contract_id'=>(int)$contract['id'],'zone'=>$zone,'damage_type'=>$damageType,'description'=>$description,'severity'=>$severity,'status'=>'open'],$agency);
            vehicleDamageHook('create','after_audit',['damage_id'=>$damageId]);
            vehicleDamageHook('create','before_idempotency_completion',['damage_id'=>$damageId]);
            vehicleDamageCompleteIdempotency((int)$idem['id'],$damageId);
            vehicleDamageHook('create','before_commit',['damage_id'=>$damageId]);
            return$damageId;
        });
    }catch(VehicleDamageReplay$replay){return$replay->result();}
}

function vehicleDamageResolve(array $command): int
{
    contractRequireCutover();enforcePermission('vehicle_damages.resolve');
    $damageId=vehicleDamagePositiveId($command['damage_id']??null,'validation.vehicle_damage_not_found');
    $reason=vehicleDamagePlainText($command['reason']??null,255,'validation.vehicle_damage_resolution');
    $visible=vehicleDamageScopedRecord($damageId);if(!$visible)throw new InvalidArgumentException(t('validation.vehicle_damage_not_found'));
    $agency=(int)$visible['agency_id'];$actorId=(int)currentUserId();
    try{
        return contractWithRetry(function()use($command,$damageId,$reason,$visible,$agency,$actorId){
            $payload=['agency_id'=>$agency,'damage_id'=>$damageId,'reason'=>$reason,'actor_id'=>$actorId];
            $idem=contractAcquireIdempotency($agency,'vehicle_damage_resolve',$command['idempotency_key']??'',$payload);
            if($idem['completed'])throw new VehicleDamageReplay($idem['result_id']);
            vehicleDamageHook('resolve','after_idempotency',['damage_id'=>$damageId]);
            /* Resolution follows the same source lock order as creation. */
            $inspection=dbFetchOne('SELECT id,vehicle_id FROM vehicle_inspections WHERE id=:id AND agency_id=:agency FOR UPDATE',['id'=>$visible['inspection_id'],'agency'=>$agency]);
            $vehicle=$inspection?dbFetchOne('SELECT id,status FROM vehicles WHERE id=:id AND agency_id=:agency FOR UPDATE',['id'=>$inspection['vehicle_id'],'agency'=>$agency]):null;
            $damage=$vehicle?dbFetchOne('SELECT * FROM vehicle_damages WHERE id=:id AND inspection_id=:inspection AND vehicle_id=:vehicle FOR UPDATE',['id'=>$damageId,'inspection'=>$inspection['id'],'vehicle'=>$vehicle['id']]):null;
            if(!$damage)throw new InvalidArgumentException(t('validation.vehicle_damage_not_found'));
            if($damage['status']!=='open'||$damage['resolved_at']!==null)throw new DomainException(t('validation.vehicle_damage_stale'));
            $actor=contractAcknowledgementActor($agency);
            $changed=dbExecute("UPDATE vehicle_damages SET status='resolved',resolved_at=NOW() WHERE id=:id AND status='open' AND resolved_at IS NULL",['id'=>$damageId]);
            if($changed->rowCount()!==1)throw new DomainException(t('validation.vehicle_damage_stale'));
            vehicleDamageHook('resolve','after_update',['damage_id'=>$damageId]);
            vehicleDamageHook('resolve','before_audit',['damage_id'=>$damageId]);
            auditLog('vehicle_damage.resolved','vehicle_damage',$damageId,['status'=>'open','resolved_at'=>null],['status'=>'resolved','resolution_reason'=>$reason,'vehicle_status'=>$vehicle['status'],'resolved_by'=>(int)$actor['id']],$agency);
            vehicleDamageHook('resolve','after_audit',['damage_id'=>$damageId]);
            vehicleDamageHook('resolve','before_idempotency_completion',['damage_id'=>$damageId]);
            vehicleDamageCompleteIdempotency((int)$idem['id'],$damageId);
            vehicleDamageHook('resolve','before_commit',['damage_id'=>$damageId]);
            return$damageId;
        });
    }catch(VehicleDamageReplay$replay){return$replay->result();}
}

function vehicleDamageScopedList(?int $vehicleId=null): array
{
    enforcePermission('vehicle_damages.view');$ids=contractScopedAgencyIds();$marks=implode(',',array_fill(0,count($ids),'?'));
    $sql="SELECT vd.*,vi.agency_id,vi.reservation_id,vi.contract_id,r.reference reservation_reference,rc.contract_number,
                 v.registration_number,v.brand,v.model
          FROM vehicle_damages vd
          JOIN vehicle_inspections vi ON vi.id=vd.inspection_id AND vi.vehicle_id=vd.vehicle_id
          JOIN vehicles v ON v.id=vd.vehicle_id AND v.agency_id=vi.agency_id
          JOIN reservations r ON r.id=vi.reservation_id AND r.agency_id=vi.agency_id
          JOIN rental_contracts rc ON rc.id=vi.contract_id AND rc.agency_id=vi.agency_id AND rc.reservation_id=r.id
          WHERE vi.agency_id IN ($marks)";$params=$ids;
    if($vehicleId!==null){$sql.=' AND vd.vehicle_id=?';$params[]=$vehicleId;}
    return dbFetchAll($sql.' ORDER BY vd.created_at DESC,vd.id DESC LIMIT 200',$params);
}

function vehicleDamageEligibleSources(?int $vehicleId=null): array
{
    enforcePermission('vehicle_damages.create');$ids=contractScopedAgencyIds();$marks=implode(',',array_fill(0,count($ids),'?'));
    $sql="SELECT vi.id,vi.vehicle_id,vi.damage_notes,vi.completed_at,v.registration_number,r.reference reservation_reference,rc.contract_number
          FROM vehicle_inspections vi
          JOIN vehicles v ON v.id=vi.vehicle_id AND v.agency_id=vi.agency_id AND v.archived_at IS NULL AND v.status='damaged'
          JOIN reservations r ON r.id=vi.reservation_id AND r.agency_id=vi.agency_id AND r.vehicle_id=vi.vehicle_id AND r.customer_id=vi.customer_id AND r.status='completed' AND r.archived_at IS NULL
          JOIN rental_contracts rc ON rc.id=vi.contract_id AND rc.agency_id=vi.agency_id AND rc.reservation_id=r.id AND rc.status='completed'
          LEFT JOIN vehicle_damages vd ON vd.inspection_id=vi.id
          WHERE vi.agency_id IN ($marks) AND vi.inspection_type='return' AND vi.status='completed' AND vi.archived_at IS NULL
            AND vi.post_return_vehicle_state='damaged' AND CHAR_LENGTH(TRIM(COALESCE(vi.damage_notes,'')))>0 AND vd.id IS NULL";$params=$ids;
    if($vehicleId!==null){$sql.=' AND vi.vehicle_id=?';$params[]=$vehicleId;}
    return dbFetchAll($sql.' ORDER BY vi.completed_at DESC,vi.id DESC LIMIT 200',$params);
}
