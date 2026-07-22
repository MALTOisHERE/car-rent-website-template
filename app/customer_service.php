<?php

function approvedCustomerStatuses()
{
    return ['new','regular','VIP','watchlist','blocked','archived'];
}

function operationalCustomerStatuses()
{
    return ['new','regular','VIP','watchlist'];
}

function customerLifecycleCommands()
{
    return [
        'mark_new'=>['status'=>'new','action'=>'status_changed','sensitive'=>false],
        'mark_regular'=>['status'=>'regular','action'=>'status_changed','sensitive'=>false],
        'mark_vip'=>['status'=>'VIP','action'=>'status_changed','sensitive'=>false],
        'mark_watchlist'=>['status'=>'watchlist','action'=>'status_changed','sensitive'=>false],
        'block'=>['status'=>'blocked','action'=>'blocked','sensitive'=>true],
        'unblock'=>['status'=>null,'action'=>'unblocked','sensitive'=>true],
        'archive'=>['status'=>'archived','action'=>'archived','sensitive'=>true],
    ];
}

function scopedCustomerRecord($customerId, $includeArchived = false, $lock = false)
{
    $ids = currentAgencyIds();
    if (!$ids) $ids=[0];
    $ph=implode(',',array_fill(0,count($ids),'?'));
    $params=array_merge([(int)$customerId],$ids);
    $sql="SELECT * FROM customers WHERE id=? AND agency_id IN ($ph)";
    if(!$includeArchived)$sql.=' AND archived_at IS NULL';
    if($lock)$sql.=' FOR UPDATE';
    return dbFetchOne($sql,$params);
}

