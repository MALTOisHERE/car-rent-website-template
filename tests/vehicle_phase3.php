<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/application.php';

$failures=[];
$assert=function($condition,$message)use(&$failures){if(!$condition)$failures[]=$message;};
$requiredColumns=['agency_id','vehicle_id','caption','alt_text','storage_path','mime_type','file_size','width','height','sort_order','is_primary','archived_at','created_by','updated_by','archived_by','primary_slot'];
$columns=array_column(dbFetchAll("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='vehicle_media'"),'column_name');
foreach($requiredColumns as $column)$assert(in_array($column,$columns,true),'vehicle_media column missing: '.$column);
$indexes=array_column(dbFetchAll("SELECT DISTINCT index_name FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='vehicle_media'"),'index_name');
$assert(in_array('uq_vehicle_media_active_primary',$indexes,true),'Active-primary uniqueness index is missing');
$foreignKeys=array_column(dbFetchAll("SELECT constraint_name FROM information_schema.table_constraints WHERE table_schema=DATABASE() AND table_name='vehicle_media' AND constraint_type='FOREIGN KEY'"),'constraint_name');
$assert(in_array('fk_vehicle_media_vehicle_agency',$foreignKeys,true),'Composite vehicle/agency foreign key is missing');

$vehicle=dbFetchOne('SELECT * FROM vehicles WHERE archived_at IS NULL ORDER BY id LIMIT 1');
if(!$vehicle){fwrite(STDERR,"FAIL: No active vehicle is available for integration verification.\n");exit(1);}
$_SESSION['user_id']=$vehicle['updated_by']?:$vehicle['created_by'];
if(!$_SESSION['user_id'])$_SESSION['user_id']=(int)dbFetchOne("SELECT id FROM users WHERE role='OWNER' AND status='active' ORDER BY id LIMIT 1")['id'];
$_SESSION['role']=ROLE_OWNER;$_SESSION['agency_ids']=[];
$pdo=db();$pdo->beginTransaction();
try{
    $base='storage/uploads/vehicle_media/integration-'.bin2hex(random_bytes(8));
    $state=dbFetchOne('SELECT COALESCE(MAX(sort_order),0) maximum FROM vehicle_media WHERE vehicle_id=:vehicle AND archived_at IS NULL',['vehicle'=>$vehicle['id']]);
    $first=insertVehicleMedia($vehicle,['path'=>$base.'-1.jpg','original_name'=>'integration-1.jpg','mime_type'=>'image/jpeg','size'=>100,'width'=>10,'height'=>10],(int)$state['maximum']+10,false);
    $second=insertVehicleMedia($vehicle,['path'=>$base.'-2.jpg','original_name'=>'integration-2.jpg','mime_type'=>'image/jpeg','size'=>100,'width'=>10,'height'=>10],(int)$state['maximum']+20,false);
    updateVehicleMediaText($vehicle['id'],$first,'Front view','Vehicle front view');
    $updated=dbFetchOne('SELECT caption,alt_text FROM vehicle_media WHERE id=:id',['id'=>$first]);
    $assert($updated['caption']==='Front view'&&$updated['alt_text']==='Vehicle front view','Media caption/alternative-text update failed');
    setPrimaryVehicleMedia($vehicle['id'],$first);
    $primary=dbFetchOne('SELECT vm.id,v.primary_image_path FROM vehicle_media vm JOIN vehicles v ON v.id=vm.vehicle_id WHERE vm.id=:id',['id'=>$first]);
    $assert((int)$primary['id']===$first&&$primary['primary_image_path']===$base.'-1.jpg','Primary media compatibility mirror failed');
    try{dbExecute('UPDATE vehicle_media SET is_primary=1 WHERE id=:id',['id'=>$second]);$assert(false,'Database allowed two active primary images');}catch(PDOException $exception){$assert(true,'Primary uniqueness enforced');}
    $pdo->rollBack();$pdo->beginTransaction();
    $vehicle=dbFetchOne('SELECT * FROM vehicles WHERE id=:id',['id'=>$vehicle['id']]);
    dbExecute('UPDATE vehicle_media SET is_primary=0,archived_at=COALESCE(archived_at,NOW(6)),archived_by=NULL WHERE vehicle_id=:vehicle AND agency_id=:agency',['vehicle'=>$vehicle['id'],'agency'=>$vehicle['agency_id']]);
    dbExecute('UPDATE vehicles SET primary_image_path=NULL WHERE id=:id',['id'=>$vehicle['id']]);

    $existingPrimary=insertVehicleMedia($vehicle,['path'=>$base.'-3.jpg','original_name'=>'integration-3.jpg','mime_type'=>'image/jpeg','size'=>100,'width'=>10,'height'=>10],10,false);
    $activeTail=insertVehicleMedia($vehicle,['path'=>$base.'-4.jpg','original_name'=>'integration-4.jpg','mime_type'=>'image/jpeg','size'=>100,'width'=>10,'height'=>10],20,false);
    $restoreWithPrimary=insertVehicleMedia($vehicle,['path'=>$base.'-5.jpg','original_name'=>'integration-5.jpg','mime_type'=>'image/jpeg','size'=>100,'width'=>10,'height'=>10],30,false);
    dbExecute('UPDATE vehicle_media SET archived_at=NOW(6),archived_by=:user WHERE id=:id',['user'=>currentUserId(),'id'=>$restoreWithPrimary]);
    setPrimaryVehicleMedia($vehicle['id'],$existingPrimary);
    restoreVehicleMedia($vehicle['id'],$restoreWithPrimary);
    $preserved=dbFetchOne('SELECT vm.is_primary,vm.sort_order,v.primary_image_path FROM vehicle_media vm JOIN vehicles v ON v.id=vm.vehicle_id WHERE vm.id=:id',['id'=>$restoreWithPrimary]);
    $primaryAfterRestore=dbFetchOne('SELECT is_primary FROM vehicle_media WHERE id=:id',['id'=>$existingPrimary]);
    $assert((int)$primaryAfterRestore['is_primary']===1,'Restoring into a non-empty gallery altered the existing primary');
    $assert((int)$preserved['is_primary']===0&&$preserved['primary_image_path']===$base.'-3.jpg','Restoring into a non-empty gallery replaced the compatibility primary');
    $assert((int)$preserved['sort_order']>20,'Restored media was not appended after all active media');

    moveVehicleMedia($vehicle['id'],$activeTail,'up');
    $ordered=array_map('intval',array_column(vehicleMediaRows($vehicle['id']),'id'));
    $assert(array_search($activeTail,$ordered,true)<array_search($existingPrimary,$ordered,true),'Accessible media reordering failed');

    dbExecute('UPDATE vehicle_media SET is_primary=0,archived_at=COALESCE(archived_at,NOW(6)),archived_by=NULL WHERE vehicle_id=:vehicle AND agency_id=:agency',['vehicle'=>$vehicle['id'],'agency'=>$vehicle['agency_id']]);
    dbExecute('UPDATE vehicles SET primary_image_path=NULL WHERE id=:id',['id'=>$vehicle['id']]);
    $restoreIntoEmpty=insertVehicleMedia($vehicle,['path'=>$base.'-6.jpg','original_name'=>'integration-6.jpg','mime_type'=>'image/jpeg','size'=>100,'width'=>10,'height'=>10],70,false);
    dbExecute('UPDATE vehicle_media SET archived_at=NOW(6),archived_by=:user WHERE id=:id',['user'=>currentUserId(),'id'=>$restoreIntoEmpty]);
    restoreVehicleMedia($vehicle['id'],$restoreIntoEmpty);
    $emptyRestore=dbFetchOne('SELECT vm.archived_at,vm.is_primary,vm.sort_order,v.primary_image_path FROM vehicle_media vm JOIN vehicles v ON v.id=vm.vehicle_id WHERE vm.id=:id',['id'=>$restoreIntoEmpty]);
    $assert($emptyRestore['archived_at']===null&&(int)$emptyRestore['is_primary']===0,'Restoring into an empty gallery selected a primary automatically');
    $assert($emptyRestore['primary_image_path']===null,'Compatibility path was populated without explicit primary selection');
    $assert((int)$emptyRestore['sort_order']===10,'Restored media was not appended at the end of the empty active gallery');
    setPrimaryVehicleMedia($vehicle['id'],$restoreIntoEmpty);
    $explicitPrimary=dbFetchOne('SELECT vm.is_primary,v.primary_image_path FROM vehicle_media vm JOIN vehicles v ON v.id=vm.vehicle_id WHERE vm.id=:id',['id'=>$restoreIntoEmpty]);
    $assert((int)$explicitPrimary['is_primary']===1&&$explicitPrimary['primary_image_path']===$base.'-6.jpg','Explicit primary selection after restoration failed');
    $restoreAudit=(int)dbFetchOne("SELECT COUNT(*) total FROM audit_logs WHERE action='vehicle.media_restored' AND entity_type='vehicle_media' AND entity_id IN (:first,:second)",['first'=>(string)$restoreWithPrimary,'second'=>(string)$restoreIntoEmpty])['total'];
    $assert($restoreAudit===2,'Restore audit logging was not preserved');

    $profileInput=function(array $record,$financingType,$monthlyAmount=''){
        return ['updated_at'=>$record['updated_at'],'category_id'=>(string)$record['category_id'],'registration_number'=>$record['registration_number'],'vin'=>$record['vin']??'','brand'=>$record['brand'],'model'=>$record['model'],'version'=>$record['version']??'','model_year'=>$record['model_year']??'','colour'=>$record['colour']??'','fuel'=>$record['fuel']??'','transmission'=>$record['transmission'],'seats'=>$record['seats']??'','doors'=>$record['doors']??'','luggage_capacity'=>$record['luggage_capacity']??'','current_mileage'=>(string)$record['current_mileage'],'mileage_allowance'=>$record['mileage_allowance']??'','purchase_date'=>$record['purchase_date']??'','purchase_price'=>$record['purchase_price']??'','financing_type'=>$financingType,'monthly_finance_amount'=>$monthlyAmount,'base_daily_price'=>$record['base_daily_price'],'recommended_deposit'=>$record['recommended_deposit'],'mileage_correction_reason'=>''];
    };
    foreach([['','Empty financing type'],['invalid','Invalid financing type']] as[$financingType,$label]){
        $current=dbFetchOne('SELECT * FROM vehicles WHERE id=:id',['id'=>$vehicle['id']]);
        try{updateVehicleProfile($vehicle['id'],$profileInput($current,$financingType));$assert(false,$label.' was accepted by the database service');}catch(InvalidArgumentException $exception){$assert(true,$label.' rejected');}
    }
    foreach([['loan',''],['loan','0'],['loan','-10'],['loan','invalid'],['lease',''],['lease','0.00'],['lease','-10'],['lease','invalid']] as[$financingType,$monthly]){
        $current=dbFetchOne('SELECT * FROM vehicles WHERE id=:id',['id'=>$vehicle['id']]);
        try{updateVehicleProfile($vehicle['id'],$profileInput($current,$financingType,$monthly));$assert(false,ucfirst($financingType).' accepted a non-positive monthly amount');}catch(InvalidArgumentException $exception){$assert(true,'Invalid monthly amount rejected');}
    }
    foreach([['loan','500.00'],['lease','625.50']] as[$financingType,$monthly]){
        $current=dbFetchOne('SELECT * FROM vehicles WHERE id=:id',['id'=>$vehicle['id']]);
        updateVehicleProfile($vehicle['id'],$profileInput($current,$financingType,$monthly));
        $saved=dbFetchOne('SELECT financing_type,monthly_finance_amount FROM vehicles WHERE id=:id',['id'=>$vehicle['id']]);
        $assert($saved['financing_type']===$financingType&&$saved['monthly_finance_amount']===$monthly,ucfirst($financingType).' financing was not persisted correctly');
    }
    $current=dbFetchOne('SELECT * FROM vehicles WHERE id=:id',['id'=>$vehicle['id']]);
    updateVehicleProfile($vehicle['id'],$profileInput($current,'owned','999.00'));
    $owned=dbFetchOne('SELECT financing_type,monthly_finance_amount FROM vehicles WHERE id=:id',['id'=>$vehicle['id']]);
    $assert($owned['financing_type']==='owned'&&$owned['monthly_finance_amount']===null,'Owned financing did not clear the monthly amount');
    dbExecute('UPDATE vehicles SET financing_type=NULL WHERE id=:id',['id'=>$vehicle['id']]);
    $legacy=dbFetchOne('SELECT financing_type FROM vehicles WHERE id=:id',['id'=>$vehicle['id']]);
    $assert(vehicleFinancingTypeForDisplay($legacy['financing_type'])==='owned','Legacy NULL financing does not render as owned');

    $otherAgency=dbFetchOne('SELECT id FROM agencies WHERE id<>:agency AND archived_at IS NULL LIMIT 1',['agency'=>$vehicle['agency_id']]);
    if($otherAgency){try{dbExecute("INSERT INTO vehicle_media(agency_id,vehicle_id,storage_path,original_name,mime_type,file_size,sort_order)VALUES(:agency,:vehicle,'invalid-cross-agency.jpg','x.jpg','image/jpeg',1,1)",['agency'=>$otherAgency['id'],'vehicle'=>$vehicle['id']]);$assert(false,'Cross-agency media insert was accepted');}catch(PDOException $exception){$assert(true,'Composite agency scope enforced');}}
    try{updateVehicleProfile($vehicle['id'],['updated_at'=>'1970-01-01 00:00:00']);$assert(false,'Stale profile update was accepted');}catch(DomainException $exception){$assert(true,'Optimistic concurrency enforced');}
}finally{if($pdo->inTransaction())$pdo->rollBack();unset($_SESSION['user_id'],$_SESSION['role'],$_SESSION['agency_ids']);}

if($failures){foreach($failures as $failure)fwrite(STDERR,'FAIL: '.$failure."\n");exit(1);}
echo "Phase 3 vehicle integration tests passed: schema, agency constraint, deterministic financing, explicit-primary restoration, metadata, ordering, compatibility mirror, audit, and stale-write rejection.\n";
