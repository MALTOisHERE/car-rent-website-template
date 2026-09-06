<?php
require_once __DIR__ . '/_layout.php';
requirePermission('maintenance.manage');

if (requestMethod() === 'POST') {
    requireCsrfPost();
    try {
        $vehicle = dbFetchOne('SELECT * FROM vehicles WHERE id=:id', ['id' => (int) $_POST['vehicle_id']]);
        if (!$vehicle) {
            throw new InvalidArgumentException(t('validation.vehicle_not_found'));
        }
        requireAgencyAccess($vehicle['agency_id']);
        dbExecute('INSERT INTO maintenance_records(vehicle_id,maintenance_type,description,garage,supplier,current_mileage,scheduled_mileage,scheduled_date,entry_at,estimated_exit_at,cost,replaced_parts,next_maintenance_date,next_maintenance_mileage,status,created_by,updated_by)VALUES(:vehicle,:type,:description,:garage,:supplier,:mileage,:scheduled_mileage,:scheduled_date,:entry_at,:estimated_exit,:cost,:parts,:next_date,:next_mileage,:status,:user,:user2)', ['vehicle'=>$vehicle['id'],'type'=>validateChoice($_POST['maintenance_type']??'',['oil_change','filters','tyres','brakes','battery','air_conditioning','technical_inspection','repair','other'],'other'),'description'=>trim((string)($_POST['description']??'')),'garage'=>trim((string)($_POST['garage']??'')),'supplier'=>trim((string)($_POST['supplier']??'')),'mileage'=>$vehicle['current_mileage'],'scheduled_mileage'=>(int)($_POST['scheduled_mileage']??0)?:null,'scheduled_date'=>($_POST['scheduled_date']??'')?:null,'entry_at'=>($_POST['entry_at']??'')?:null,'estimated_exit'=>($_POST['estimated_exit_at']??'')?:null,'cost'=>positiveMoney($_POST['cost']??'0')??'0.00','parts'=>trim((string)($_POST['replaced_parts']??'')),'next_date'=>($_POST['next_maintenance_date']??'')?:null,'next_mileage'=>(int)($_POST['next_maintenance_mileage']??0)?:null,'status'=>validateChoice($_POST['status']??'',['scheduled','in_progress'],'scheduled'),'user'=>currentUserId(),'user2'=>currentUserId()]);
        $id = (int) db()->lastInsertId();
        if ($_POST['status'] === 'in_progress') {
            dbExecute("UPDATE vehicles SET status='maintenance',updated_by=:user WHERE id=:id", ['user'=>currentUserId(),'id'=>$vehicle['id']]);
        }
        auditLog('maintenance.created', 'maintenance_record', $id, null, ['vehicle_id'=>$vehicle['id'],'status'=>$_POST['status']], $vehicle['agency_id']);
        flash('success', t('message.maintenance_created'));
        safeRedirect('maintenance.php');
    } catch (InvalidArgumentException|DomainException $exception) {
        flash('danger', $exception->getMessage());
    } catch (Throwable $exception) {
        reportDatabaseError($exception, 'Maintenance operation failed');
        flash('danger', t('message.maintenance_failed'));
    }
    safeRedirect('maintenance_form.php');
}

$ids = currentAgencyIds();
if (!$ids) $ids = [0];
$ph = implode(',', array_fill(0, count($ids), '?'));
$vehicles = dbFetchAll("SELECT id,registration_number,brand,model,current_mileage FROM vehicles WHERE agency_id IN ($ph) AND archived_at IS NULL ORDER BY registration_number", $ids);

backofficeHeader(t('page.maintenance_create.title'), 'maintenance.php');
pageHeader('page.maintenance_create.title', 'page.maintenance_create.description', [
    'breadcrumbs' => [['label'=>'nav.fleet'],['label'=>'nav.maintenance','href'=>'maintenance.php'],['label'=>'page.maintenance_create.title']],
    'secondary' => ['label'=>'common.cancel','href'=>'maintenance.php'],
]);
?>
<section class="card form-card">
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create">
        <div class="grid">
            <label><?= e(t('field.vehicle')) ?><select name="vehicle_id"><?php foreach ($vehicles as $v): ?><option value="<?= e($v['id']) ?>"><?= e($v['registration_number'] . ' - ' . $v['brand'] . ' ' . $v['model']) ?></option><?php endforeach; ?></select></label>
            <label><?= e(t('field.type')) ?><select name="maintenance_type"><?php foreach (['oil_change','filters','tyres','brakes','battery','air_conditioning','technical_inspection','repair','other'] as $type): ?><option value="<?= e($type) ?>"><?= e(t('option.' . $type)) ?></option><?php endforeach; ?></select></label>
            <label><?= e(t('common.status')) ?><select name="status"><option value="scheduled"><?= e(translatedStatus('scheduled')) ?></option><option value="in_progress"><?= e(translatedStatus('in_progress')) ?></option></select></label>
            <label><?= e(t('field.date')) ?><input type="date" name="scheduled_date"></label>
            <label><?= e(t('field.start_date')) ?><input type="datetime-local" name="entry_at"></label>
            <label><?= e(t('field.end_date')) ?><input type="datetime-local" name="estimated_exit_at"></label>
            <label><?= e(t('field.garage')) ?><input name="garage"></label>
            <label><?= e(t('field.supplier')) ?><input name="supplier"></label>
            <label><?= e(t('field.cost')) ?><input name="cost" type="number" step="0.01" inputmode="decimal"></label>
            <label class="full"><?= e(t('field.description')) ?><textarea name="description"></textarea></label>
            <label><?= e(t('field.next_maintenance_date')) ?><input type="date" name="next_maintenance_date"></label>
            <label><?= e(t('field.next_mileage')) ?><input type="number" name="next_maintenance_mileage"></label>
        </div>
        <div class="form-actions">
            <button class="btn primary"><?= e(t('action.save_maintenance')) ?></button>
            <a class="btn secondary" href="maintenance.php"><?= e(t('common.cancel')) ?></a>
        </div>
    </form>
</section>
<?php backofficeFooter();
