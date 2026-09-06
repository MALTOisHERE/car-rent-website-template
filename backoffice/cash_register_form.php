<?php
require_once __DIR__ . '/_layout.php';
requirePermission('cash.manage');

if (requestMethod() === 'POST') {
    requireCsrfPost();
    try {
        if (($_POST['action'] ?? '') !== 'open') {
            throw new InvalidArgumentException(t('validation.invalid_action'));
        }
        $id = openCashRegister(['agency_id'=>(int)($_POST['agency_id']??0),'business_date'=>$_POST['business_date']??'','opening_balance'=>$_POST['opening_balance']??'','idempotency_key'=>$_POST['idempotency_key']??'']);
        flash('success', t('message.cash_opened'));
        safeRedirect('cash_register_detail.php?id=' . $id);
    } catch (AuthorizationException $e) {
        http_response_code(403);
        exit(t('validation.not_authorized'));
    } catch (InvalidArgumentException|DomainException $e) {
        flash('danger', $e->getMessage());
    } catch (Throwable $e) {
        reportDatabaseError($e, 'Cash register opening failed');
        flash('danger', t('message.cash_failed'));
    }
    safeRedirect('cash_register_form.php');
}

[$ids, $ph] = financeScopedPlaceholders(currentAgencyIds());
$agencies = dbFetchAll("SELECT id,name FROM agencies WHERE id IN ($ph) AND archived_at IS NULL ORDER BY name", $ids);

backofficeHeader(t('page.cash_register_create.title'), 'cash.php');
pageHeader('page.cash_register_create.title', 'page.cash_register_create.description', [
    'breadcrumbs' => [['label'=>'nav.finance'],['label'=>'nav.cash_register','href'=>'cash.php'],['label'=>'page.cash_register_create.title']],
    'secondary' => ['label'=>'common.cancel','href'=>'cash.php'],
]);
?>
<section class="card form-card">
    <form method="post">
        <?= csrfField() ?>
        <?= financeIdempotencyField('cash.open') ?>
        <input type="hidden" name="action" value="open">
        <div class="grid">
            <label><?= e(t('field.agency')) ?><select name="agency_id"><?php foreach ($agencies as $a): ?><option value="<?= e($a['id']) ?>"><?= e($a['name']) ?></option><?php endforeach; ?></select></label>
            <label><?= e(t('field.business_date')) ?><input type="date" name="business_date" value="<?= e(date('Y-m-d')) ?>" required></label>
            <label><?= e(t('field.opening_balance')) ?><input name="opening_balance" value="0.00" type="number" step="0.01" inputmode="decimal" required></label>
        </div>
        <div class="form-actions">
            <button class="btn primary"><?= e(t('action.open_register')) ?></button>
            <a class="btn secondary" href="cash.php"><?= e(t('common.cancel')) ?></a>
        </div>
    </form>
</section>
<?php backofficeFooter();
