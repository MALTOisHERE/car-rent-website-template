<?php

/** Phase 5B.1 authoritative contract lifecycle boundary. */

final class ContractIdempotentReplay extends RuntimeException
{
    private int $contractResult;

    public function __construct(int $result)
    {
        parent::__construct('Completed contract command replay.');
        $this->contractResult=$result;
    }

    public function result(): int
    {
        return $this->contractResult;
    }
}

function contractLifecycleStatuses(): array
{
    return ['draft','issued','signed','active','completed','cancelled'];
}

function contractRequireCutover(): void
{
    if(!tableExists('contract_status_history')||!tableExists('rental_operation_idempotency_keys')){
        throw new DomainException(t('validation.contract_read_only'));
    }
    if(!dbFetchOne("SELECT version FROM schema_migrations WHERE version='007_contracts_rental_operations'")){
        throw new DomainException(t('validation.contract_read_only'));
    }
}

function contractIdempotencyToken(): string
{
    return bin2hex(random_bytes(32));
}

function contractIdempotencyField(string $operation): string
{
    return '<input type="hidden" name="idempotency_key" value="'.e(contractIdempotencyToken()).'"><input type="hidden" name="operation_type" value="'.e($operation).'">';
}

function contractCanonicalPayload(array $payload): string
{
    $sort=function(&$value)use(&$sort):void{
        if(!is_array($value))return;
        foreach($value as&$child)$sort($child);
        if(!array_is_list($value))ksort($value,SORT_STRING);
    };
    $sort($payload);
    return hash('sha256',(string)json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION));
}

function contractAcquireIdempotency(int $agencyId,string $operation,$rawKey,array $payload): array
{
    $rawKey=strtolower(trim((string)$rawKey));
    if(!preg_match('/^[a-f0-9]{64}$/',$rawKey))throw new InvalidArgumentException(t('validation.contract_idempotency'));
    $keyHash=hash('sha256',$rawKey);$payloadHash=contractCanonicalPayload($payload);$actor=(int)currentUserId();
    dbExecute(
        "INSERT IGNORE INTO rental_operation_idempotency_keys(origin_agency_id,operation_type,key_hash,payload_hash,status,created_by)
         VALUES(:agency,:operation,:key_hash,:payload_hash,'in_progress',:actor)",
        ['agency'=>$agencyId,'operation'=>$operation,'key_hash'=>$keyHash,'payload_hash'=>$payloadHash,'actor'=>$actor]
    );
    $row=dbFetchOne(
        'SELECT * FROM rental_operation_idempotency_keys
         WHERE origin_agency_id=:agency AND operation_type=:operation AND key_hash=:key_hash FOR UPDATE',
        ['agency'=>$agencyId,'operation'=>$operation,'key_hash'=>$keyHash]
    );
    if(!$row||(int)$row['created_by']!==$actor||!hash_equals((string)$row['payload_hash'],$payloadHash)){
        throw new DomainException(t('validation.contract_idempotency_conflict'));
    }
    return ['id'=>(int)$row['id'],'completed'=>$row['status']==='completed','result_id'=>(int)($row['result_entity_id']??0)];
}

function contractCompleteIdempotency(int $id,int $contractId): void
{
    $updated=dbExecute(
        "UPDATE rental_operation_idempotency_keys
         SET status='completed',result_entity_type='contract',result_entity_id=:contract,completed_at=NOW(6)
         WHERE id=:id AND status='in_progress'",
        ['contract'=>$contractId,'id'=>$id]
    );
    if($updated->rowCount()!==1)throw new DomainException(t('validation.contract_stale'));
}

function contractIsRetryable(Throwable $exception): bool
{
    return $exception instanceof PDOException
        && ((string)$exception->getCode()==='40001'||in_array((int)($exception->errorInfo[1]??0),[1205,1213],true));
}

function contractWithRetry(callable $callback)
{
    for($attempt=1;$attempt<=3;$attempt++){
        try{return withTransaction($callback);}
        catch(Throwable$exception){
            if(!contractIsRetryable($exception)||$attempt===3)throw$exception;
            usleep(random_int(20000*$attempt,70000*$attempt));
        }
    }
    throw new RuntimeException('Contract transaction retry exhausted.');
}

