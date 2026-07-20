<?php
require_once __DIR__ . '/_layout.php';
requirePermission('payments.create');
$canManageFinance = canViewFinanceHistory();

if (requestMethod() === 'POST') {
    requireCsrfPost();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'payment') {
            $id = recordPayment(['reservation_id'=>(int)($_POST['reservation_id']??0),'contract_id'=>$_POST['contract_id']??null,'invoice_id'=>$_POST['invoice_id']??null,'amount'=>$_POST['amount']??'','paid_at'=>$_POST['paid_at']??date('Y-m-d H:i:s'),'method'=>$_POST['method']??'','reference'=>$_POST['reference']??'','notes'=>$_POST['notes']??'']);
            flash('success', 'Payment recorded: #' . $id);
        } elseif ($action === 'invoice') {
            requirePermission('invoices.manage');
            $id = createInvoiceFromReservation((int) ($_POST['reservation_id'] ?? 0));
            flash('success', 'Invoice created: #' . $id);
        } elseif ($action === 'deposit_create') {
            requirePermission('payments.manage');
            $reservation = dbFetchOne('SELECT * FROM reservations WHERE id=:id', ['id'=>(int)($_POST['reservation_id']??0)]);
            if (!$reservation) throw new InvalidArgumentException(t('validation.reservation_not_found'));
            requireAgencyAccess($reservation['agency_id']);
            $amount = positiveMoney($_POST['amount'] ?? '');
            if ($amount === null) throw new InvalidArgumentException(t('validation.deposit_amount'));
            dbExecute("INSERT INTO deposits(reservation_id,contract_id,amount,currency,status,created_by,updated_by) VALUES(:reservation,:contract,:amount,:currency,'requested',:user,:user2)", ['reservation'=>$reservation['id'],'contract'=>($_POST['contract_id']??'')?:null,'amount'=>$amount,'currency'=>$reservation['currency'],'user'=>currentUserId(),'user2'=>currentUserId()]);
            $id = (int) db()->lastInsertId();
            auditLog('deposit.created','deposit',$id,null,['amount'=>$amount],$reservation['agency_id']);
            flash('success',t('message.deposit_request_created'));
        } elseif ($action === 'deposit_status') {
            requirePermission('payments.manage');
            updateDepositStatus((int)($_POST['id']??0), $_POST['status']??'', $_POST['retained_amount']??'0', $_POST['reason']??null);
            flash('success',t('message.deposit_status_updated'));
        }
    } catch (InvalidArgumentException|DomainException $exception) {
        flash('danger', $exception->getMessage());
    } catch (Throwable $exception) {
        reportDatabaseError($exception, 'Financial operation failed');
        flash('danger',t('message.finance_failed'));
    }
    safeRedirect('finance.php');
}

$agencyIds = currentAgencyIds(); if (!$agencyIds) $agencyIds = [0];
$placeholders = implode(',', array_fill(0, count($agencyIds), '?'));
$reservations = dbFetchAll("SELECT r.id,r.reference,r.total_amount,r.remaining_amount,r.currency,c.first_name,c.last_name,rc.id contract_id FROM reservations r JOIN customers c ON c.id=r.customer_id LEFT JOIN rental_contracts rc ON rc.reservation_id=r.id AND rc.status<>'cancelled' WHERE r.agency_id IN ($placeholders) AND r.status NOT IN ('cancelled','expired') ORDER BY r.created_at DESC LIMIT 200", $agencyIds);
$payments = $canManageFinance ? dbFetchAll("SELECT p.*,r.reference reservation_reference FROM payments p JOIN reservations r ON r.id=p.reservation_id WHERE p.agency_id IN ($placeholders) ORDER BY p.paid_at DESC LIMIT 100", $agencyIds) : [];
$deposits = $canManageFinance ? dbFetchAll("SELECT d.*,r.reference FROM deposits d JOIN reservations r ON r.id=d.reservation_id WHERE r.agency_id IN ($placeholders) ORDER BY d.created_at DESC LIMIT 100", $agencyIds) : [];
$invoices = $canManageFinance && canViewInvoiceSections() ? dbFetchAll("SELECT i.*,r.reference FROM invoices i LEFT JOIN reservations r ON r.id=i.reservation_id WHERE i.agency_id IN ($placeholders) ORDER BY i.issued_at DESC LIMIT 100", $agencyIds) : [];

