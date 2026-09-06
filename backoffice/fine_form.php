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
        $amount = positiveMoney($_POST['amount'] ?? '');
        if ($amount === null) {
            throw new InvalidArgumentException(t('validation.fine_amount'));
        }
        $document = null;
        if (isset($_FILES['document']) && ($_FILES['document']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $document = storeValidatedDocument($_FILES['document'], 'fines')['path'];
        }
        dbExecute('INSERT INTO fines(vehicle_id,contract_id,customer_id,driver_name,occurred_at,fine_type,amount,administrative_fee,status,document_path,created_by)VALUES(:vehicle,:contract,:customer,:driver,:occurred,:type,:amount,:fee,\'pending\',:document,:user)', ['vehicle'=>$vehicle['id'],'contract'=>($_POST['contract_id']??'')?:null,'customer'=>($_POST['customer_id']??'')?:null,'driver'=>trim((string)($_POST['driver_name']??'')),'occurred'=>$_POST['occurred_at'],'type'=>trim((string)($_POST['fine_type']??'')),'amount'=>$amount,'fee'=>positiveMoney($_POST['administrative_fee']??'0')??'0.00','document'=>$document,'user'=>currentUserId()]);
        auditLog('fine.created', 'fine', db()->lastInsertId(), null, ['vehicle_id'=>$vehicle['id'],'amount'=>$amount], $vehicle['agency_id']);
        flash('success', t('message.fine_recorded'));
        safeRedirect('incidents.php');
    } catch (InvalidArgumentException|DomainException $exception) {
        flash('danger', $exception->getMessage());
    } catch (Throwable $exception) {
        reportDatabaseError($exception, 'Incident operation failed');
        flash('danger', t('message.incident_failed'));
    }
    safeRedirect('fine_form.php');
}

$ids = currentAgencyIds();
if (!$ids) $ids = [0];
$ph = implode(',', array_fill(0, count($ids), '?'));
$vehicles = dbFetchAll("SELECT id,registration_number,brand,model FROM vehicles WHERE agency_id IN ($ph) AND archived_at IS NULL ORDER BY registration_number", $ids);
$contracts = dbFetchAll("SELECT rc.id,rc.contract_number,r.customer_id,r.vehicle_id FROM rental_contracts rc JOIN reservations r ON r.id=rc.reservation_id WHERE r.agency_id IN ($ph) ORDER BY rc.created_at DESC LIMIT 200", $ids);

backofficeHeader(t('page.fine_create.title'), 'incidents.php');
pageHeader('page.fine_create.title', 'page.fine_create.description', [
    'breadcrumbs' => [['label'=>'nav.fleet'],['label'=>'nav.incidents','href'=>'incidents.php'],['label'=>'page.fine_create.title']],
    'secondary' => ['label'=>'common.cancel','href'=>'incidents.php'],
]);
?>
<section class="card form-card">
    <form method="post" enctype="multipart/form-data">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="fine">
        <div class="grid">
            <label><?= e(t('field.vehicle')) ?><select name="vehicle_id"><?php foreach ($vehicles as $v): ?><option value="<?= e($v['id']) ?>"><?= e($v['registration_number'] . ' ' . $v['brand'] . ' ' . $v['model']) ?></option><?php endforeach; ?></select></label>
            <label><?= e(t('field.contract')) ?><select name="contract_id"><option value=""><?= e(t('common.unassigned')) ?></option><?php foreach ($contracts as $c): ?><option value="<?= e($c['id']) ?>"><?= e($c['contract_number']) ?></option><?php endforeach; ?></select></label>
            <label><?= e(t('field.driver')) ?><input name="driver_name"></label>
            <label><?= e(t('field.date_time')) ?><input type="datetime-local" name="occurred_at" required></label>
            <label><?= e(t('field.type')) ?><input name="fine_type" required></label>
            <label><?= e(t('field.amount')) ?><input type="number" step="0.01" name="amount" required></label>
            <label><?= e(t('field.administrative_fee')) ?><input type="number" step="0.01" name="administrative_fee" value="0.00"></label>
            <label><?= e(t('field.document')) ?><input type="file" name="document" accept="application/pdf,image/jpeg,image/png,image/webp"></label>
        </div>
        <div class="form-actions">
            <button class="btn primary"><?= e(t('action.record_fine')) ?></button>
            <a class="btn secondary" href="incidents.php"><?= e(t('common.cancel')) ?></a>
        </div>
    </form>
</section>
<?php backofficeFooter();