function contractScopedAgencyIds(): array
{
    $ids=currentUserRole()===ROLE_OWNER
        ?array_map('intval',array_column(dbFetchAll('SELECT id FROM agencies'),'id'))
        :currentAgencyIds();
    return $ids?:[0];
}

function contractScopedReservation(int $reservationId,bool $lock=false): ?array
{
    $ids=contractScopedAgencyIds();$ph=implode(',',array_fill(0,count($ids),'?'));
    return dbFetchOne(
        "SELECT r.*,c.first_name,c.last_name,c.identity_number,c.licence_number,
                v.registration_number,v.vin,v.brand,v.model,a.name agency_name
         FROM reservations r
         JOIN customers c ON c.id=r.customer_id AND c.agency_id=r.agency_id
         LEFT JOIN vehicles v ON v.id=r.vehicle_id AND v.agency_id=r.agency_id
         JOIN agencies a ON a.id=r.agency_id
         WHERE r.id=? AND r.agency_id IN ($ph) AND r.archived_at IS NULL".($lock?' FOR UPDATE':''),
        array_merge([$reservationId],$ids)
    );
}

function contractScopedRecord(int $contractId,bool $lock=false): ?array
{
    $ids=contractScopedAgencyIds();$ph=implode(',',array_fill(0,count($ids),'?'));
    return dbFetchOne(
        "SELECT rc.* FROM rental_contracts rc
         WHERE rc.id=? AND rc.agency_id IN ($ph)".($lock?' FOR UPDATE':''),
        array_merge([$contractId],$ids)
    );
}

function contractSnapshot(array $reservation,string $language): array
{
    $pricing=json_decode((string)$reservation['pricing_snapshot_json'],true);
    return [
        'schema_version'=>1,
        'language_code'=>$language,
        'reservation_reference'=>$reservation['reference'],
        'agency'=>['id'=>(int)$reservation['agency_id'],'name'=>$reservation['agency_name']],
        'customer'=>[
            'name'=>trim($reservation['first_name'].' '.$reservation['last_name']),
            'identity_number'=>$reservation['identity_number'],
            'licence_number'=>$reservation['licence_number'],
        ],
        'vehicle'=>[
            'registration_number'=>$reservation['registration_number'],
            'vin'=>$reservation['vin'],
            'description'=>trim((string)$reservation['brand'].' '.(string)$reservation['model']),
        ],
        'period'=>['pickup_at'=>$reservation['pickup_at'],'return_at'=>$reservation['return_at']],
        'pricing'=>is_array($pricing)?$pricing:[],
        'deposit_amount'=>$reservation['deposit_amount'],
        'currency'=>$reservation['currency'],
    ];
}

function contractApprovedTerms(): string
{
    return 'The renter accepts the agreed rental period, vehicle condition, mileage, fuel, payment, deposit, and liability terms recorded in this contract.';
}

