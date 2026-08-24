<?php

function p5b4Inventory(): array
{
    $root=inspectionPhotoRoot();if(!is_dir($root))return[];$base=inspectionPhotoNormalPath($root);$items=[];
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);
    foreach($iterator as$item)$items[]=substr(inspectionPhotoNormalPath($item->getPathname()),strlen($base)+1).($item->isDir()?'/':'');
    sort($items,SORT_STRING);return$items;
}

function p5b4PhotoBundle(array $fixture,int $inspection,array &$files): array
{
    $hashes=[];
    foreach(inspectionPhotoSlots()as$slot){
        $dir=inspectionPhotoRoot().'/final/'.$fixture['agency_id'].'/'.$inspection;inspectionPhotoEnsureDirectory($dir);
        $name=bin2hex(random_bytes(24)).'.png';$path=$dir.'/'.$name;$image=imagecreatetruecolor(8,8);$color=imagecolorallocate($image,28,92,158);imagefill($image,0,0,$color);imagepng($image,$path);imagedestroy($image);
        $files[]=$path;$hash=hash_file('sha256',$path);$hashes[$path]=$hash;
        dbExecute('INSERT INTO inspection_photos(agency_id,inspection_id,photo_type,photo_slot,storage_path,original_name,mime_type,file_size,sha256,protected_file,captured_at,created_by)VALUES(:agency,:inspection,:type,:slot,:path,:name,\'image/png\',:size,:sha,1,NOW(6),:actor)',['agency'=>$fixture['agency_id'],'inspection'=>$inspection,'type'=>$slot,'slot'=>$slot,'path'=>'inspection-photo-private/final/'.$fixture['agency_id'].'/'.$inspection.'/'.$name,'name'=>$slot.'.png','size'=>filesize($path),'sha'=>$hash,'actor'=>$fixture['users'][ROLE_AGENCY_MANAGER]]);
    }
    return$hashes;
}

function p5b4Eligible(array $fixture,string $suffix,array &$files,string $condition='good'): array
{
    phase5bAs($fixture,ROLE_AGENCY_MANAGER);$reservation=phase5bCreateReservation($fixture,$suffix,'ready');
    dbExecute('UPDATE reservations SET pickup_at=DATE_SUB(NOW(6),INTERVAL 2 HOUR),return_at=DATE_ADD(NOW(6),INTERVAL 1 DAY) WHERE id=:id',['id'=>$reservation]);
    dbExecute("UPDATE vehicles SET status='reserved',current_mileage=100,archived_at=NULL WHERE id=:id",['id'=>$fixture['vehicle_id']]);
    $contract=contractCreateFromReservation(['reservation_id'=>$reservation,'idempotency_key'=>phase5bToken()]);contractIssue(['contract_id'=>$contract,'idempotency_key'=>phase5bToken()]);
    $row=contractScopedDetail($contract)['contract'];$version=(int)$row['current_version_id'];$customer=trim($row['first_name'].' '.$row['last_name']);$manager=$fixture['run'].' '.ROLE_AGENCY_MANAGER;
    foreach([['customer',$customer],['agency_representative',$manager]]as[$type,$name])contractRecordAcknowledgement(['contract_id'=>$contract,'contract_version_id'=>$version,'acknowledgement_type'=>$type,'language_code'=>'en','party_name'=>$name,'acknowledgement_method'=>'in_person','idempotency_key'=>phase5bToken()]);
    dbExecute("INSERT INTO vehicle_inspections(agency_id,origin_agency_id,performing_agency_id,reservation_id,contract_id,vehicle_id,customer_id,inspection_type,inspected_at,mileage,fuel_level,status,created_by)VALUES(:agency,:a2,:a3,:reservation,:contract,:vehicle,:customer,'checkout',DATE_SUB(NOW(6),INTERVAL 5 MINUTE),125,50,'draft',:actor)",['agency'=>$fixture['agency_id'],'a2'=>$fixture['agency_id'],'a3'=>$fixture['agency_id'],'reservation'=>$reservation,'contract'=>$contract,'vehicle'=>$fixture['vehicle_id'],'customer'=>$fixture['customer_id'],'actor'=>$fixture['users'][ROLE_AGENCY_MANAGER]]);
    $checkout=(int)db()->lastInsertId();p5b4PhotoBundle($fixture,$checkout,$files);
    rentalCheckout(['reservation_id'=>$reservation,'contract_id'=>$contract,'inspection_id'=>$checkout,'idempotency_key'=>phase5bToken(),'mileage'=>'125','fuel_level'=>'50.00','handed_over_at'=>(new DateTimeImmutable('now'))->modify('-4 minutes')->format('Y-m-d H:i:s'),'comment'=>'P5B4 checkout']);
    dbExecute("INSERT INTO vehicle_inspections(agency_id,origin_agency_id,performing_agency_id,reservation_id,contract_id,vehicle_id,customer_id,inspection_type,inspected_at,mileage,fuel_level,status,created_by)VALUES(:agency,:a2,:a3,:reservation,:contract,:vehicle,:customer,'return',NOW(6),150,40,'draft',:actor)",['agency'=>$fixture['agency_id'],'a2'=>$fixture['agency_id'],'a3'=>$fixture['agency_id'],'reservation'=>$reservation,'contract'=>$contract,'vehicle'=>$fixture['vehicle_id'],'customer'=>$fixture['customer_id'],'actor'=>$fixture['users'][ROLE_AGENCY_MANAGER]]);
    $inspection=(int)db()->lastInsertId();$hashes=p5b4PhotoBundle($fixture,$inspection,$files);
    return['reservation'=>$reservation,'contract'=>$contract,'checkout'=>$checkout,'inspection'=>$inspection,'vehicle'=>$fixture['vehicle_id'],'customer'=>$fixture['customer_id'],'hashes'=>$hashes,'command'=>['reservation_id'=>$reservation,'contract_id'=>$contract,'inspection_id'=>$inspection,'idempotency_key'=>phase5bToken(),'mileage'=>'150','fuel_level'=>'40.00','returned_at'=>(new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),'vehicle_condition'=>$condition,'comment'=>'P5B4 operational comment']];
}

