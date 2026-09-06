<?php
require_once __DIR__ . '/_layout.php';
requirePermission('payments.excess');

if (requestMethod() === 'POST') {
    requireCsrfPost();
    $storedProof = null;
    try {
        if (($_POST['action'] ?? '') !== 'excess_payment') {
            throw new InvalidArgumentException(t('validation.invalid_action'));
        }
        if (isset($_FILES['proof']) && ($_FILES['proof']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $storedProof = storeValidatedDocument($_FILES['proof'], 'payment_proofs');
        }
        $command = ['reservation_id'=>(int)($_POST['reservation_id']??0),'contract_id'=>$_POST['contract_id']??null,'invoice_id'=>$_POST['invoice_id']??null,'amount'=>$_POST['amount']??'','paid_at'=>$_POST['paid_at']??'','method'=>$_POST['method']??'','reference'=>$_POST['reference']??'','notes'=>$_POST['notes']??'','reason'=>$_POST['reason']??'','idempotency_key'=>$_POST['idempotency_key']??''];
        if ($storedProof) $command += ['proof_path'=>$storedProof['path'],'proof_original_name'=>$storedProof['original_name'],'proof_mime_type'=>$storedProof['mime_type'],'proof_file_size'=>$storedProof['size']];
        $id = createExcessPayment($command);
        flash('success', t('message.finance_excess_created', ['id'=>$id]));
        safeRedirect('finance.php');
    } catch (AuthorizationException $exception) {
        if ($storedProof) removeNewStoredUpload($storedProof['path']);
        http_response_code(403);
        exit(t('validation.not_authorized'));
    } catch (InvalidArgumentException|DomainException $exception) {
        if ($storedProof) removeNewStoredUpload($storedProof['path']);
        flash('danger', $exception->getMessage());
    } catch (Throwable $exception) {
        if ($storedProof) removeNewStoredUpload($storedProof['path']);
        reportDatabaseError($exception, 'Finance operation failed');
        flash('danger', t('message.finance_failed'));
    }
    safeRedirect('excess_payment_form.php');
}

[$agencyIds, $ph] = financeScopedPlaceholders(currentAgencyIds());
$reservations = dbFetchAll("SELECT r.id,r.reference,r.total_amount,r.advance_amount,r.remaining_amount,r.currency,c.first_name,c.last_name,rc.id contract_id,i.id invoice_id FROM reservations r JOIN customers c ON c.id=r.customer_id LEFT JOIN rental_contracts rc ON rc.reservation_id=r.id AND rc.status<>'cancelled' LEFT JOIN invoices i ON i.reservation_id=r.id AND i.invoice_type='invoice' AND i.status NOT IN('cancelled','credited') WHERE r.agency_id IN ($ph) AND r.status NOT IN('cancelled','expired') ORDER BY r.created_at DESC LIMIT 200", $agencyIds);

backofficeHeader(t('page.excess_payment_create.title'), 'finance.php');
pageHeader('page.excess_payment_create.title', 'page.excess_payment_create.description', [
    'breadcrumbs' => [['label'=>'nav.finance'],['label'=>'nav.payments','href'=>'finance.php'],['label'=>'page.excess_payment_create.title']],
    'secondary' => ['label'=>'common.cancel','href'=>'finance.php'],
]);
?>
<section class="card form-card">
    <form method="post" enctype="multipart/form-data">
        <?= csrfField() ?>
        <?= financeIdempotencyField('payment.excess') ?>
        <input type="hidden" name="action" value="excess_payment">
        <input type="hidden" name="paid_at" value="<?= e(date('Y-m-d\TH:i')) ?>">
        <div class="grid">
            <label class="full"><?= e(t('field.reservation')) ?><select name="reservation_id"><?php foreach ($reservations as $r): ?><option value="<?= e($r['id']) ?>"><?= e($r['reference'] . ' - ' . localizedMoney($r['remaining_amount'], $r['currency'])) ?></option><?php endforeach; ?></select></label>
            <label><?= e(t('field.amount')) ?><input name="amount" required type="number" step="0.01" inputmode="decimal"></label>
            <label><?= e(t('field.method')) ?><select name="method"><?php foreach (financePaymentMethods() as $m): ?><option value="<?= e($m) ?>"><?= e(t('option.' . $m)) ?></option><?php endforeach; ?></select></label>
            <label><?= e(t('field.reason')) ?><input name="reason" required maxlength="255"></label>
            <label><?= e(t('field.document')) ?><input type="file" name="proof" accept="application/pdf,image/jpeg,image/png,image/webp"></label>
        </div>
        <div class="form-actions">
            <button class="btn primary"><?= e(t('action.allocate_excess')) ?></button>
            <a class="btn secondary" href="finance.php"><?= e(t('common.cancel')) ?></a>
        </div>
    </form>
</section>
<?php backofficeFooter();