function contractCreateFromReservation($input): int
{
    contractRequireCutover();enforcePermission('contract.create');
    if(!is_array($input))$input=['reservation_id'=>(int)$input];
    $reservationId=(int)($input['reservation_id']??0);
    $reservation=contractScopedReservation($reservationId,false);
    if(!$reservation)throw new InvalidArgumentException(t('validation.contract_reservation_not_found'));
    $agencyId=(int)$reservation['agency_id'];$key=$input['idempotency_key']??'';
    try{
        return contractWithRetry(function()use($agencyId,$reservationId,$key){
            $idem=contractAcquireIdempotency($agencyId,'contract.create',$key,['reservation_id'=>$reservationId]);
            if($idem['completed'])throw new ContractIdempotentReplay($idem['result_id']);
            $reservation=contractScopedReservation($reservationId,true);
            if(!$reservation||!in_array($reservation['status'],['confirmed','deposit_paid','ready'],true)||empty($reservation['vehicle_id'])){
                throw new DomainException(t('validation.contract_reservation_ineligible'));
            }
            $existing=dbFetchOne(
                "SELECT id FROM rental_contracts
                 WHERE reservation_id=:reservation AND status IN('draft','issued','signed','active') LIMIT 1 FOR UPDATE",
                ['reservation'=>$reservationId]
            );
            if($existing)throw new DomainException(t('validation.contract_live_exists'));
            $number=generateBusinessReference('CTR');
            dbExecute(
                "INSERT INTO rental_contracts(agency_id,reservation_id,contract_number,status,current_version,created_by,updated_by)
                 VALUES(:agency,:reservation,:number,'draft',0,:actor,:actor2)",
                ['agency'=>$agencyId,'reservation'=>$reservationId,'number'=>$number,'actor'=>currentUserId(),'actor2'=>currentUserId()]
            );
            $contractId=(int)db()->lastInsertId();
            dbExecute(
                "INSERT INTO contract_status_history(agency_id,contract_id,reservation_id,from_status,to_status,changed_by,occurred_at,metadata_json)
                 VALUES(:agency,:contract,:reservation,NULL,'draft',:actor,NOW(6),:metadata)",
                ['agency'=>$agencyId,'contract'=>$contractId,'reservation'=>$reservationId,'actor'=>currentUserId(),'metadata'=>json_encode(['source'=>'contract.create'],JSON_UNESCAPED_SLASHES)]
            );
            auditLog('contract.created','contract',$contractId,null,['status'=>'draft','contract_number'=>$number,'reservation_id'=>$reservationId],$agencyId);
            contractCompleteIdempotency($idem['id'],$contractId);
            return $contractId;
        });
    }catch(ContractIdempotentReplay$replay){return$replay->result();}
}

function contractIssue($input): int
{
    contractRequireCutover();enforcePermission('contract.issue');
    if(!is_array($input))$input=['contract_id'=>(int)$input];
    $contractId=(int)($input['contract_id']??0);$unlocked=contractScopedRecord($contractId,false);
    if(!$unlocked)throw new InvalidArgumentException(t('validation.contract_not_found'));
    $agencyId=(int)$unlocked['agency_id'];$key=$input['idempotency_key']??'';
    try{
        return contractWithRetry(function()use($agencyId,$contractId,$key){
            $idem=contractAcquireIdempotency($agencyId,'contract.issue',$key,['contract_id'=>$contractId]);
            if($idem['completed'])throw new ContractIdempotentReplay($idem['result_id']);
            $candidate=contractScopedRecord($contractId,false);
            if(!$candidate)throw new InvalidArgumentException(t('validation.contract_not_found'));
            $reservation=contractScopedReservation((int)$candidate['reservation_id'],true);
            $contract=contractScopedRecord($contractId,true);
            if(!$reservation||!$contract)throw new InvalidArgumentException(t('validation.contract_not_found'));
            if($contract['status']!=='draft')throw new DomainException(t('validation.contract_issue_state'));
            if((int)$contract['current_version']!==0||$contract['current_version_id']!==null
                ||dbFetchOne('SELECT id FROM contract_versions WHERE contract_id=:id LIMIT 1 FOR UPDATE',['id'=>$contractId])){
                throw new DomainException(t('validation.contract_version_state'));
            }
            $terms=contractApprovedTerms();$versionIds=[];
            foreach(['en','fr','ar']as$language){
                $snapshot=contractSnapshot($reservation,$language);
                $json=json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
                dbExecute(
                    "INSERT INTO contract_versions(agency_id,contract_id,version_number,language_code,predecessor_version_id,snapshot_json,snapshot_sha256,terms_text,change_reason,created_by)
                     VALUES(:agency,:contract,1,:language,NULL,:snapshot,:digest,:terms,:reason,:actor)",
                    ['agency'=>$agencyId,'contract'=>$contractId,'language'=>$language,'snapshot'=>$json,'digest'=>hash('sha256',$json),'terms'=>$terms,'reason'=>t('contract.initial_issue_reason'),'actor'=>currentUserId()]
                );
                $versionIds[$language]=(int)db()->lastInsertId();
            }
            $changed=dbExecute(
                "UPDATE rental_contracts
                 SET status='issued',current_version=1,current_version_id=:version,issued_at=NOW(6),updated_by=:actor
                 WHERE id=:id AND agency_id=:agency AND status='draft'",
                ['version'=>$versionIds['en'],'actor'=>currentUserId(),'id'=>$contractId,'agency'=>$agencyId]
            );
            if($changed->rowCount()!==1)throw new DomainException(t('validation.contract_stale'));
            dbExecute(
                "INSERT INTO contract_status_history(agency_id,contract_id,reservation_id,from_status,to_status,changed_by,occurred_at,metadata_json)
                 VALUES(:agency,:contract,:reservation,'draft','issued',:actor,NOW(6),:metadata)",
                ['agency'=>$agencyId,'contract'=>$contractId,'reservation'=>$contract['reservation_id'],'actor'=>currentUserId(),'metadata'=>json_encode(['version_number'=>1,'languages'=>['en','fr','ar']],JSON_UNESCAPED_SLASHES)]
            );
            auditLog('contract.issued','contract',$contractId,['status'=>'draft','current_version'=>0],['status'=>'issued','current_version'=>1,'current_version_id'=>$versionIds['en'],'languages'=>['en','fr','ar']],$agencyId);
            contractCompleteIdempotency($idem['id'],$contractId);
            return$contractId;
        });
    }catch(ContractIdempotentReplay$replay){return$replay->result();}
}

