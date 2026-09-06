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
        $stored = storeValidatedDocument($_FILES['document'], 'vehicle_documents');
        dbExecute('INSERT INTO vehicle_documents(vehicle_id,document_type,document_number,issued_at,expires_at,storage_path,original_name,mime_type,file_size,created_by)VALUES(:vehicle,:type,:number,:issued,:expires,:path,:original,:mime,:size,:user)', ['vehicle'=>$vehicle['id'],'type'=>validateChoice($_POST['document_type']??'',['registration','insurance','technical_inspection','tax','other'],'other'),'number'=>trim((string)($_POST['document_number']??'')),'issued'=>($_POST['issued_at']??'')?:null,'expires'=>($_POST['expires_at']??'')?:null,'path'=>$stored['path'],'original'=>$stored['original_name'],'mime'=>$stored['mime_type'],'size'=>$stored['size'],'user'=>currentUserId()]);
        auditLog('vehicle_document.created', 'vehicle_document', db()->lastInsertId(), null, ['vehicle_id'=>$vehicle['id'],'type'=>$_POST['document_type']], $vehicle['agency_id']);
        flash('success', t('message.vehicle_document_stored'));
        safeRedirect('maintenance.php');
    } catch (InvalidArgumentException|DomainException $exception) {
        flash('danger', $exception->getMessage());
    } catch (Throwable $exception) {
        reportDatabaseError($exception, 'Maintenance operation failed');
        flash('danger', t('message.maintenance_failed'));
    }
    safeRedirect('vehicle_document_form.php');
}

$ids = currentAgencyIds();
if (!$ids) $ids = [0];
$ph = implode(',', array_fill(0, count($ids), '?'));
$vehicles = dbFetchAll("SELECT id,registration_number,brand,model,current_mileage FROM vehicles WHERE agency_id IN ($ph) AND archived_at IS NULL ORDER BY registration_number", $ids);

backofficeHeader(t('page.vehicle_document_create.title'), 'maintenance.php');
pageHeader('page.vehicle_document_create.title', 'page.vehicle_document_create.description', [
    'breadcrumbs' => [['label'=>'nav.fleet'],['label'=>'nav.maintenance','href'=>'maintenance.php'],['label'=>'page.vehicle_document_create.title']],
    'secondary' => ['label'=>'common.cancel','href'=>'maintenance.php'],
]);
?>
<section class="card form-card">
    <form method="post" enctype="multipart/form-data">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="document">
        <div class="grid">
            <label><?= e(t('field.vehicle')) ?><select name="vehicle_id"><?php foreach ($vehicles as $v): ?><option value="<?= e($v['id']) ?>"><?= e($v['registration_number']) ?></option><?php endforeach; ?></select></label>
            <label><?= e(t('field.document_type')) ?><select name="document_type"><?php foreach (['registration','insurance','technical_inspection','tax','other'] as $documentType): ?><option value="<?= e($documentType) ?>"><?= e(t('option.' . $documentType)) ?></option><?php endforeach; ?></select></label>
            <label><?= e(t('field.number')) ?><input name="document_number"></label>
            <label><?= e(t('field.issue_date')) ?><input type="date" name="issued_at"></label>
            <label><?= e(t('field.expiry_date')) ?><input type="date" name="expires_at"></label>
            <label><?= e(t('field.document')) ?><input type="file" name="document" accept="application/pdf,image/jpeg,image/png,image/webp" required></label>
        </div>
        <div class="form-actions">
            <button class="btn primary"><?= e(t('action.store_document')) ?></button>
            <a class="btn secondary" href="maintenance.php"><?= e(t('common.cancel')) ?></a>
        </div>
    </form>
</section>
<?php backofficeFooter();
