<?php

function phase5bToken(): string
{
    return bin2hex(random_bytes(32));
}

function phase5bAs(array $fixture,string $role): void
{
    $userId=$fixture['users'][$role]??0;
    $_SESSION=['user_id'=>$userId,'role'=>$role,'agency_ids'=>[$fixture['agency_id']], '_authenticated_at'=>time()];
}

function phase5bCreateReservation(array $fixture,string $suffix='RES',string $status='confirmed'): int
{
    $reference=substr($fixture['run'].'_'.$suffix,0,40);
    dbExecute(
        "INSERT INTO reservations(reference,agency_id,return_agency_id,customer_id,vehicle_id,category_id,status,source,pickup_at,return_at,currency,daily_price,rental_days,tax_rate,total_amount,advance_amount,legacy_finance_paid_amount,remaining_amount,deposit_amount,pricing_snapshot_json,finance_tracking_started_at,created_by,updated_by)
         VALUES(:reference,:agency,:agency2,:customer,:vehicle,:category,:status,'agency',DATE_ADD(NOW(),INTERVAL 30 DAY),DATE_ADD(NOW(),INTERVAL 31 DAY),'MAD',100,1,0,100,0,0,100,200,:snapshot,NOW(6),:actor,:actor2)",
        ['reference'=>$reference,'agency'=>$fixture['agency_id'],'agency2'=>$fixture['agency_id'],'customer'=>$fixture['customer_id'],'vehicle'=>$fixture['vehicle_id'],'category'=>$fixture['category_id'],'status'=>$status,'snapshot'=>json_encode(['schema_version'=>2,'total'=>'100.00'],JSON_UNESCAPED_SLASHES),'actor'=>$fixture['users'][ROLE_AGENCY_MANAGER],'actor2'=>$fixture['users'][ROLE_AGENCY_MANAGER]]
    );
    $id=(int)db()->lastInsertId();
    dbExecute('INSERT INTO reservation_status_history(reservation_id,from_status,to_status,changed_by)VALUES(:id,NULL,:status,:actor)',['id'=>$id,'status'=>$status,'actor'=>$fixture['users'][ROLE_AGENCY_MANAGER]]);
    return$id;
}

function phase5bFixture(string $run): array
{
    $fixture=['run'=>$run,'agency_id'=>0,'users'=>[],'customer_id'=>0,'category_id'=>0,'vehicle_id'=>0,'reservation_ids'=>[]];
    dbExecute("INSERT INTO agencies(name,code,currency,status)VALUES(:name,:code,'MAD','active')",['name'=>$run,'code'=>substr($run,0,30)]);
    $fixture['agency_id']=(int)db()->lastInsertId();
    foreach([ROLE_OWNER,ROLE_AGENCY_MANAGER,ROLE_RENTAL_AGENT,ROLE_FLEET_AGENT,ROLE_ACCOUNTANT]as$role){
        $email=strtolower($run.'_'.strtolower($role).'@example.test');
        dbExecute("INSERT INTO users(fullname,email,email_normalized,password_hash,role,status)VALUES(:name,:email,:email2,:hash,:role,'active')",['name'=>$run.' '.$role,'email'=>$email,'email2'=>$email,'hash'=>password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT),'role'=>$role]);
        $id=(int)db()->lastInsertId();$fixture['users'][$role]=$id;
        if($role!==ROLE_OWNER)dbExecute('INSERT INTO user_agencies(user_id,agency_id,is_primary)VALUES(:user,:agency,1)',['user'=>$id,'agency'=>$fixture['agency_id']]);
    }
    dbExecute("INSERT INTO customers(agency_id,first_name,last_name,identity_number,licence_number,status,created_by,updated_by)VALUES(:agency,'P5B_TEST','Customer',:identity,:licence,'regular',:actor,:actor2)",['agency'=>$fixture['agency_id'],'identity'=>$run.'_ID','licence'=>$run.'_LIC','actor'=>$fixture['users'][ROLE_AGENCY_MANAGER],'actor2'=>$fixture['users'][ROLE_AGENCY_MANAGER]]);
    $fixture['customer_id']=(int)db()->lastInsertId();
    dbExecute("INSERT INTO customer_status_history(customer_id,agency_id,new_status,action_type,changed_by)VALUES(:customer,:agency,'regular','created',:actor)",['customer'=>$fixture['customer_id'],'agency'=>$fixture['agency_id'],'actor'=>$fixture['users'][ROLE_AGENCY_MANAGER]]);
    dbExecute("INSERT INTO vehicle_categories(agency_id,name,code,base_daily_price,recommended_deposit,status)VALUES(:agency,:name,:code,100,200,'active')",['agency'=>$fixture['agency_id'],'name'=>$run,'code'=>substr($run,0,30)]);
    $fixture['category_id']=(int)db()->lastInsertId();
    dbExecute("INSERT INTO vehicles(agency_id,category_id,registration_number,vin,brand,model,base_daily_price,recommended_deposit,status,created_by,updated_by)VALUES(:agency,:category,:registration,:vin,'P5B','Vehicle',100,200,'available',:actor,:actor2)",['agency'=>$fixture['agency_id'],'category'=>$fixture['category_id'],'registration'=>substr($run,0,40),'vin'=>substr($run.'_VIN',0,80),'actor'=>$fixture['users'][ROLE_AGENCY_MANAGER],'actor2'=>$fixture['users'][ROLE_AGENCY_MANAGER]]);
    $fixture['vehicle_id']=(int)db()->lastInsertId();
    return$fixture;
}