function contractCancel($input): int
{
    contractRequireCutover();enforcePermission('contract.cancel');
    if(!is_array($input))throw new InvalidArgumentException(t('validation.contract_cancel_reason'));
    $contractId=(int)($input['contract_id']??0);$reason=trim((string)($input['reason']??''));
    if($reason===''||mb_strlen($reason)>255)throw new InvalidArgumentException(t('validation.contract_cancel_reason'));
    $unlocked=contractScopedRecord($contractId,false);
    if(!$unlocked)throw new InvalidArgumentException(t('validation.contract_not_found'));
    $agencyId=(int)$unlocked['agency_id'];$key=$input['idempotency_key']??'';
    try{
        return contractWithRetry(function()use($agencyId,$contractId,$reason,$key){
            $idem=contractAcquireIdempotency($agencyId,'contract.cancel',$key,['contract_id'=>$contractId,'reason'=>$reason]);
            if($idem['completed'])throw new ContractIdempotentReplay($idem['result_id']);
            $candidate=contractScopedRecord($contractId,false);
            if(!$candidate)throw new InvalidArgumentException(t('validation.contract_not_found'));
            $reservation=contractScopedReservation((int)$candidate['reservation_id'],true);
            $contract=contractScopedRecord($contractId,true);
            if(!$reservation||!$contract)throw new InvalidArgumentException(t('validation.contract_not_found'));
            if(!in_array($contract['status'],['draft','issued'],true))throw new DomainException(t('validation.contract_cancel_state'));
            $changed=dbExecute(
                "UPDATE rental_contracts
                 SET status='cancelled',cancelled_at=NOW(6),cancelled_by=:actor,cancellation_reason=:reason,updated_by=:actor2
                 WHERE id=:id AND agency_id=:agency AND status=:from_status",
                ['actor'=>currentUserId(),'reason'=>$reason,'actor2'=>currentUserId(),'id'=>$contractId,'agency'=>$agencyId,'from_status'=>$contract['status']]
            );
            if($changed->rowCount()!==1)throw new DomainException(t('validation.contract_stale'));
            dbExecute(
                "INSERT INTO contract_status_history(agency_id,contract_id,reservation_id,from_status,to_status,reason,changed_by,occurred_at,metadata_json)
                 VALUES(:agency,:contract,:reservation,:from_status,'cancelled',:reason,:actor,NOW(6),:metadata)",
                ['agency'=>$agencyId,'contract'=>$contractId,'reservation'=>$contract['reservation_id'],'from_status'=>$contract['status'],'reason'=>$reason,'actor'=>currentUserId(),'metadata'=>json_encode(['source'=>'contract.cancel'],JSON_UNESCAPED_SLASHES)]
            );
            auditLog('contract.cancelled','contract',$contractId,['status'=>$contract['status']],['status'=>'cancelled','reason'=>$reason],$agencyId);
            contractCompleteIdempotency($idem['id'],$contractId);
            return$contractId;
        });
    }catch(ContractIdempotentReplay$replay){return$replay->result();}
}

