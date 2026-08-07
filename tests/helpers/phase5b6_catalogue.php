<?php

function p5b6Catalogue(): array
{
    return [
        'roles'=>[ROLE_OWNER,ROLE_AGENCY_MANAGER,ROLE_RENTAL_AGENT,ROLE_ACCOUNTANT,ROLE_FLEET_AGENT,ROLE_CUSTOMER],
        'permissions'=>[
            'contract.view'=>[ROLE_OWNER,ROLE_AGENCY_MANAGER,ROLE_RENTAL_AGENT],
            'contract.create'=>[ROLE_OWNER,ROLE_AGENCY_MANAGER,ROLE_RENTAL_AGENT],
            'contract.issue'=>[ROLE_OWNER,ROLE_AGENCY_MANAGER,ROLE_RENTAL_AGENT],
            'contract.cancel'=>[ROLE_OWNER,ROLE_AGENCY_MANAGER],
            'contract.acknowledge_customer'=>[ROLE_OWNER,ROLE_AGENCY_MANAGER,ROLE_RENTAL_AGENT],
            'contract.acknowledge_agency'=>[ROLE_OWNER,ROLE_AGENCY_MANAGER,ROLE_RENTAL_AGENT],
            'contract.sign'=>[ROLE_OWNER,ROLE_AGENCY_MANAGER],
            'inspection_photos.view'=>[ROLE_OWNER,ROLE_AGENCY_MANAGER,ROLE_RENTAL_AGENT,ROLE_FLEET_AGENT],
            'inspection_photos.upload'=>[ROLE_OWNER,ROLE_AGENCY_MANAGER,ROLE_RENTAL_AGENT,ROLE_FLEET_AGENT],
            'inspection_photos.archive'=>[ROLE_OWNER,ROLE_AGENCY_MANAGER],
            'rental.checkout'=>[ROLE_OWNER,ROLE_AGENCY_MANAGER,ROLE_RENTAL_AGENT],
            'rental.checkin'=>[ROLE_OWNER,ROLE_AGENCY_MANAGER,ROLE_RENTAL_AGENT],
        ],
        'routes'=>[
            'backoffice/contracts.php'=>['method'=>'GET|POST','permission'=>'contract.view','actions'=>['create'=>'contract.create']],
            'backoffice/contract_detail.php'=>['method'=>'GET|POST','permission'=>'contract.view','actions'=>['issue'=>'contract.issue','cancel'=>'contract.cancel','acknowledge_customer'=>'contract.acknowledge_customer','acknowledge_agency'=>'contract.acknowledge_agency']],
            'backoffice/contract_print.php'=>['method'=>'GET','permission'=>'contract.view','actions'=>[]],
            'backoffice/inspections.php'=>['method'=>'GET|POST','permission'=>'inspections.manage','actions'=>['photo_bundle'=>'inspection_photos.upload','photo_archive'=>'inspection_photos.archive','rental_checkout'=>'rental.checkout','rental_checkin'=>'rental.checkin']],
            'backoffice/inspection_photo.php'=>['method'=>'GET','permission'=>'inspection_photos.view','actions'=>[]],
            'backoffice/reservation_detail.php'=>['method'=>'GET|POST','permission'=>'reservations.manage','actions'=>[]],
        ],
        'statuses'=>[
            'reservation'=>['draft','quote','pending','confirmed','deposit_paid','ready','active','completed','cancelled','no_show','expired'],
            'contract'=>['draft','issued','signed','active','completed','cancelled'],
            'vehicle'=>['available','reserved','rented','cleaning','maintenance','damaged','blocked','sold','retired'],
            'inspection'=>['draft','completed','archived'],
            'idempotency'=>['in_progress','completed'],
        ],
        'photo_slots'=>['front','rear','left','right','interior','dashboard'],
        'operations'=>['rental.checkout','rental.checkin'],
    ];
}

function p5b6TranslationKeys(): array
{
    $english=translationCatalogue('en');$keys=[];
    foreach(array_keys($english)as$key){
        if(preg_match('/^(page\.(contracts|contract_detail|inspections)|section\.(create_contract|contract_|rental_summary|allowed_actions|inspection_)|acknowledgement\.|photo\.slot_|action\.(create_contract|issue_contract|cancel_contract|acknowledge_|upload_inspection_photos|rental_checkout|rental_checkin)|message\.(contract_|inspection_photo_|rental_checkout|rental_checkin)|validation\.(contract_|inspection_|checkout_|checkin_)|print\.)/',$key))$keys[]=$key;
    }
    $catalogue=p5b6Catalogue();
    foreach($catalogue['statuses']as$statuses)foreach($statuses as$status)$keys[]='status.'.$status;
    foreach(['checkout','return','good','dirty','damaged']as$value)$keys[]='option.'.$value;
    foreach(['number','agency','reservation','customer','vehicle','version','current_version','issued','signed','cancelled','type','party_name','actor','language','method','date_time','digest','created','transition','reason','period','total','identity','licence','registration','pickup','return','fuel','mileage','photos','inspection_date_time','checkout_mileage','return_mileage','returned_at','vehicle_condition','operational_comment','pickup_date_time','return_date_time','notes']as$field)$keys[]='field.'.$field;
    foreach(['en','fr','ar']as$language)$keys[]='language.'.$language;
    return array_values(array_unique($keys));
}

