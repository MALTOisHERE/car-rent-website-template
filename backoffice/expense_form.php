<?php
require_once __DIR__ . '/_layout.php';
requirePermission('expenses.manage');

if (requestMethod() === 'POST') {
    requireCsrfPost();
    $receipt = null;
    try {
        if (($_POST['action'] ?? '') !== 'create') {
            throw new InvalidArgumentException(t('validation.invalid_action'));
        }
        $agencyId = (int) ($_POST['agency_id'] ?? 0);
        financeAssertAgency($agencyId);
        if (isset($_FILES['receipt']) && ($_FILES['receipt']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $receipt = storeValidatedDocument($_FILES['receipt'], 'expense_receipts');
        }
        $id = createExpense(['agency_id'=>$agencyId,'vehicle_id'=>$_POST['vehicle_id']??null,'contract_id'=>$_POST['contract_id']??null,'category'=>$_POST['category']??'other','supplier'=>$_POST['supplier']??'','description'=>$_POST['description']??'','amount'=>$_POST['amount']??'','method'=>$_POST['method']??'','expense_date'=>$_POST['expense_date']??'','receipt'=>$receipt??[],'idempotency_key'=>$_POST['idempotency_key']??'']);
        flash('success', t('message.expense_created'));
        safeRedirect('expenses.php');
    } catch (AuthorizationException $e) {
        if ($receipt) removeNewStoredUpload($receipt['path']);
        http_response_code(403);
        exit(t('validation.not_authorized'));
    } catch (InvalidArgumentException|DomainException $e) {
        if ($receipt) removeNewStoredUpload($receipt['path']);
        flash('danger', $e->getMessage());
    } catch (Throwable $e) {
        if ($receipt) removeNewStoredUpload($receipt['path']);
        reportDatabaseError($e, 'Expense operation failed');
        flash('danger', t('message.expense_failed'));
    }
    safeRedirect('expense_form.php');
}

[$ids, $ph] = financeScopedPlaceholders(currentAgencyIds());
$agencies = dbFetchAll("SELECT id,name FROM agencies WHERE id IN ($ph) AND archived_at IS NULL ORDER BY name", $ids);
$vehicles = dbFetchAll("SELECT id,registration_number FROM vehicles WHERE agency_id IN ($ph) AND archived_at IS NULL ORDER BY registration_number", $ids);

backofficeHeader(t('page.expense_create.title'), 'expenses.php');
pageHeader('page.expense_create.title', 'page.expense_create.description', [
    'breadcrumbs' => [['label'=>'nav.finance'],['label'=>'nav.expenses','href'=>'expenses.php'],['label'=>'page.expense_create.title']],
    'secondary' => ['label'=>'common.cancel','href'=>'expenses.php'],
]);
?>
<section class="card form-card">
    <form method="post" enctype="multipart/form-data">
        <?= csrfField() ?>
        <?= financeIdempotencyField('expense.create') ?>
        <input type="hidden" name="action" value="create">
        <div class="grid">
            <label><?= e(t('field.agency')) ?><select name="agency_id"><?php foreach ($agencies as $a): ?><option value="<?= e($a['id']) ?>"><?= e($a['name']) ?></option><?php endforeach; ?></select></label>
            <label><?= e(t('field.vehicle')) ?><select name="vehicle_id"><option value=""><?= e(t('common.unassigned')) ?></option><?php foreach ($vehicles as $v): ?><option value="<?= e($v['id']) ?>"><?= isolatedValue($v['registration_number'], 'reference-value') ?></option><?php endforeach; ?></select></label>
            <label><?= e(t('field.category')) ?><select name="category"><?php foreach (['maintenance','repair','insurance','technical_inspection','tax','cleaning','fuel','leasing','parking','fine','partner_commission','administration','other'] as $c): ?><option value="<?= e($c) ?>"><?= e(t('option.' . $c)) ?></option><?php endforeach; ?></select></label>
            <label><?= e(t('field.supplier')) ?><input name="supplier"></label>
            <label><?= e(t('field.description')) ?><input name="description" required maxlength="255"></label>
            <label><?= e(t('field.amount')) ?><input name="amount" type="number" step="0.01" inputmode="decimal" required></label>
            <label><?= e(t('field.method')) ?><select name="method"><?php foreach (financePaymentMethods() as $m): ?><option value="<?= e($m) ?>"><?= e(t('option.' . $m)) ?></option><?php endforeach; ?></select></label>
            <label><?= e(t('field.date')) ?><input type="date" name="expense_date" value="<?= e(date('Y-m-d')) ?>" required></label>
            <label><?= e(t('field.document')) ?><input type="file" name="receipt" accept="application/pdf,image/jpeg,image/png,image/webp"></label>
        </div>
        <div class="form-actions">
            <button class="btn primary"><?= e(t('action.record_expense')) ?></button>
            <a class="btn secondary" href="expenses.php"><?= e(t('common.cancel')) ?></a>
        </div>
    </form>
</section>
<?php backofficeFooter();