function contractScopedList(array $filters=[]): array
{
    contractRequireCutover();enforcePermission('contract.view');
    $ids=contractScopedAgencyIds();$ph=implode(',',array_fill(0,count($ids),'?'));$where=["rc.agency_id IN ($ph)"];$params=$ids;
    $status=trim((string)($filters['status']??''));if(in_array($status,contractLifecycleStatuses(),true)){$where[]='rc.status=?';$params[]=$status;}
    $reservationId=(int)($filters['reservation_id']??0);if($reservationId>0){$where[]='rc.reservation_id=?';$params[]=$reservationId;}
    return dbFetchAll(
        "SELECT rc.*,r.reference,c.first_name,c.last_name,v.registration_number,a.name agency_name
         FROM rental_contracts rc
         JOIN reservations r ON r.id=rc.reservation_id AND r.agency_id=rc.agency_id
         JOIN customers c ON c.id=r.customer_id AND c.agency_id=rc.agency_id
         LEFT JOIN vehicles v ON v.id=r.vehicle_id AND v.agency_id=rc.agency_id
         JOIN agencies a ON a.id=rc.agency_id
         WHERE ".implode(' AND ',$where).' ORDER BY rc.created_at DESC,rc.id DESC LIMIT 200',
        $params
    );
}

function contractScopedDetail(int $contractId): array
{
    contractRequireCutover();enforcePermission('contract.view');
    $ids=contractScopedAgencyIds();$ph=implode(',',array_fill(0,count($ids),'?'));
    $contract=dbFetchOne(
        "SELECT rc.*,r.reference,r.pickup_at,r.return_at,r.currency,r.total_amount,r.deposit_amount,
                c.first_name,c.last_name,c.email,c.identity_number,c.licence_number,
                v.registration_number,v.vin,v.brand,v.model,a.name agency_name,
                cv.language_code current_version_language,cv.snapshot_sha256 current_version_sha256
         FROM rental_contracts rc
         JOIN reservations r ON r.id=rc.reservation_id AND r.agency_id=rc.agency_id
         JOIN customers c ON c.id=r.customer_id AND c.agency_id=rc.agency_id
         LEFT JOIN vehicles v ON v.id=r.vehicle_id AND v.agency_id=rc.agency_id
         JOIN agencies a ON a.id=rc.agency_id
         LEFT JOIN contract_versions cv ON cv.id=rc.current_version_id AND cv.agency_id=rc.agency_id
         WHERE rc.id=? AND rc.agency_id IN ($ph)",
        array_merge([$contractId],$ids)
    );
    if(!$contract)throw new InvalidArgumentException(t('validation.contract_not_found'));
    return[
        'contract'=>$contract,
        'versions'=>dbFetchAll('SELECT id,version_number,language_code,snapshot_sha256,created_at FROM contract_versions WHERE contract_id=:contract AND agency_id=:agency ORDER BY version_number,language_code',['contract'=>$contractId,'agency'=>$contract['agency_id']]),
        'history'=>contractStatusHistory($contractId),
    ];
}

function contractStatusHistory(int $contractId): array
{
    enforcePermission('contract.view');
    $contract=contractScopedRecord($contractId,false);
    if(!$contract)throw new InvalidArgumentException(t('validation.contract_not_found'));
    return dbFetchAll(
        'SELECT h.*,u.fullname changed_by_name FROM contract_status_history h
         LEFT JOIN users u ON u.id=h.changed_by
         WHERE h.contract_id=:contract AND h.agency_id=:agency ORDER BY h.occurred_at,h.id',
        ['contract'=>$contractId,'agency'=>$contract['agency_id']]
    );
}
