<?php
if(PHP_SAPI!=='cli')exit(1);
define('INSPECTION_PHOTO_TEST_HOOK',true);
require_once __DIR__.'/../app/application.php';
require_once __DIR__.'/helpers/phase5b_fixture.php';
require_once __DIR__.'/helpers/phase5b6_catalogue.php';

$failures=[];$assert=static function(bool $condition,string $message)use(&$failures):void{if(!$condition)$failures[]=$message;};
$rejects=static function(callable $operation,string $message)use($assert):void{try{$operation();$assert(false,$message);}catch(Throwable){}};
$run='P5B6_'.strtoupper(bin2hex(random_bytes(4)));$fixtures=[];$temporaryFiles=[];$inventoryBefore=p5b6RuntimeInventory();
$lifecycleEvidence=[];$damagedBefore=[];$damagedAfter=[];

$financialCounters=static function(array $fixture,array $ctx):array{
    $agency=(int)$fixture['agency_id'];$reservation=['agency'=>$agency,'reservation'=>$ctx['reservation'],'contract'=>$ctx['contract']];$customer=$reservation+['customer'=>$ctx['customer']];
    return[
        'payments'=>(int)dbFetchOne('SELECT COUNT(*) n FROM payments WHERE agency_id=:agency AND (reservation_id=:reservation OR contract_id=:contract)',$reservation)['n'],
        'invoices'=>(int)dbFetchOne('SELECT COUNT(*) n FROM invoices WHERE agency_id=:agency AND (reservation_id=:reservation OR contract_id=:contract OR customer_id=:customer)',$customer)['n'],
        'invoice_items'=>(int)dbFetchOne('SELECT COUNT(*) n FROM invoice_items ii JOIN invoices i ON i.id=ii.invoice_id WHERE i.agency_id=:agency AND (i.reservation_id=:reservation OR i.contract_id=:contract OR i.customer_id=:customer)',$customer)['n'],
        'deposits'=>(int)dbFetchOne('SELECT COUNT(*) n FROM deposits WHERE agency_id=:agency AND (reservation_id=:reservation OR contract_id=:contract)',$reservation)['n'],
        'expenses'=>(int)dbFetchOne('SELECT COUNT(*) n FROM expenses WHERE agency_id=:agency AND (vehicle_id=:vehicle OR contract_id=:contract)',['agency'=>$agency,'vehicle'=>$ctx['vehicle'],'contract'=>$ctx['contract']])['n'],
        'payment_adjustments'=>(int)dbFetchOne('SELECT COUNT(*) n FROM payment_adjustments pa JOIN payments p ON p.id=pa.payment_id AND p.agency_id=pa.agency_id WHERE pa.agency_id=:agency AND (p.reservation_id=:reservation OR p.contract_id=:contract)',$reservation)['n'],
        'deposit_events'=>(int)dbFetchOne('SELECT COUNT(*) n FROM deposit_events de JOIN deposits d ON d.id=de.deposit_id AND d.agency_id=de.agency_id WHERE de.agency_id=:agency AND (d.reservation_id=:reservation OR d.contract_id=:contract)',$reservation)['n'],
        'cash_movements'=>(int)dbFetchOne('SELECT COUNT(*) n FROM cash_movements WHERE agency_id=:agency',['agency'=>$agency])['n'],
        'financial_number_allocations'=>(int)dbFetchOne('SELECT COUNT(*) n FROM financial_number_allocations WHERE agency_id=:agency',['agency'=>$agency])['n'],
        'finance_idempotency_keys'=>(int)dbFetchOne('SELECT COUNT(*) n FROM finance_idempotency_keys WHERE agency_id=:agency',['agency'=>$agency])['n'],
        'cash_registers'=>(int)dbFetchOne('SELECT COUNT(*) n FROM cash_registers WHERE agency_id=:agency',['agency'=>$agency])['n'],
    ];
};