backofficeHeader(t('page.finance.title'), 'finance.php');
pageHeader('page.finance.title', 'page.finance.description', [
    'breadcrumbs'=>[['label'=>'nav.finance'],['label'=>'nav.payments']],
    'primary'=>['label'=>'action.record_payment','href'=>'#record-payment'],
    'secondary'=>canViewInvoiceSections() ? ['label'=>'page.invoices.title','href'=>'invoices.php'] : null,
]);
?>
<div class="grid">
<section class="card" id="record-payment"><h2><?=e(t('section.record_payment'))?></h2><form method="post"><?=csrfField()?><input type="hidden" name="action" value="payment">
<label><?=e(t('field.reservation'))?><select name="reservation_id"><?php foreach($reservations as $r):?><option value="<?=e($r['id'])?>"><?=e($r['reference'].' — '.$r['first_name'].' '.$r['last_name'].' — '.localizedMoney($r['remaining_amount'],$r['currency']))?></option><?php endforeach;?></select></label>
<label><?=e(t('field.amount'))?><input name="amount" inputmode="decimal" required></label><label><?=e(t('field.date_time'))?><input type="datetime-local" name="paid_at" value="<?=e(date('Y-m-d\TH:i'))?>" required></label>
<label><?=e(t('field.method'))?><select name="method"><?php foreach(['cash','card','bank_transfer','cheque','online','other'] as $method):?><option value="<?=e($method)?>"><?=e(t('option.'.$method))?></option><?php endforeach;?></select></label>
<label><?=e(t('field.reference'))?><input name="reference"></label><label><?=e(t('field.notes'))?><textarea name="notes"></textarea></label><button class="btn primary"><?=e(t('action.record_payment'))?></button></form>
<?php if (canViewInvoiceSections()): ?><hr><h2><?=e(t('action.create_invoice'))?></h2><form method="post"><?=csrfField()?><input type="hidden" name="action" value="invoice"><label><?=e(t('field.reservation'))?><select name="reservation_id"><?php foreach($reservations as $r):?><option value="<?=e($r['id'])?>"><?=e($r['reference'])?></option><?php endforeach;?></select></label><button class="btn secondary"><?=e(t('action.create_invoice'))?></button></form><?php endif; ?>
<?php if ($canManageFinance): ?><div id="deposits"><h2><?=e(t('action.request_deposit'))?></h2><form method="post"><?=csrfField()?><input type="hidden" name="action" value="deposit_create"><label><?=e(t('field.reservation'))?><select name="reservation_id"><?php foreach($reservations as $r):?><option value="<?=e($r['id'])?>"><?=e($r['reference'])?></option><?php endforeach;?></select></label><label><?=e(t('field.amount'))?><input name="amount" inputmode="decimal" required></label><button class="btn secondary"><?=e(t('action.request_deposit'))?></button></form></div><?php endif; ?>
</section>
<?php if ($canManageFinance): ?>
<section class="card"><h2><?=e(t('section.recent_payments'))?></h2><div class="table-wrap" role="region" aria-label="<?=e(t('section.recent_payments'))?>" tabindex="0"><table><thead><tr><th scope="col"><?=e(t('field.number'))?></th><th scope="col"><?=e(t('field.reservation'))?></th><th scope="col"><?=e(t('field.amount'))?></th><th scope="col"><?=e(t('field.method'))?></th><th scope="col"><?=e(t('common.status'))?></th><th scope="col"><?=e(t('field.date'))?></th></tr></thead><tbody><?php foreach($payments as $p):?><tr><td><?=isolatedValue($p['payment_number'],'reference-value')?></td><td><?=isolatedValue($p['reservation_reference'],'reference-value')?></td><td class="money"><?=money($p['amount'],$p['currency'])?></td><td><?=e(t('option.'.$p['method']))?></td><td><?=statusBadge($p['status'])?></td><td><?=formattedDateTime($p['paid_at'])?></td></tr><?php endforeach;?></tbody></table><?php if(!$payments) echo emptyState('empty.no_payments'); ?></div>
<h2><?=e(t('section.deposits'))?></h2><div class="table-wrap" role="region" aria-label="<?=e(t('section.deposits'))?>" tabindex="0"><table><thead><tr><th scope="col"><?=e(t('field.reservation'))?></th><th scope="col"><?=e(t('field.amount'))?></th><th scope="col"><?=e(t('status.held'))?></th><th scope="col"><?=e(t('common.status'))?></th><th scope="col"><?=e(t('common.actions'))?></th></tr></thead><tbody><?php foreach($deposits as $d):?><tr><td><?=isolatedValue($d['reference'],'reference-value')?></td><td class="money"><?=money($d['amount'],$d['currency'])?></td><td class="money"><?=money($d['retained_amount'],$d['currency'])?></td><td><?=statusBadge($d['status'])?></td><td><form method="post"><?=csrfField()?><input type="hidden" name="action" value="deposit_status"><input type="hidden" name="id" value="<?=e($d['id'])?>"><select name="status"><?php foreach(['requested','received','held','partially_retained','returned','disputed'] as $s):?><option value="<?=e($s)?>" <?=$s===$d['status']?'selected':''?>><?=e(translatedStatus($s))?></option><?php endforeach;?></select><input name="retained_amount" value="<?=e($d['retained_amount'])?>" placeholder="<?=e(t('field.amount'))?>"><input name="reason" placeholder="<?=e(t('field.reason'))?>"><button class="btn secondary"><?=e(t('common.update'))?></button></form></td></tr><?php endforeach;?></tbody></table><?php if(!$deposits) echo emptyState('empty.no_deposits'); ?></div>
<?php if(canViewInvoiceSections()): ?><h2><?=e(t('section.invoices'))?></h2><div class="table-wrap" role="region" aria-label="<?=e(t('section.invoices'))?>" tabindex="0"><table><thead><tr><th scope="col"><?=e(t('field.number'))?></th><th scope="col"><?=e(t('field.reservation'))?></th><th scope="col"><?=e(t('field.total'))?></th><th scope="col"><?=e(t('status.paid'))?></th><th scope="col"><?=e(t('common.status'))?></th></tr></thead><tbody><?php foreach($invoices as $i):?><tr><td><?=isolatedValue($i['invoice_number'],'reference-value')?></td><td><?=isolatedValue($i['reference'],'reference-value')?></td><td class="money"><?=money($i['total_amount'],$i['currency'])?></td><td class="money"><?=money($i['paid_amount'],$i['currency'])?></td><td><?=statusBadge($i['status'])?></td></tr><?php endforeach;?></tbody></table><?php if(!$invoices) echo emptyState('empty.no_invoices'); ?></div><?php endif; ?>
</section>
<?php else: ?><section class="card"><h2><?=e(t('section.payment_access'))?></h2><p><?=e(t('message.payment_access'))?></p></section><?php endif; ?>
</div>
<?php backofficeFooter();
