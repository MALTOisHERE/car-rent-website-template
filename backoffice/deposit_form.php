<?php
require_once __DIR__ . '/_layout.php';
requirePermission('deposits.manage');

if (requestMethod() === 'POST') {
    requireCsrfPost();
    try {
        if (($_POST['action'] ?? '') !== 'deposit_create') {
            throw new InvalidArgumentException(t('validation.invalid_action'));
        }
        $id = createDeposit(['reservation_id'=>(int)($_POST['reservation_id']??0),'contract_id'=>$_POST['contract_id']??null,'amount'=>$_POST['amount']??'','reason'=>$_POST['reason']??'','idempotency_key'=>$_POST['idempotency_key']??'']);
        flash('success', t('message.deposit_request_created'));
        safeRedirect('deposits.php');
    } catch (AuthorizationException $exception) {
        http_response_code(403);
        exit(t('validation.not_authorized'));
    } catch (InvalidArgumentException|DomainException $exception) {
        flash('danger', $exception->getMessage());
    } catch (Throwable $exception) {
        reportDatabaseError($exception, 'Finance operation failed');
        flash('danger', t('message.finance_failed'));
    }
    safeRedirect('deposit_form.php');
}

[$agencyIds, $ph] = financeScopedPlaceholders(currentAgencyIds());
$reservations = dbFetchAll("SELECT r.id,r.reference,r.total_amount,r.advance_amount,r.remaining_amount,r.currency,c.first_name,c.last_name,rc.id contract_id,i.id invoice_id FROM reservations r JOIN customers c ON c.id=r.customer_id LEFT JOIN rental_contracts rc ON rc.reservation_id=r.id AND rc.status<>'cancelled' LEFT JOIN invoices i ON i.reservation_id=r.id AND i.invoice_type='invoice' AND i.status NOT IN('cancelled','credited') WHERE r.agency_id IN ($ph) AND r.status NOT IN('cancelled','expired') ORDER BY r.created_at DESC LIMIT 200", $agencyIds);

backofficeHeader(t('page.deposit_create.title'), 'deposits.php');
pageHeader('page.deposit_create.title', 'page.deposit_create.description', [
    'breadcrumbs' => [['label'=>'nav.finance'],['label'=>'nav.deposits','href'=>'deposits.php'],['label'=>'page.deposit_create.title']],
    'secondary' => ['label'=>'common.cancel','href'=>'deposits.php'],
]);
?>
<section class="card form-card">
    <form method="post">
        <?= csrfField() ?>
        <?= financeIdempotencyField('deposit.create') ?>
        <input type="hidden" name="action" value="deposit_create">
        <div class="grid">
            <label><?= e(t('field.reservation')) ?><select name="reservation_id"><?php foreach ($reservations as $r): ?><option value="<?= e($r['id']) ?>"><?= e($r['reference']) ?></option><?php endforeach; ?></select></label>
            <label><?= e(t('field.amount')) ?><input name="amount" type="number" step="0.01" inputmode="decimal" required></label>
            <label class="full"><?= e(t('field.reason')) ?><input name="reason" maxlength="255"></label>
        </div>
        <div class="form-actions">
            <button class="btn primary"><?= e(t('action.request_deposit')) ?></button>
            <a class="btn secondary" href="deposits.php"><?= e(t('common.cancel')) ?></a>
        </div>
    </form>
</section>
<?php backofficeFooter();