try{
    $catalogue=p5b6Catalogue();
    foreach($catalogue['routes']as$route=>$definition){$assert(is_file(__DIR__.'/../'.$route),'Catalogued Phase 5B route missing: '.$route);$source=(string)file_get_contents(__DIR__.'/../'.$route);$assert(!str_contains($source,'TO'.'DO'),'Unfinished Phase 5B marker in '.$route);}
    $assert(reservationStatuses()===$catalogue['statuses']['reservation'],'Reservation vocabulary drift');
    $assert(contractLifecycleStatuses()===$catalogue['statuses']['contract'],'Contract vocabulary drift');
    $assert(vehicleStatuses()===$catalogue['statuses']['vehicle'],'Vehicle vocabulary drift');
    $assert(inspectionPhotoSlots()===$catalogue['photo_slots'],'Photo-slot vocabulary drift');
    $inspectionCheck=strtolower((string)(dbFetchOne("SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='chk_inspection_status'")['CHECK_CLAUSE']??''));
    foreach($catalogue['statuses']['inspection']as$status)$assert(str_contains($inspectionCheck,$status),'Inspection status missing from authoritative CHECK: '.$status);
    $idempotencyCheck=strtolower((string)(dbFetchOne("SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='chk_rental_idem_status'")['CHECK_CLAUSE']??''));
    foreach($catalogue['statuses']['idempotency']as$status)$assert(str_contains($idempotencyCheck,$status),'Idempotency status missing from authoritative CHECK: '.$status);
    $serviceSources=(string)file_get_contents(__DIR__.'/../app/rental_checkout_service.php').(string)file_get_contents(__DIR__.'/../app/rental_checkin_service.php');
    foreach($catalogue['operations']as$operation)$assert(str_contains($serviceSources,$operation),'Rental operation missing from services: '.$operation);

    $keys=p5b6TranslationKeys();$keys[]='contract.approved_terms';$keys=array_values(array_unique($keys));$translations=[];
    foreach(supportedLanguages()as$language){
        $translations[$language]=translationCatalogue($language);
        foreach($keys as$key){$value=$translations[$language][$key]??null;$assert(is_string($value)&&trim($value)!=='',$language.' missing or empty Phase 5B translation '.$key);$assert(is_string($value)&&mb_check_encoding($value,'UTF-8'),$language.' invalid UTF-8 in '.$key);}
    }
    foreach($keys as$key){$expected=p5b6Placeholders((string)($translations['en'][$key]??''));foreach(['fr','ar']as$language)$assert(p5b6Placeholders((string)($translations[$language][$key]??''))===$expected,$language.' placeholder mismatch for '.$key);}
    foreach(['page.contracts.description','message.contract_issued','message.contract_acknowledgement_help','message.inspection_photo_guidance','message.rental_checkout_completed','message.rental_checkin_completed','validation.checkout_failed','validation.checkin_failed','contract.approved_terms']as$key){$assert(($translations['fr'][$key]??null)!==($translations['en'][$key]??null),'French accidental English fallback for '.$key);$assert(($translations['ar'][$key]??null)!==($translations['en'][$key]??null),'Arabic accidental English fallback for '.$key);$assert(preg_match('/\p{Arabic}/u',(string)($translations['ar'][$key]??''))===1,'Arabic script missing for '.$key);}
    foreach(['fr','ar']as$language)foreach($keys as$key)$assert(!preg_match('/(?:Ã.|Â.|Ø.|Ù.)/u',(string)($translations[$language][$key]??'')),$language.' mojibake marker in '.$key);

    $rbacFixture=phase5bFixture($run.'_RBAC');$fixtures[]=$rbacFixture;
    foreach($catalogue['roles']as$role){$_SESSION=['user_id'=>$rbacFixture['users'][$role]??$rbacFixture['users'][ROLE_AGENCY_MANAGER],'role'=>$role,'agency_ids'=>[$rbacFixture['agency_id']], '_authenticated_at'=>time()];foreach($catalogue['permissions']as$permission=>$allowedRoles)$assert(can($permission)===in_array($role,$allowedRoles,true),$role.' permission mismatch for '.$permission);}

    $goodFixture=phase5bFixture($run.'_GOOD');$fixtures[]=$goodFixture;$damagedFixture=phase5bFixture($run.'_DAMAGED');$fixtures[]=$damagedFixture;
    $good=p5b6PrepareCheckout($goodFixture,'GOOD',$temporaryFiles);$damaged=p5b6PrepareCheckout($damagedFixture,'DAMAGED',$temporaryFiles);
    phase5bAs($goodFixture,ROLE_AGENCY_MANAGER);$foreignContractDenied=false;try{contractScopedDetail($damaged['contract']);}catch(InvalidArgumentException){$foreignContractDenied=true;}$assert($foreignContractDenied,'Foreign contract detail was disclosed');
    $foreignVersionDenied=false;try{contractAcknowledgementsForVersion($damaged['contract'],$damaged['version']);}catch(InvalidArgumentException){$foreignVersionDenied=true;}$assert($foreignVersionDenied,'Foreign contract version/acknowledgements were disclosed');
    $foreignPhoto=(int)$damaged['checkout_photos'][0]['id'];$foreignPhotoDenied=false;try{inspectionPhotoAuthorizeRead($foreignPhoto);}catch(InvalidArgumentException){$foreignPhotoDenied=true;}$assert($foreignPhotoDenied,'Foreign inspection photo was disclosed');
    $foreignState=dbFetchOne('SELECT r.status reservation_status,rc.status contract_status,vi.status inspection_status FROM reservations r JOIN rental_contracts rc ON rc.id=:contract JOIN vehicle_inspections vi ON vi.id=:inspection WHERE r.id=:reservation',['contract'=>$damaged['contract'],'inspection'=>$damaged['checkout'],'reservation'=>$damaged['reservation']]);
    $rejects(fn()=>rentalCheckout(['reservation_id'=>$damaged['reservation'],'contract_id'=>$damaged['contract'],'inspection_id'=>$damaged['checkout'],'idempotency_key'=>phase5bToken(),'mileage'=>'125','fuel_level'=>'50.00','handed_over_at'=>(new DateTimeImmutable('now'))->format('Y-m-d H:i:s')]),'Foreign checkout was accepted');
    $assert(dbFetchOne('SELECT r.status reservation_status,rc.status contract_status,vi.status inspection_status FROM reservations r JOIN rental_contracts rc ON rc.id=:contract JOIN vehicle_inspections vi ON vi.id=:inspection WHERE r.id=:reservation',['contract'=>$damaged['contract'],'inspection'=>$damaged['checkout'],'reservation'=>$damaged['reservation']])===$foreignState,'Foreign checkout attempt mutated data');

    dbExecute("UPDATE users SET status='inactive' WHERE id=:id",['id'=>$goodFixture['users'][ROLE_AGENCY_MANAGER]]);phase5bAs($goodFixture,ROLE_AGENCY_MANAGER);$rejects(fn()=>p5b6Checkout($good),'Inactive actor completed checkout');dbExecute("UPDATE users SET status='active' WHERE id=:id",['id'=>$goodFixture['users'][ROLE_AGENCY_MANAGER]]);
    $_SESSION=['user_id'=>$goodFixture['users'][ROLE_AGENCY_MANAGER],'role'=>ROLE_AGENCY_MANAGER,'agency_ids'=>[],'_authenticated_at'=>time()];$rejects(fn()=>p5b6Checkout($good),'No-agency actor completed checkout');

    phase5bAs($goodFixture,ROLE_AGENCY_MANAGER);$assert(p5b6Checkout($good)===$good['reservation'],'Happy-path checkout failed');
    $checkoutState=dbFetchOne('SELECT r.status reservation_status,rc.status contract_status,v.status vehicle_status,vi.status inspection_status FROM reservations r JOIN rental_contracts rc ON rc.id=:contract JOIN vehicles v ON v.id=:vehicle JOIN vehicle_inspections vi ON vi.id=:inspection WHERE r.id=:reservation',['contract'=>$good['contract'],'vehicle'=>$good['vehicle'],'inspection'=>$good['checkout'],'reservation'=>$good['reservation']]);
    $assert($checkoutState===['reservation_status'=>'active','contract_status'=>'active','vehicle_status'=>'rented','inspection_status'=>'completed'],'Happy-path checkout state mismatch');
    $good=p5b6PrepareReturn($goodFixture,$good,$temporaryFiles);$assert(p5b6Checkin($good,'good')===$good['reservation'],'Happy-path check-in failed');
    $finalState=dbFetchOne('SELECT r.status reservation_status,rc.status contract_status,v.status vehicle_status,vi.status inspection_status FROM reservations r JOIN rental_contracts rc ON rc.id=:contract JOIN vehicles v ON v.id=:vehicle JOIN vehicle_inspections vi ON vi.id=:inspection WHERE r.id=:reservation',['contract'=>$good['contract'],'vehicle'=>$good['vehicle'],'inspection'=>$good['return_inspection'],'reservation'=>$good['reservation']]);
    $assert($finalState===['reservation_status'=>'completed','contract_status'=>'completed','vehicle_status'=>'available','inspection_status'=>'completed'],'Happy-path final state mismatch');
    $reservationHistory=array_map(static fn($row)=>(string)($row['from_status']??'NULL').'->'.$row['to_status'],dbFetchAll('SELECT from_status,to_status FROM reservation_status_history WHERE reservation_id=:id ORDER BY id',['id'=>$good['reservation']]));
    $contractHistory=array_map(static fn($row)=>(string)($row['from_status']??'NULL').'->'.$row['to_status'],dbFetchAll('SELECT from_status,to_status FROM contract_status_history WHERE contract_id=:id ORDER BY id',['id'=>$good['contract']]));
    $assert($reservationHistory===['NULL->ready','ready->active','active->completed'],'Reservation history sequence mismatch');
    $assert($contractHistory===['NULL->draft','draft->issued','issued->signed','signed->active','active->completed'],'Contract history sequence mismatch');
    $auditActions=array_column(dbFetchAll('SELECT action FROM audit_logs WHERE agency_id=:agency AND ((entity_type=\'contract\' AND entity_id=:contract) OR (entity_type=\'reservation\' AND entity_id=:reservation) OR (entity_type=\'inspection\' AND entity_id IN(:checkout,:return_inspection))) ORDER BY id',['agency'=>$goodFixture['agency_id'],'contract'=>$good['contract'],'reservation'=>$good['reservation'],'checkout'=>$good['checkout'],'return_inspection'=>$good['return_inspection']]),'action');
    foreach(['contract.created','contract.issued','contract_customer_acknowledged','contract_agency_representative_acknowledged','contract_signed','rental.checkout','rental.checkin']as$action)$assert(count(array_keys($auditActions,$action,true))===1,'Happy-path audit count mismatch for '.$action);
    $idemRows=dbFetchAll('SELECT operation_type,status,COUNT(*) count FROM rental_operation_idempotency_keys WHERE origin_agency_id=:agency GROUP BY operation_type,status ORDER BY operation_type,status',['agency'=>$goodFixture['agency_id']]);
    $assert((int)dbFetchOne("SELECT COUNT(*) n FROM rental_operation_idempotency_keys WHERE origin_agency_id=:agency AND status='in_progress'",['agency'=>$goodFixture['agency_id']])['n']===0,'Happy path left in-progress idempotency');
    $lifecycleEvidence=['checkout'=>$checkoutState,'final'=>$finalState,'reservation_history'=>$reservationHistory,'contract_history'=>$contractHistory,'audit'=>$auditActions,'idempotency'=>$idemRows];

    phase5bAs($damagedFixture,ROLE_AGENCY_MANAGER);$assert(p5b6Checkout($damaged)===$damaged['reservation'],'Damaged lifecycle checkout failed');$damaged=p5b6PrepareReturn($damagedFixture,$damaged,$temporaryFiles);
    foreach(['vehicle_damages','accidents','fines','maintenance_records']as$table)$damagedBefore[$table]=(int)dbFetchOne("SELECT COUNT(*) n FROM $table WHERE vehicle_id=:vehicle",['vehicle'=>$damaged['vehicle']])['n'];$damagedBefore['finance']=$financialCounters($damagedFixture,$damaged);
    $assert(p5b6Checkin($damaged,'damaged')===$damaged['reservation'],'Damaged lifecycle check-in failed');
    foreach(['vehicle_damages','accidents','fines','maintenance_records']as$table)$damagedAfter[$table]=(int)dbFetchOne("SELECT COUNT(*) n FROM $table WHERE vehicle_id=:vehicle",['vehicle'=>$damaged['vehicle']])['n'];$damagedAfter['finance']=$financialCounters($damagedFixture,$damaged);
    $damagedState=dbFetchOne('SELECT r.status reservation_status,rc.status contract_status,v.status vehicle_status,vi.status inspection_status FROM reservations r JOIN rental_contracts rc ON rc.id=:contract JOIN vehicles v ON v.id=:vehicle JOIN vehicle_inspections vi ON vi.id=:inspection WHERE r.id=:reservation',['contract'=>$damaged['contract'],'vehicle'=>$damaged['vehicle'],'inspection'=>$damaged['return_inspection'],'reservation'=>$damaged['reservation']]);
    $assert($damagedState===['reservation_status'=>'completed','contract_status'=>'completed','vehicle_status'=>'damaged','inspection_status'=>'completed'],'Damaged lifecycle final state mismatch');$assert($damagedAfter===$damagedBefore,'Damaged lifecycle created operational or financial side effects');

    $cancelFixture=phase5bFixture($run.'_CANCEL');$fixtures[]=$cancelFixture;phase5bAs($cancelFixture,ROLE_AGENCY_MANAGER);$cancelReservation=phase5bCreateReservation($cancelFixture,'CANCEL','confirmed');$cancelContract=contractCreateFromReservation(['reservation_id'=>$cancelReservation,'idempotency_key'=>phase5bToken()]);$assert(contractCancel(['contract_id'=>$cancelContract,'reason'=>'Phase 5B.6 permitted cancellation','idempotency_key'=>phase5bToken()])===$cancelContract,'Permitted contract cancellation failed');$assert(dbFetchOne('SELECT status FROM rental_contracts WHERE id=:id',['id'=>$cancelContract])['status']==='cancelled','Cancelled contract did not enter cancelled state');
}catch(Throwable $exception){$failures[]='Unexpected Phase 5B.6 consolidation failure: '.$exception->getMessage();}
finally{
    foreach($temporaryFiles as$file)if(is_file($file)&&!is_link($file))@unlink($file);
    foreach(array_reverse($fixtures)as$fixture){try{p5b6RemoveFixtureFiles($fixture);phase5bCleanup($fixture);}catch(Throwable $exception){$failures[]='Phase 5B.6 cleanup failed: '.$exception->getMessage();}}
    inspectionPhotoPruneEmptyControlledDirectories();
}

