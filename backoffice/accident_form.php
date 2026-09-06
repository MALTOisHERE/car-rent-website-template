<?php
require_once __DIR__ . '/_layout.php';
requirePermission('vehicle_damages.view');

if (requestMethod() === 'POST') {
    requireCsrfPost();
    try {
        $vehicle = dbFetchOne('SELECT * FROM vehicles WHERE id=:id', ['id' => (int) ($_POST['vehicle_id'] ?? 0)]);
        if (!$vehicle) {
            throw new InvalidArgumentException(t('validation.vehicle_not_found'));
        }
        requireAgencyAccess($vehicle['agency_id']);
        $report = null;
        if (isset($_FILES['report']) && ($_FILES['report']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $report = storeValidatedDocument($_FILES['report'], 'accidents')['path'];
        }
        dbExecute('INSERT INTO accidents(vehicle_id,contract_id,customer_id,occurred_at,location,description,driver_name,accident_report_path,insurance_claim_number,insurance_excess,estimated_cost,customer_charge,replacement_vehicle_id,status,created_by)VALUES(:vehicle,:contract,:customer,:occurred,:location,:description,:driver,:report,:claim,:excess,:estimated,:charge,:replacement,\'reported\',:user)', ['vehicle'=>$vehicle['id'],'contract'=>($_POST['contract_id']??'')?:null,'customer'=>($_POST['customer_id']??'')?:null,'occurred'=>$_POST['occurred_at'],'location'=>trim((string)($_POST['location']??'')),'description'=>trim((string)($_POST['description']??'')),'driver'=>trim((string)($_POST['driver_name']??'')),'report'=>$report,'claim'=>trim((string)($_POST['insurance_claim_number']??'')),'excess'=>positiveMoney($_POST['insurance_excess']??'0')??'0.00','estimated'=>positiveMoney($_POST['estimated_cost']??'0')??'0.00','charge'=>positiveMoney($_POST['customer_charge']??'0')??'0.00','replacement'=>($_POST['replacement_vehicle_id']??'')?:null,'user'=>currentUserId()]);
        $id = (int) db()->lastInsertId();
        dbExecute("UPDATE vehicles SET status='damaged',updated_by=:user WHERE id=:id", ['user'=>currentUserId(),'id'=>$vehicle['id']]);
        auditLog('accident.created', 'accident', $id, null, ['vehicle_id'=>$vehicle['id']], $vehicle['agency_id']);
        flash('success', t('message.accident_recorded'));
        safeRedirect('incidents.php');
    } catch (InvalidArgumentException|DomainException $exception) {
        flash('danger', $exception->getMessage());
    } catch (Throwable $exception) {
        reportDatabaseError($exception, 'Incident operation failed');
        flash('danger', t('message.incident_failed'));
    }
    safeRedirect('accident_form.php');
}

$ids = currentAgencyIds();
if (!$ids) $ids = [0];
$ph = implode(',', array_fill(0, count($ids), '?'));
$vehicles = dbFetchAll("SELECT id,registration_number,brand,model FROM vehicles WHERE agency_id IN ($ph) AND archived_at IS NULL ORDER BY registration_number", $ids);
$contracts = dbFetchAll("SELECT rc.id,rc.contract_number,r.customer_id,r.vehicle_id FROM rental_contracts rc JOIN reservations r ON r.id=rc.reservation_id WHERE r.agency_id IN ($ph) ORDER BY rc.created_at DESC LIMIT 200", $ids);

backofficeHeader(t('page.accident_create.title'), 'incidents.php');
pageHeader('page.accident_create.title', 'page.accident_create.description', [
    'breadcrumbs' => [['label'=>'nav.fleet'],['label'=>'nav.incidents','href'=>'incidents.php'],['label'=>'page.accident_create.title']],
    'secondary' => ['label'=>'common.cancel','href'=>'incidents.php'],
]);
?>
<section class="card form-card">
    <form method="post" enctype="multipart/form-data">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="accident">
        <div class="grid">
            <label><?= e(t('field.vehicle')) ?><select name="vehicle_id"><?php foreach ($vehicles as $v): ?><option value="<?= e($v['id']) ?>"><?= e($v['registration_number'] . ' ' . $v['brand'] . ' ' . $v['model']) ?></option><?php endforeach; ?></select></label>
            <label><?= e(t('field.contract')) ?><select name="contract_id"><option value=""><?= e(t('common.unassigned')) ?></option><?php foreach ($contracts as $c): ?><option value="<?= e($c['id']) ?>"><?= e($c['contract_number']) ?></option><?php endforeach; ?></select></label>
            <label><?= e(t('field.date_time')) ?><input type="datetime-local" name="occurred_at" required></label>
            <label><?= e(t('field.location')) ?><input name="location"></label>
            <label><?= e(t('field.driver')) ?><input name="driver_name"></label>
            <label class="full"><?= e(t('field.description')) ?><textarea name="description" required></textarea></label>
            <label><?= e(t('field.insurance_claim')) ?><input name="insurance_claim_number"></label>
            <label><?= e(t('field.insurance_excess')) ?><input type="number" step="0.01" name="insurance_excess" value="0.00"></label>
            <label><?= e(t('field.estimated_cost')) ?><input type="number" step="0.01" name="estimated_cost" value="0.00"></label>
            <label><?= e(t('field.customer_charge')) ?><input type="number" step="0.01" name="customer_charge" value="0.00"></label>
            <label><?= e(t('field.report')) ?><input type="file" name="report" accept="application/pdf,image/jpeg,image/png,image/webp"></label>
        </div>
        <div class="form-actions">
            <button class="btn danger"><?= e(t('action.record_accident')) ?></button>
            <a class="btn secondary" href="incidents.php"><?= e(t('common.cancel')) ?></a>
        </div>
    </form>
</section>
<?php backofficeFooter();