function validatedCustomerProfile(array $input, array $existing = null)
{
    $first=trim((string)($input['first_name']??''));
    $last=trim((string)($input['last_name']??''));
    if($first===''||$last==='')throw new InvalidArgumentException(t('validation.customer_name'));
    if(mb_strlen($first)>100||mb_strlen($last)>100)throw new InvalidArgumentException(t('validation.customer_name'));
    $birthRaw=trim((string)($input['date_of_birth']??''));$birth=$birthRaw!==''?validDateValue($birthRaw):null;
    if($birthRaw!==''&&!$birth)throw new InvalidArgumentException(t('validation.invalid_birth_date'));
    $issueRaw=trim((string)($input['licence_issue_date']??''));$issue=$issueRaw!==''?validDateValue($issueRaw):null;
    $expiryRaw=trim((string)($input['licence_expires_at']??''));$expiry=$expiryRaw!==''?validDateValue($expiryRaw):null;
    if(($issueRaw!==''&&!$issue)||($expiryRaw!==''&&!$expiry))throw new InvalidArgumentException(t('validation.valid_driver_dates'));
    if($birth&&$birth->diff(new DateTimeImmutable('today'))->y<appConfig('minimum_driver_age'))throw new DomainException(t('validation.driver_minimum_age'));
    $emailRaw=trim((string)($input['email']??''));$email=$emailRaw===''?'':normalizedEmail($emailRaw);
    if($emailRaw!==''&&!filter_var($emailRaw,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException(t('validation.email'));
    $phoneRaw=trim((string)($input['phone']??''));$phone=normalizedPhone($phoneRaw);
    $type=validateChoice($input['customer_type']??'individual',['individual','company'],'individual');
    return [
        'customer_type'=>$type,'first_name'=>$first,'last_name'=>$last,
        'identity_number'=>trim((string)($input['identity_number']??'')),
        'nationality'=>strtoupper(substr(trim((string)($input['nationality']??'')),0,2))?:null,
        'date_of_birth'=>$birth?$birth->format('Y-m-d'):null,'address'=>trim((string)($input['address']??'')),
        'phone'=>$phoneRaw,'phone_normalized'=>$phone,'whatsapp'=>trim((string)($input['whatsapp']??'')),
        'email'=>$emailRaw,'email_normalized'=>$email,'licence_number'=>trim((string)($input['licence_number']??'')),
        'licence_issue_date'=>$issue?$issue->format('Y-m-d'):null,'licence_expires_at'=>$expiry?$expiry->format('Y-m-d'):null,
        'licence_country'=>strtoupper(substr(trim((string)($input['licence_country']??'')),0,2))?:null,
        'company_name'=>trim((string)($input['company_name']??'')),'ice'=>trim((string)($input['ice']??'')),
        'tax_identifier'=>trim((string)($input['tax_identifier']??'')),'company_registration_number'=>trim((string)($input['company_registration_number']??'')),
        'billing_address'=>trim((string)($input['billing_address']??'')),'notes'=>trim((string)($input['notes']??'')),
    ];
}

function assertCustomerDuplicateFree($agencyId, array $profile, $excludeId = null)
{
    $where=['agency_id=:agency','archived_at IS NULL'];$params=['agency'=>(int)$agencyId];
    if($excludeId){$where[]='id<>:exclude';$params['exclude']=(int)$excludeId;}
    $matches=[];
    if($profile['email_normalized']!==''){$matches[]='email_normalized=:email';$params['email']=$profile['email_normalized'];}
    if($profile['phone_normalized']!==''){$matches[]='phone_normalized=:phone';$params['phone']=$profile['phone_normalized'];}
    if($profile['identity_number']!==''){$matches[]='identity_number=:identity';$params['identity']=$profile['identity_number'];}
    if(!$matches)return;
    $row=dbFetchOne('SELECT id FROM customers WHERE '.implode(' AND ',$where).' AND ('.implode(' OR ',$matches).') LIMIT 1',$params);
    if($row)throw new DomainException(t('validation.duplicate_customer'));
}

function createCustomerProfile($agencyId, array $input)
{
    enforcePermission('customers.manage');requireAgencyAccess($agencyId);$profile=validatedCustomerProfile($input);
    return withTransaction(function()use($agencyId,$profile){
        assertCustomerDuplicateFree($agencyId,$profile);
        $columns=array_keys($profile);$params=$profile;$params['agency_id']=(int)$agencyId;$params['creator']=currentUserId();$params['updater']=currentUserId();
        $sql='INSERT INTO customers(agency_id,'.implode(',',$columns).',status,created_by,updated_by) VALUES(:agency_id,'.implode(',',array_map(fn($c)=>':'.$c,$columns)).',\'new\',:creator,:updater)';
        dbExecute($sql,$params);$id=(int)db()->lastInsertId();
        dbExecute("INSERT INTO customer_status_history(customer_id,agency_id,previous_status,new_status,action_type,changed_by) VALUES(:customer,:agency,NULL,'new','created',:user)",['customer'=>$id,'agency'=>$agencyId,'user'=>currentUserId()]);
        auditLog('customer.created','customer',$id,null,['status'=>'new'],$agencyId);return$id;
    });
}

function updateCustomerProfile($customerId, array $input, $expectedUpdatedAt)
{
    enforcePermission('customers.manage');$profile=validatedCustomerProfile($input);
    return withTransaction(function()use($customerId,$profile,$expectedUpdatedAt){
        $customer=scopedCustomerRecord($customerId,false,true);if(!$customer)throw new InvalidArgumentException(t('validation.customer_not_found'));
        if(!hash_equals((string)$customer['updated_at'],(string)$expectedUpdatedAt))throw new DomainException(t('validation.stale_customer'));
        assertCustomerDuplicateFree($customer['agency_id'],$profile,$customer['id']);
        $sets=[];$params=['id'=>$customer['id'],'expected'=>$expectedUpdatedAt,'user'=>currentUserId()];$before=[];$after=[];
        foreach($profile as $column=>$value){$sets[]="$column=:$column";$params[$column]=$value;if((string)($customer[$column]??'')!==(string)($value??'')){$before[$column]=$customer[$column]??null;$after[$column]=$value;}}
        if(!$after)return false;
        $changed=dbExecute('UPDATE customers SET '.implode(',',$sets).',updated_by=:user WHERE id=:id AND updated_at=:expected',$params);
        if($changed->rowCount()!==1)throw new DomainException(t('validation.stale_customer'));
        auditLog('customer.profile_updated','customer',$customer['id'],$before,$after,$customer['agency_id']);return true;
    });
}

function changeCustomerLifecycle($customerId, $command, $reason, $expectedUpdatedAt)
{
    $map=customerLifecycleCommands();if(!isset($map[$command]))throw new InvalidArgumentException(t('validation.invalid_action'));
    enforcePermission($map[$command]['sensitive']?'customers.lifecycle':'customers.manage');
    return withTransaction(function()use($customerId,$command,$reason,$expectedUpdatedAt,$map){
        $customer=scopedCustomerRecord($customerId,true,true);if(!$customer)throw new InvalidArgumentException(t('validation.customer_not_found'));
        if($customer['archived_at']!==null)throw new DomainException(t('validation.archived_customer'));
        if(!hash_equals((string)$customer['updated_at'],(string)$expectedUpdatedAt))throw new DomainException(t('validation.stale_customer'));
        $definition=$map[$command];$newStatus=$definition['status'];
        if($command==='unblock'){
            if($customer['status']!=='blocked')throw new DomainException(t('validation.customer_transition'));
            $history=dbFetchOne("SELECT previous_status FROM customer_status_history WHERE customer_id=:id AND action_type='blocked' AND previous_status IN ('new','regular','VIP','watchlist') ORDER BY changed_at DESC,id DESC LIMIT 1",['id'=>$customer['id']]);
            $newStatus=$history['previous_status']??'regular';
        }elseif($command==='block'&&$customer['status']==='archived')throw new DomainException(t('validation.customer_transition'));
        elseif($command!=='archive'&&$customer['status']==='blocked')throw new DomainException(t('validation.customer_transition'));
        if($newStatus===$customer['status'])throw new DomainException(t('validation.no_status_change'));
        $reason=trim((string)$reason);if($definition['sensitive']&&($reason===''||mb_strlen($reason)>255))throw new InvalidArgumentException(t('validation.lifecycle_reason'));
        $params=['status'=>$newStatus,'reason'=>$newStatus==='blocked'?$reason:null,'user'=>currentUserId(),'id'=>$customer['id'],'expected'=>$expectedUpdatedAt];
        $archiveSql=$command==='archive'?',archived_at=NOW(6)':'';
        $changed=dbExecute("UPDATE customers SET status=:status,blocking_reason=:reason,updated_by=:user$archiveSql WHERE id=:id AND updated_at=:expected",$params);
        if($changed->rowCount()!==1)throw new DomainException(t('validation.stale_customer'));
        dbExecute('INSERT INTO customer_status_history(customer_id,agency_id,previous_status,new_status,action_type,reason,changed_by) VALUES(:customer,:agency,:previous,:new_status,:action,:reason,:user)',['customer'=>$customer['id'],'agency'=>$customer['agency_id'],'previous'=>$customer['status'],'new_status'=>$newStatus,'action'=>$definition['action'],'reason'=>$definition['sensitive']?$reason:null,'user'=>currentUserId()]);
        auditLog('customer.'.$definition['action'],'customer',$customer['id'],['status'=>$customer['status']],['status'=>$newStatus,'reason'=>$definition['sensitive']?$reason:null],$customer['agency_id']);return true;
    });
}

function customerWorkspaceData($customerId)
{
    $customer=scopedCustomerRecord($customerId,false,false);if(!$customer)throw new InvalidArgumentException(t('validation.customer_not_found'));
    $id=$customer['id'];
    $data=['customer'=>$customer];
    $data['metrics']=dbFetchOne("SELECT COUNT(*) reservations_count,SUM(status='completed') completed_count,COALESCE(SUM(remaining_amount),0) outstanding_balance,MAX(return_at) last_rental FROM reservations WHERE customer_id=:id AND archived_at IS NULL",['id'=>$id]);
    $data['drivers']=dbFetchAll('SELECT * FROM additional_drivers WHERE customer_id=:id AND archived_at IS NULL ORDER BY created_at DESC',['id'=>$id]);
    $data['documents']=dbFetchAll('SELECT * FROM customer_documents WHERE customer_id=:id ORDER BY archived_at IS NOT NULL,expires_at,id',['id'=>$id]);
    $data['reservations']=dbFetchAll('SELECT r.*,v.registration_number,v.brand,v.model FROM reservations r LEFT JOIN vehicles v ON v.id=r.vehicle_id WHERE r.customer_id=:id ORDER BY r.created_at DESC LIMIT 50',['id'=>$id]);
    $data['history']=dbFetchAll('SELECT h.*,u.fullname changed_by_name FROM customer_status_history h LEFT JOIN users u ON u.id=h.changed_by WHERE h.customer_id=:id ORDER BY h.changed_at DESC,h.id DESC',['id'=>$id]);
    $data['payments']=canViewFinanceHistory()?dbFetchAll('SELECT p.* FROM payments p JOIN reservations r ON r.id=p.reservation_id WHERE r.customer_id=:id ORDER BY p.paid_at DESC LIMIT 50',['id'=>$id]):[];
    $data['requests']=can('reservations.manage')?dbFetchAll('SELECT cr.*,r.reference FROM customer_requests cr LEFT JOIN reservations r ON r.id=cr.reservation_id WHERE cr.customer_id=:id ORDER BY cr.created_at DESC LIMIT 50',['id'=>$id]):[];
    $data['fines']=can('vehicles.manage')?dbFetchAll('SELECT f.*,v.registration_number FROM fines f JOIN vehicles v ON v.id=f.vehicle_id WHERE f.customer_id=:id ORDER BY f.occurred_at DESC LIMIT 30',['id'=>$id]):[];
    $data['accidents']=can('vehicles.manage')?dbFetchAll('SELECT a.*,v.registration_number FROM accidents a JOIN vehicles v ON v.id=a.vehicle_id WHERE a.customer_id=:id ORDER BY a.occurred_at DESC LIMIT 30',['id'=>$id]):[];
    return$data;
}

function changeCustomerDocumentArchive($documentId, $restore = false)
{
    enforcePermission('customers.manage');return withTransaction(function()use($documentId,$restore){
        $ids=currentAgencyIds();if(!$ids)$ids=[0];$ph=implode(',',array_fill(0,count($ids),'?'));
        $row=dbFetchOne("SELECT d.*,c.agency_id,c.archived_at customer_archived_at FROM customer_documents d JOIN customers c ON c.id=d.customer_id WHERE d.id=? AND c.agency_id IN ($ph) FOR UPDATE",array_merge([(int)$documentId],$ids));
        if(!$row||$row['customer_archived_at']!==null)throw new InvalidArgumentException(t('validation.document_not_found'));
        if($restore){if($row['archived_at']===null)return false;dbExecute('UPDATE customer_documents SET archived_at=NULL WHERE id=:id AND archived_at IS NOT NULL',['id'=>$row['id']]);$action='restored';}
        else{if($row['archived_at']!==null)return false;dbExecute('UPDATE customer_documents SET archived_at=NOW() WHERE id=:id AND archived_at IS NULL',['id'=>$row['id']]);$action='archived';}
        auditLog('customer.document_'.$action,'customer_document',$row['id'],['archived_at'=>$row['archived_at']],['archived_at'=>$restore?null:'set','customer_id'=>$row['customer_id']],$row['agency_id']);return true;
    });
}

function addCustomerDriver($customerId, array $input)
{
    enforcePermission('customers.manage');$birth=validDateValue($input['date_of_birth']??'');$issue=trim((string)($input['licence_issue_date']??''));$issue=$issue!==''?validDateValue($issue):null;$expiry=validDateValue($input['licence_expires_at']??'');
    if(!$birth||!$expiry||$expiry<=new DateTimeImmutable('today'))throw new InvalidArgumentException(t('validation.valid_driver_dates'));
    if($birth->diff(new DateTimeImmutable('today'))->y<appConfig('minimum_driver_age'))throw new DomainException(t('validation.driver_minimum_age'));
    return withTransaction(function()use($customerId,$input,$birth,$issue,$expiry){$customer=scopedCustomerRecord($customerId,false,true);if(!$customer)throw new InvalidArgumentException(t('validation.customer_not_found'));
        $first=trim((string)($input['first_name']??''));$last=trim((string)($input['last_name']??''));$licence=trim((string)($input['licence_number']??''));if($first===''||$last===''||$licence==='')throw new InvalidArgumentException(t('validation.driver_fields'));
        dbExecute("INSERT INTO additional_drivers(customer_id,first_name,last_name,identity_number,date_of_birth,licence_number,licence_issue_date,licence_expires_at,licence_country,phone,status,created_by) VALUES(:customer,:first,:last,:identity,:birth,:licence,:issue,:expiry,:country,:phone,'active',:user)",['customer'=>$customer['id'],'first'=>$first,'last'=>$last,'identity'=>trim((string)($input['identity_number']??'')),'birth'=>$birth->format('Y-m-d'),'licence'=>$licence,'issue'=>$issue?$issue->format('Y-m-d'):null,'expiry'=>$expiry->format('Y-m-d'),'country'=>strtoupper(substr(trim((string)($input['licence_country']??'MA')),0,2)),'phone'=>trim((string)($input['phone']??'')),'user'=>currentUserId()]);$id=(int)db()->lastInsertId();auditLog('additional_driver.created','additional_driver',$id,null,['customer_id'=>$customer['id']],$customer['agency_id']);return$id;});
}

function uploadCustomerDocument($customerId, array $input, array $file)
{
    enforcePermission('customers.manage');$customer=scopedCustomerRecord($customerId,false,false);if(!$customer)throw new InvalidArgumentException(t('validation.customer_not_found'));
    $stored=storeValidatedDocument($file,'customer_documents');
    try{return withTransaction(function()use($customer,$input,$stored){dbExecute('INSERT INTO customer_documents(customer_id,document_type,document_number,issuing_country,issued_at,expires_at,storage_path,original_name,mime_type,file_size,created_by) VALUES(:customer,:type,:number,:country,:issued,:expires,:path,:original,:mime,:size,:user)',['customer'=>$customer['id'],'type'=>validateChoice($input['document_type']??'',['identity','passport','driving_licence','company','other'],'other'),'number'=>trim((string)($input['document_number']??'')),'country'=>strtoupper(substr(trim((string)($input['issuing_country']??'MA')),0,2)),'issued'=>trim((string)($input['issued_at']??''))?:null,'expires'=>trim((string)($input['expires_at']??''))?:null,'path'=>$stored['path'],'original'=>$stored['original_name'],'mime'=>$stored['mime_type'],'size'=>$stored['size'],'user'=>currentUserId()]);$id=(int)db()->lastInsertId();auditLog('customer.document_created','customer_document',$id,null,['customer_id'=>$customer['id']],$customer['agency_id']);return$id;});}
    catch(Throwable $exception){removeNewStoredUpload($stored['path']);throw$exception;}
}