$inventoryAfter=p5b6RuntimeInventory();$newInventory=array_values(array_diff($inventoryAfter,$inventoryBefore));$assert($newInventory===[],'Phase 5B.6 left controlled runtime files: '.implode(',',$newInventory));
$dbResidue=[
    'agencies'=>(int)dbFetchOne("SELECT COUNT(*) n FROM agencies WHERE name LIKE 'P5B6\\_%' ESCAPE '\\\\'")['n'],
    'reservations'=>(int)dbFetchOne("SELECT COUNT(*) n FROM reservations WHERE reference LIKE 'P5B6\\_%' ESCAPE '\\\\'")['n'],
    'contracts'=>(int)dbFetchOne("SELECT COUNT(*) n FROM rental_contracts rc JOIN reservations r ON r.id=rc.reservation_id WHERE r.reference LIKE 'P5B6\\_%' ESCAPE '\\\\'")['n'],
    'versions'=>(int)dbFetchOne("SELECT COUNT(*) n FROM contract_versions cv JOIN rental_contracts rc ON rc.id=cv.contract_id JOIN reservations r ON r.id=rc.reservation_id WHERE r.reference LIKE 'P5B6\\_%' ESCAPE '\\\\'")['n'],
    'acknowledgements'=>(int)dbFetchOne("SELECT COUNT(*) n FROM contract_acknowledgements ca JOIN rental_contracts rc ON rc.id=ca.contract_id JOIN reservations r ON r.id=rc.reservation_id WHERE r.reference LIKE 'P5B6\\_%' ESCAPE '\\\\'")['n'],
    'inspections'=>(int)dbFetchOne("SELECT COUNT(*) n FROM vehicle_inspections vi JOIN reservations r ON r.id=vi.reservation_id WHERE r.reference LIKE 'P5B6\\_%' ESCAPE '\\\\'")['n'],
    'photos'=>(int)dbFetchOne("SELECT COUNT(*) n FROM inspection_photos ip JOIN vehicle_inspections vi ON vi.id=ip.inspection_id JOIN reservations r ON r.id=vi.reservation_id WHERE r.reference LIKE 'P5B6\\_%' ESCAPE '\\\\'")['n'],
    'checkout_in_progress'=>(int)dbFetchOne("SELECT COUNT(*) n FROM rental_operation_idempotency_keys WHERE operation_type='rental_checkout' AND status='in_progress'")['n'],
    'checkin_in_progress'=>(int)dbFetchOne("SELECT COUNT(*) n FROM rental_operation_idempotency_keys WHERE operation_type='rental_checkin' AND status='in_progress'")['n'],
];
foreach($dbResidue as$name=>$count)$assert($count===0,'Phase 5B.6 DB residue '.$name.'='.$count);
if($failures){foreach(array_unique($failures)as$message)fwrite(STDERR,"FAIL: $message\n");exit(1);}
echo 'Phase 5B.6 catalogue: '.json_encode(['routes'=>p5b6Catalogue()['routes'],'permissions'=>p5b6Catalogue()['permissions'],'photo_slots'=>p5b6Catalogue()['photo_slots']],JSON_UNESCAPED_SLASHES).".\n";
echo 'Phase 5B.6 translation parity: languages=en,fr,ar, keys='.count($keys).', missing=0, empty=0, placeholder_mismatches=0, mojibake=0.' ."\n";
echo 'Phase 5B.6 happy lifecycle: '.json_encode($lifecycleEvidence,JSON_UNESCAPED_SLASHES).".\n";
echo 'Phase 5B.6 damaged lifecycle: before='.json_encode($damagedBefore,JSON_UNESCAPED_SLASHES).', after='.json_encode($damagedAfter,JSON_UNESCAPED_SLASHES).', state='.json_encode($damagedState,JSON_UNESCAPED_SLASHES).".\n";
echo 'Phase 5B.6 cleanup: runtime_before='.count($inventoryBefore).', runtime_after='.count($inventoryAfter).', runtime_new='.count($newInventory).', db='.json_encode($dbResidue,JSON_UNESCAPED_SLASHES).".\n";
echo "Phase 5B.6 consolidation tests passed: catalogue, translation parity, RBAC, agency isolation, full lifecycle, damaged-return non-effects, histories, audit, idempotency, and cleanup.\n";