function p5b6Placeholders(string $value): array
{
    preg_match_all('/:([a-z][a-z0-9_]*)/i',$value,$matches);$names=array_values(array_unique($matches[1]??[]));sort($names);return$names;
}

function p5b6RuntimeInventory(): array
{
    $root=inspectionPhotoRoot();if(!is_dir($root))return[];$base=inspectionPhotoNormalPath($root);$items=[];
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);
    foreach($iterator as$item)$items[]=substr(inspectionPhotoNormalPath($item->getPathname()),strlen($base)+1).($item->isDir()?'/':'');
    sort($items,SORT_STRING);return$items;
}

function p5b6CreatePhotoBundle(array $fixture,int $inspection,array &$temporaryFiles): array
{
    $uploads=[];
    foreach(inspectionPhotoSlots()as$slot){
        $path=dirname(__DIR__,2).'/storage/p5b6-upload-'.bin2hex(random_bytes(8)).'.jpg';$image=imagecreatetruecolor(8,8);$colour=imagecolorallocate($image,24,88,152);imagefill($image,0,0,$colour);imagejpeg($image,$path,90);imagedestroy($image);$temporaryFiles[]=$path;
        $uploads[$slot]=['name'=>$slot.'.jpg','type'=>'image/jpeg','tmp_name'=>$path,'error'=>UPLOAD_ERR_OK,'size'=>filesize($path)];
    }
    $result=inspectionPhotoPersistBundle($inspection,inspectionPhotoStageBundle($uploads),phase5bToken());
    if($result!==$inspection)throw new RuntimeException('Phase 5B.6 photo bundle fixture failed.');
    return dbFetchAll('SELECT id,photo_slot,storage_path,file_size,sha256 FROM inspection_photos WHERE inspection_id=:inspection AND archived_at IS NULL ORDER BY photo_slot,id',['inspection'=>$inspection]);
}

function p5b6PrepareCheckout(array $fixture,string $suffix,array &$temporaryFiles,bool $withPhotos=true): array
{
    phase5bAs($fixture,ROLE_AGENCY_MANAGER);$reservation=phase5bCreateReservation($fixture,$suffix,'ready');
    dbExecute('UPDATE reservations SET pickup_at=DATE_SUB(NOW(6),INTERVAL 2 HOUR),return_at=DATE_ADD(NOW(6),INTERVAL 1 DAY) WHERE id=:id',['id'=>$reservation]);
    dbExecute("UPDATE vehicles SET status='reserved',current_mileage=100,archived_at=NULL WHERE id=:id",['id'=>$fixture['vehicle_id']]);
    $contract=contractCreateFromReservation(['reservation_id'=>$reservation,'idempotency_key'=>phase5bToken()]);contractIssue(['contract_id'=>$contract,'idempotency_key'=>phase5bToken()]);
    $detail=contractScopedDetail($contract)['contract'];$version=(int)$detail['current_version_id'];
    foreach([['customer',trim($detail['first_name'].' '.$detail['last_name'])],['agency_representative',$fixture['run'].' '.ROLE_AGENCY_MANAGER]]as[$type,$party])contractRecordAcknowledgement(['contract_id'=>$contract,'contract_version_id'=>$version,'acknowledgement_type'=>$type,'language_code'=>'en','party_name'=>$party,'acknowledgement_method'=>'in_person','idempotency_key'=>phase5bToken()]);
    dbExecute("INSERT INTO vehicle_inspections(agency_id,origin_agency_id,performing_agency_id,reservation_id,contract_id,vehicle_id,customer_id,inspection_type,inspected_at,mileage,fuel_level,status,created_by)VALUES(:agency,:origin,:performing,:reservation,:contract,:vehicle,:customer,'checkout',DATE_SUB(NOW(6),INTERVAL 5 MINUTE),125,50,'draft',:actor)",['agency'=>$fixture['agency_id'],'origin'=>$fixture['agency_id'],'performing'=>$fixture['agency_id'],'reservation'=>$reservation,'contract'=>$contract,'vehicle'=>$fixture['vehicle_id'],'customer'=>$fixture['customer_id'],'actor'=>$fixture['users'][ROLE_AGENCY_MANAGER]]);
    $checkout=(int)db()->lastInsertId();$checkoutPhotos=$withPhotos?p5b6CreatePhotoBundle($fixture,$checkout,$temporaryFiles):[];
    return['reservation'=>$reservation,'contract'=>$contract,'version'=>$version,'checkout'=>$checkout,'checkout_photos'=>$checkoutPhotos,'vehicle'=>$fixture['vehicle_id'],'customer'=>$fixture['customer_id']];
}

