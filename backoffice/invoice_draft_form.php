<?php
require_once __DIR__ . '/_layout.php';
requirePermission('invoices.manage');

if (requestMethod() === 'POST') {
    requireCsrfPost();
    try {
        if (($_POST['action'] ?? '') !== 'invoice_draft') {
            throw new InvalidArgumentException(t('validation.invalid_action'));
        }
        $id = createInvoiceDraftFromReservation(['reservation_id'=>(int)($_POST['reservation_id']??0),'language_code'=>$_POST['language_code']??language(),'idempotency_key'=>$_POST['idempotency_key']??'']);
        safeRedirect('invoice_detail.php?id=' . $id);
    } catch (AuthorizationException $exception) {
        http_response_code(403);
        exit(t('validation.not_authorized'));
    } catch (InvalidArgumentException|DomainException $exception) {
        flash('danger', $exception->getMessage());
    } catch (Throwable $exception) {
        reportDatabaseError($exception, 'Finance operation failed');
        flash('danger', t('message.finance_failed'));
    }
    safeRedirect('invoice_draft_form.php');
}

[$agencyIds, $ph] = financeScopedPlaceholders(currentAgencyIds());
$reservations = dbFetchAll("SELECT r.id,r.reference,r.total_amount,r.advance_amount,r.remaining_amount,r.currency,c.first_name,c.last_name,rc.id contract_id,i.id invoice_id FROM reservations r JOIN customers c ON c.id=r.customer_id LEFT JOIN rental_contracts rc ON rc.reservation_id=r.id AND rc.status<>'cancelled' LEFT JOIN invoices i ON i.reservation_id=r.id AND i.invoice_type='invoice' AND i.status NOT IN('cancelled','credited') WHERE r.agency_id IN ($ph) AND r.status NOT IN('cancelled','expired') ORDER BY r.created_at DESC LIMIT 200", $agencyIds);

backofficeHeader(t('page.invoice_draft_create.title'), 'invoices.php');
pageHeader('page.invoice_draft_create.title', 'page.invoice_draft_create.description', [
    'breadcrumbs' => [['label'=>'nav.finance'],['label'=>'nav.invoices','href'=>'invoices.php'],['label'=>'page.invoice_draft_create.title']],
    'secondary' => ['label'=>'common.cancel','href'=>'invoices.php'],
]);
?>
<section class="card form-card">
    <form method="post">
        <?= csrfField() ?>
        <?= financeIdempotencyField('invoice.draft') ?>
        <input type="hidden" name="action" value="invoice_draft">
        <div class="grid">
            <label><?= e(t('field.reservation')) ?><select name="reservation_id"><?php foreach ($reservations as $r): ?><option value="<?= e($r['id']) ?>"><?= e($r['reference']) ?></option><?php endforeach; ?></select></label>
            <label><?= e(t('field.language')) ?><select name="language_code"><?php foreach (supportedLanguages() as $lang): ?><option value="<?= e($lang) ?>"><?= e(strtoupper($lang)) ?></option><?php endforeach; ?></select></label>
        </div>
        <div class="form-actions">
            <button class="btn primary"><?= e(t('action.create_invoice_draft')) ?></button>
            <a class="btn secondary" href="invoices.php"><?= e(t('common.cancel')) ?></a>
        </div>
    </form>
</section>
<?php backofficeFooter();