function phase5bCleanup(array $fixture): void
{
    if(db()->inTransaction())db()->rollBack();$agency=(int)($fixture['agency_id']??0);if(!$agency)return;
    dbExecute('DELETE FROM inspection_photos WHERE agency_id=:agency',['agency'=>$agency]);
    dbExecute('DELETE FROM vehicle_inspections WHERE agency_id=:agency',['agency'=>$agency]);
    dbExecute('DELETE FROM contract_acknowledgements WHERE agency_id=:agency',['agency'=>$agency]);
    dbExecute('DELETE FROM contract_status_history WHERE agency_id=:agency',['agency'=>$agency]);
    dbExecute('UPDATE rental_contracts SET current_version_id=NULL WHERE agency_id=:agency',['agency'=>$agency]);
    dbExecute('DELETE FROM contract_versions WHERE agency_id=:agency',['agency'=>$agency]);
    dbExecute('DELETE FROM rental_operation_idempotency_keys WHERE origin_agency_id=:agency OR performing_agency_id=:agency2',['agency'=>$agency,'agency2'=>$agency]);
    dbExecute('DELETE FROM rental_contracts WHERE agency_id=:agency',['agency'=>$agency]);
    dbExecute('DELETE FROM audit_logs WHERE agency_id=:agency',['agency'=>$agency]);
    $reservations=array_column(dbFetchAll('SELECT id FROM reservations WHERE agency_id=:agency',['agency'=>$agency]),'id');
    if($reservations){$ph=implode(',',array_fill(0,count($reservations),'?'));dbExecute("DELETE FROM reservation_status_history WHERE reservation_id IN($ph)",$reservations);dbExecute("DELETE FROM reservation_options WHERE reservation_id IN($ph)",$reservations);dbExecute("DELETE FROM reservations WHERE id IN($ph)",$reservations);}
    dbExecute('DELETE FROM customer_status_history WHERE agency_id=:agency',['agency'=>$agency]);
    dbExecute('DELETE FROM customers WHERE agency_id=:agency',['agency'=>$agency]);
    $vehicles=array_column(dbFetchAll('SELECT id FROM vehicles WHERE agency_id=:agency',['agency'=>$agency]),'id');
    if($vehicles&&tableExists('vehicle_status_history')){$ph=implode(',',array_fill(0,count($vehicles),'?'));dbExecute("DELETE FROM vehicle_status_history WHERE vehicle_id IN($ph)",$vehicles);}
    dbExecute('DELETE FROM vehicles WHERE agency_id=:agency',['agency'=>$agency]);
    dbExecute('DELETE FROM vehicle_categories WHERE agency_id=:agency',['agency'=>$agency]);
    $users=array_values($fixture['users']??[]);if($users){$ph=implode(',',array_fill(0,count($users),'?'));dbExecute("DELETE FROM user_agencies WHERE user_id IN($ph)",$users);dbExecute("DELETE FROM users WHERE id IN($ph)",$users);}
    dbExecute('DELETE FROM agencies WHERE id=:agency',['agency'=>$agency]);
}

function phase5bPair(string $runtime,array $a,array $b): array
{
    $barrier=$runtime.'/barrier_'.bin2hex(random_bytes(3));mkdir($barrier,0750,true);$processes=[];$results=[];
    foreach(['a'=>$a,'b'=>$b]as$label=>$command){
        $command+=['barrier'=>$barrier,'label'=>$label];$path=$barrier.'/'.$label.'.json';
        file_put_contents($path,json_encode($command,JSON_UNESCAPED_SLASHES));
        $descriptor=[['pipe','r'],['file',$barrier.'/'.$label.'.out','a'],['file',$barrier.'/'.$label.'.err','a']];
        $processes[$label]=proc_open([PHP_BINARY,'-d','session.save_path='.$runtime,dirname(__DIR__).'/helpers/phase5b_concurrency_worker.php',$path],$descriptor,$pipes,$label?dirname(__DIR__,2):null);
        if(is_resource($processes[$label]))fclose($pipes[0]);
    }
    $deadline=microtime(true)+20;while((!is_file($barrier.'/a.ready')||!is_file($barrier.'/b.ready'))&&microtime(true)<$deadline)usleep(10000);
    file_put_contents($barrier.'/go','go');
    foreach($processes as$label=>$process){if(is_resource($process))proc_close($process);$path=$barrier.'/'.$label.'.result';$results[$label]=is_file($path)?json_decode((string)file_get_contents($path),true):['ok'=>false,'error'=>'missing result'];}
    foreach(glob($barrier.'/*')?:[]as$file)if(is_file($file))unlink($file);rmdir($barrier);
    return$results;
}