function p5b6Checkout(array $ctx): int
{
    return rentalCheckout(['reservation_id'=>$ctx['reservation'],'contract_id'=>$ctx['contract'],'inspection_id'=>$ctx['checkout'],'idempotency_key'=>phase5bToken(),'mileage'=>'125','fuel_level'=>'50.00','handed_over_at'=>(new DateTimeImmutable('now'))->modify('-4 minutes')->format('Y-m-d H:i:s'),'comment'=>'Phase 5B.6 handover']);
}

function p5b6PrepareReturn(array $fixture,array $ctx,array &$temporaryFiles): array
{
    dbExecute("INSERT INTO vehicle_inspections(agency_id,origin_agency_id,performing_agency_id,reservation_id,contract_id,vehicle_id,customer_id,inspection_type,inspected_at,mileage,fuel_level,status,created_by)VALUES(:agency,:origin,:performing,:reservation,:contract,:vehicle,:customer,'return',NOW(6),150,40,'draft',:actor)",['agency'=>$fixture['agency_id'],'origin'=>$fixture['agency_id'],'performing'=>$fixture['agency_id'],'reservation'=>$ctx['reservation'],'contract'=>$ctx['contract'],'vehicle'=>$ctx['vehicle'],'customer'=>$ctx['customer'],'actor'=>$fixture['users'][ROLE_AGENCY_MANAGER]]);
    $ctx['return_inspection']=(int)db()->lastInsertId();$ctx['return_photos']=p5b6CreatePhotoBundle($fixture,$ctx['return_inspection'],$temporaryFiles);return$ctx;
}

function p5b6Checkin(array $ctx,string $condition='good'): int
{
    return rentalCheckin(['reservation_id'=>$ctx['reservation'],'contract_id'=>$ctx['contract'],'inspection_id'=>$ctx['return_inspection'],'idempotency_key'=>phase5bToken(),'mileage'=>'150','fuel_level'=>'40.00','returned_at'=>(new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),'vehicle_condition'=>$condition,'comment'=>'Phase 5B.6 return']);
}

function p5b6RemoveFixtureFiles(array $fixture): void
{
    if(empty($fixture['agency_id']))return;
    foreach(dbFetchAll('SELECT storage_path FROM inspection_photos WHERE agency_id=:agency',['agency'=>$fixture['agency_id']])as$row){$path=inspectionPhotoPath((string)$row['storage_path']);if($path&&is_file($path)&&!is_link($path))@unlink($path);if($path)inspectionPhotoRemoveEmptyControlledDirectory(dirname($path));}
    @rmdir(inspectionPhotoRoot().'/final/'.(int)$fixture['agency_id']);
}

function p5b6Session(int $userId,string $role,array $agencyIds,string $prefix='p5b6'): array
{
    session_write_close();session_name('rental_agency_session');session_id($prefix.bin2hex(random_bytes(12)));session_start();$sid=session_id();$csrf=bin2hex(random_bytes(32));$_SESSION=['user_id'=>$userId,'role'=>$role,'agency_ids'=>array_values(array_map('intval',$agencyIds)),'_authenticated_at'=>time(),'_created_at'=>time(),'_last_activity'=>time(),'_regenerated_at'=>time(),'_csrf_token'=>$csrf];session_write_close();return[$sid,$csrf,dirname(__DIR__,2).'/storage/sess_'.$sid];
}

function p5b6HttpRequest(string $url,string $session='',string $method='GET',array $data=[],?string $rawBody=null,array $headers=[]): array
{
    $header=$session!==''?'Cookie: rental_agency_session='.$session."\r\n":'';
    foreach($headers as$value)$header.=$value."\r\n";
    $options=['method'=>$method,'header'=>$header,'timeout'=>5,'ignore_errors'=>true,'max_redirects'=>0];
    if($method==='POST'){$options['content']=$rawBody??http_build_query($data);if($rawBody===null)$options['header'].="Content-Type: application/x-www-form-urlencoded\r\n";}
    $body=@file_get_contents($url,false,stream_context_create(['http'=>$options]));$responseHeaders=$http_response_header??[];
    return['body'=>$body===false?'':$body,'headers'=>$responseHeaders,'status'=>implode(' ',$responseHeaders)];
}

function p5b6Status(array $response,int $code): bool
{
    return preg_match('/HTTP\/\S+\s+'.preg_quote((string)$code,'/').'\b/',$response['status'])===1;
}

function p5b6DisclosurePattern(): string
{
    return '/SQLSTATE|PDOException|SELECT\s+.+\s+FROM|INSERT\s+INTO|UPDATE\s+.+\s+SET|stack trace|[A-Z]:\\\\[^\r\n<]+|\/(?:var|home|srv|tmp)\/[^\r\n<]+|inspection-photo-private|session[_ -]?id|csrf[_ -]?token|payload_hash|key_hash/i';
}