function p5b4State(array $ctx): array
{
    return dbFetchOne('SELECT r.status reservation_status,rc.status contract_status,rc.completed_at contract_completed_at,v.status vehicle_status,v.current_mileage,vi.status inspection_status,vi.completed_at inspection_completed_at,vi.post_return_vehicle_state,vi.damage_notes,vi.cleanliness,vi.notes FROM reservations r JOIN rental_contracts rc ON rc.id=:contract JOIN vehicles v ON v.id=:vehicle JOIN vehicle_inspections vi ON vi.id=:inspection WHERE r.id=:reservation',['contract'=>$ctx['contract'],'vehicle'=>$ctx['vehicle'],'inspection'=>$ctx['inspection'],'reservation'=>$ctx['reservation']]);
}

function p5b4Unchanged(array $ctx,array $fixture,callable $assert,string $label): void
{
    $state=p5b4State($ctx);$assert($state['reservation_status']==='active'&&$state['contract_status']==='active'&&$state['contract_completed_at']===null&&$state['vehicle_status']==='rented'&&(int)$state['current_mileage']===125&&$state['inspection_status']==='draft'&&$state['inspection_completed_at']===null&&$state['post_return_vehicle_state']===null,$label.' changed business state');
    $assert((int)dbFetchOne("SELECT COUNT(*) n FROM reservation_status_history WHERE reservation_id=:id AND from_status='active' AND to_status='completed'",['id'=>$ctx['reservation']])['n']===0,$label.' wrote reservation history');
    $assert((int)dbFetchOne("SELECT COUNT(*) n FROM contract_status_history WHERE contract_id=:id AND from_status='active' AND to_status='completed'",['id'=>$ctx['contract']])['n']===0,$label.' wrote contract history');
    $assert((int)dbFetchOne("SELECT COUNT(*) n FROM audit_logs WHERE agency_id=:agency AND entity_id=:id AND action='rental.checkin'",['agency'=>$fixture['agency_id'],'id'=>$ctx['reservation']])['n']===0,$label.' wrote audit');
    $assert((int)dbFetchOne("SELECT COUNT(*) n FROM rental_operation_idempotency_keys WHERE origin_agency_id=:agency AND operation_type='rental_checkin' AND status='in_progress'",['agency'=>$fixture['agency_id']])['n']===0,$label.' left idempotency residue');
    $assert((int)dbFetchOne('SELECT COUNT(*) n FROM inspection_photos WHERE inspection_id=:id AND archived_at IS NULL',['id'=>$ctx['inspection']])['n']===6,$label.' changed photo rows');
    foreach($ctx['hashes']as$path=>$hash)$assert(is_file($path)&&hash_equals($hash,hash_file('sha256',$path)),$label.' changed protected bytes');
}

function p5b4RemoveFiles(array $files): void
{
    foreach($files as$file){if(is_file($file)&&!is_link($file))@unlink($file);inspectionPhotoRemoveEmptyControlledDirectory(dirname($file));}
}

function p5b4Session(int $id,string $role,array $agencies): array
{
    session_write_close();session_name('rental_agency_session');session_id('p5b4'.bin2hex(random_bytes(12)));session_start();
    $_SESSION=['user_id'=>$id,'role'=>$role,'agency_ids'=>$agencies,'_authenticated_at'=>time(),'_created_at'=>time(),'_last_activity'=>time(),'_regenerated_at'=>time(),'_csrf_token'=>bin2hex(random_bytes(32))];$result=[session_id(),$_SESSION['_csrf_token']];session_write_close();return$result;
}
