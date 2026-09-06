<?php
require_once __DIR__ . '/_layout.php';
requirePermission('vehicles.manage');
$agencyIds = currentAgencyIds();

if (requestMethod() === 'POST') {
    requireCsrfPost();
    try {
        $agencyId=(int)($_POST['agency_id']??0); requireAgencyAccess($agencyId);
        $categoryId=(int)($_POST['category_id']??0);
        $category=dbFetchOne('SELECT id FROM vehicle_categories WHERE id=:id AND (agency_id=:agency OR agency_id IS NULL) AND archived_at IS NULL',['id'=>$categoryId,'agency'=>$agencyId]);
        if(!$category) throw new InvalidArgumentException(t('validation.valid_vehicle_category'));
        $registration=strtoupper(trim((string)($_POST['registration_number']??'')));
        $brand=trim((string)($_POST['brand']??'')); $model=trim((string)($_POST['model']??''));
        $price=positiveMoney($_POST['base_daily_price']??'');
        if($registration===''||$brand===''||$model===''||$price===null||moneyToCents($price)<=0) throw new InvalidArgumentException(t('validation.vehicle_required_fields'));
        $stored = null;
        try {
            withTransaction(function () use ($agencyId,$categoryId,$registration,$brand,$model,$price,&$stored) {
                if(isset($_FILES['image'])&&($_FILES['image']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) $stored=storeValidatedImage($_FILES['image'],'vehicle_media');
                dbExecute('INSERT INTO vehicles (agency_id,category_id,registration_number,vin,brand,model,version,model_year,colour,fuel,transmission,seats,doors,luggage_capacity,current_mileage,base_daily_price,recommended_deposit,status,primary_image_path,created_by,updated_by) VALUES (:agency,:category,:registration,:vin,:brand,:model,:version,:year,:colour,:fuel,:transmission,:seats,:doors,:luggage,:mileage,:daily_price,:deposit,\'available\',:image,:creator,:updater)',[
                    'agency'=>$agencyId,'category'=>$categoryId,'registration'=>$registration,'vin'=>strtoupper(trim((string)($_POST['vin']??'')))?:null,'brand'=>$brand,'model'=>$model,'version'=>trim((string)($_POST['version']??''))?:null,'year'=>($_POST['model_year']??'')!==''?(int)$_POST['model_year']:null,'colour'=>trim((string)($_POST['colour']??''))?:null,'fuel'=>validateChoice($_POST['fuel']??'', ['petrol','diesel','hybrid','electric','other'],null),'transmission'=>validateChoice($_POST['transmission']??'', ['manual','automatic'],'manual'),'seats'=>(int)($_POST['seats']??0)?:null,'doors'=>(int)($_POST['doors']??0)?:null,'luggage'=>(int)($_POST['luggage_capacity']??0)?:null,'mileage'=>max(0,(int)($_POST['current_mileage']??0)),'daily_price'=>$price,'deposit'=>positiveMoney($_POST['recommended_deposit']??'0')??'0.00','image'=>$stored['path']??null,'creator'=>currentUserId(),'updater'=>currentUserId()
                ]);
                $id=(int)db()->lastInsertId();
                if($stored) insertVehicleMedia(['id'=>$id,'agency_id'=>$agencyId],$stored,10,true);
                dbExecute("INSERT INTO vehicle_status_history(vehicle_id,to_status,reason,changed_by) VALUES(:id,'available',:reason,:user)",['id'=>$id,'reason'=>t('message.vehicle_created'),'user'=>currentUserId()]);
                auditLog('vehicle.created','vehicle',$id,null,['registration'=>$registration],$agencyId);
            });
        } catch (Throwable $exception) {
            if ($stored) removeNewStoredUpload($stored['path']);
            throw $exception;
        }
        flash('success',t('message.vehicle_created'));
        safeRedirect('vehicles.php');
    } catch(InvalidArgumentException|DomainException $exception) {
        flash('danger',$exception->getMessage());
    } catch(Throwable $exception) {
        reportDatabaseError($exception,'Vehicle operation failed'); flash('danger',t('message.vehicle_failed'));
    }
    safeRedirect('vehicle_form.php');
}

$agencies=dbFetchAll('SELECT id,name FROM agencies WHERE archived_at IS NULL ORDER BY name');
$categories=dbFetchAll('SELECT id,name,agency_id FROM vehicle_categories WHERE archived_at IS NULL ORDER BY name');

backofficeHeader(t('page.vehicle_create.title'), 'vehicles.php');
pageHeader('page.vehicle_create.title', 'page.vehicle_create.description', [
    'breadcrumbs' => [['label'=>'nav.fleet'],['label'=>'nav.vehicles','href'=>'vehicles.php'],['label'=>'page.vehicle_create.title']],
    'secondary' => ['label'=>'common.cancel','href'=>'vehicles.php'],
]);
?>
<section class="card form-card">
    <form method="post" enctype="multipart/form-data">
        <?=csrfField()?>
        <input type="hidden" name="action" value="create">
        <div class="grid">
            <label><?=e(t('field.agency'))?><select name="agency_id" required><?php foreach($agencies as $agency):if(currentUserRole()!==ROLE_OWNER&&!in_array((int)$agency['id'],$agencyIds,true))continue;?><option value="<?=e($agency['id'])?>"><?=e($agency['name'])?></option><?php endforeach;?></select></label>
            <label><?=e(t('field.category'))?><select name="category_id" required><?php foreach($categories as $category):?><option value="<?=e($category['id'])?>"><?=e($category['name'])?></option><?php endforeach;?></select></label>
            <label><?=e(t('field.registration'))?><input name="registration_number" required></label>
            <label><?=e(t('field.vin'))?><input name="vin"></label>
            <label><?=e(t('field.brand'))?><input name="brand" required></label>
            <label><?=e(t('field.model'))?><input name="model" required></label>
            <label><?=e(t('field.version'))?><input name="version"></label>
            <label><?=e(t('field.year'))?><input type="number" name="model_year" min="1900" max="<?=e((int)date('Y')+1)?>"></label>
            <label><?=e(t('field.colour'))?><input name="colour"></label>
            <label><?=e(t('field.fuel'))?><select name="fuel"><?php foreach(['petrol','diesel','hybrid','electric','other'] as $fuel):?><option value="<?=e($fuel)?>"><?=e(t('option.'.$fuel))?></option><?php endforeach;?></select></label>
            <label><?=e(t('field.transmission'))?><select name="transmission"><?php foreach(['manual','automatic'] as $transmission):?><option value="<?=e($transmission)?>"><?=e(t('option.'.$transmission))?></option><?php endforeach;?></select></label>
            <label><?=e(t('field.seats'))?><input type="number" name="seats" min="1" max="99"></label>
            <label><?=e(t('field.doors'))?><input type="number" name="doors" min="1" max="10"></label>
            <label><?=e(t('field.luggage'))?><input type="number" name="luggage_capacity" min="0" max="99"></label>
            <label><?=e(t('field.mileage'))?><input type="number" name="current_mileage" min="0"></label>
            <label><?=e(t('field.daily_price'))?><input name="base_daily_price" type="number" step="0.01" inputmode="decimal" required></label>
            <label><?=e(t('field.deposit'))?><input name="recommended_deposit" type="number" step="0.01" inputmode="decimal"></label>
            <label class="full"><?=e(t('field.vehicle_image',['size'=>formatFileSize(appConfig('upload_max_bytes'))]))?><input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
        </div>
        <div class="form-actions">
            <button class="btn primary"><?=e(t('common.save'))?></button>
            <a class="btn secondary" href="vehicles.php"><?=e(t('common.cancel'))?></a>
        </div>
    </form>
</section>
<?php backofficeFooter();
