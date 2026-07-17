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
            if (!$reservation) throw new InvalidArgumentException('Reservation not found.');
            requireAgencyAccess($reservation['agency_id']);
            $amount = positiveMoney($_POST['amount'] ?? '');
            if ($amount === null) throw new InvalidArgumentException('Enter a valid deposit amount.');
            dbExecute("INSERT INTO deposits(reservation_id,contract_id,amount,currency,status,created_by,updated_by) VALUES(:reservation,:contract,:amount,:currency,'requested',:user,:user2)", ['reservation'=>$reservation['id'],'contract'=>($_POST['contract_id']??'')?:null,'amount'=>$amount,'currency'=>$reservation['currency'],'user'=>currentUserId(),'user2'=>currentUserId()]);
            $id = (int) db()->lastInsertId();
            auditLog('deposit.created','deposit',$id,null,['amount'=>$amount],$reservation['agency_id']);
            flash('success','Deposit request created.');
        } elseif ($action === 'deposit_status') {
            requirePermission('payments.manage');
            updateDepositStatus((int)($_POST['id']??0), $_POST['status']??'', $_POST['retained_amount']??'0', $_POST['reason']??null);
            flash('success','Deposit status updated.');
        }
    } catch (InvalidArgumentException|DomainException $exception) {
        flash('danger', $exception->getMessage());
    } catch (Throwable $exception) {
        reportDatabaseError($exception, 'Financial operation failed');
        flash('danger', 'The financial operation could not be completed.');
    }
    safeRedirect('finance.php');
}

$agencyIds = currentAgencyIds(); if (!$agencyIds) $agencyIds = [0];
$placeholders = implode(',', array_fill(0, count($agencyIds), '?'));
$reservations = dbFetchAll("SELECT r.id,r.reference,r.total_amount,r.remaining_amount,r.currency,c.first_name,c.last_name,rc.id contract_id FROM reservations r JOIN customers c ON c.id=r.customer_id LEFT JOIN rental_contracts rc ON rc.reservation_id=r.id AND rc.status<>'cancelled' WHERE r.agency_id IN ($placeholders) AND r.status NOT IN ('cancelled','expired') ORDER BY r.created_at DESC LIMIT 200", $agencyIds);
$payments = $canManageFinance ? dbFetchAll("SELECT p.*,r.reference reservation_reference FROM payments p JOIN reservations r ON r.id=p.reservation_id WHERE p.agency_id IN ($placeholders) ORDER BY p.paid_at DESC LIMIT 100", $agencyIds) : [];
$deposits = $canManageFinance ? dbFetchAll("SELECT d.*,r.reference FROM deposits d JOIN reservations r ON r.id=d.reservation_id WHERE r.agency_id IN ($placeholders) ORDER BY d.created_at DESC LIMIT 100", $agencyIds) : [];
$invoices = $canManageFinance && canViewInvoiceSections() ? dbFetchAll("SELECT i.*,r.reference FROM invoices i LEFT JOIN reservations r ON r.id=i.reservation_id WHERE i.agency_id IN ($placeholders) ORDER BY i.issued_at DESC LIMIT 100", $agencyIds) : [];

backofficeHeader(t('payments'), 'finance.php');
pageHeader('Payments, deposits, and invoices', 'Record authorized transactions and review financial lifecycles.', [
    'breadcrumbs'=>[['label'=>'Finance'],['label'=>'payments']],
    'primary'=>['label'=>'Record payment','href'=>'#record-payment'],
    'secondary'=>canViewInvoiceSections() ? ['label'=>'Invoice register','href'=>'invoices.php'] : null,
]);
?>
<div class="grid">
<section class="card" id="record-payment"><h2>Record payment</h2><form method="post"><?=csrfField()?><input type="hidden" name="action" value="payment">
<label>Reservation<select name="reservation_id"><?php foreach($reservations as $r):?><option value="<?=e($r['id'])?>"><?=e($r['reference'].' — '.$r['first_name'].' '.$r['last_name'].' — Due '.$r['remaining_amount'].' '.$r['currency'])?></option><?php endforeach;?></select></label>
<label>Amount<input name="amount" inputmode="decimal" required></label><label>Date/time<input type="datetime-local" name="paid_at" value="<?=e(date('Y-m-d\TH:i'))?>" required></label>
<label>Method<select name="method"><option>cash</option><option>card</option><option>bank_transfer</option><option>cheque</option><option>online</option><option>other</option></select></label>
<label>Reference<input name="reference"></label><label>Notes<textarea name="notes"></textarea></label><button class="btn primary">Record payment</button></form>
<?php if (canViewInvoiceSections()): ?><hr><h2>Create invoice</h2><form method="post"><?=csrfField()?><input type="hidden" name="action" value="invoice"><label>Reservation<select name="reservation_id"><?php foreach($reservations as $r):?><option value="<?=e($r['id'])?>"><?=e($r['reference'])?></option><?php endforeach;?></select></label><button class="btn secondary">Create invoice</button></form><?php endif; ?>
<?php if ($canManageFinance): ?><div id="deposits"><h2>Request deposit</h2><form method="post"><?=csrfField()?><input type="hidden" name="action" value="deposit_create"><label>Reservation<select name="reservation_id"><?php foreach($reservations as $r):?><option value="<?=e($r['id'])?>"><?=e($r['reference'])?></option><?php endforeach;?></select></label><label>Amount<input name="amount" inputmode="decimal" required></label><button class="btn secondary">Request deposit</button></form></div><?php endif; ?>
</section>
<?php if ($canManageFinance): ?>
<section class="card"><h2>Recent payments</h2><div class="table-wrap" role="region" aria-label="Recent payments" tabindex="0"><table><thead><tr><th scope="col">Number</th><th scope="col">Reservation</th><th scope="col">Amount</th><th scope="col">Method</th><th scope="col">Status</th><th scope="col">Date</th></tr></thead><tbody><?php foreach($payments as $p):?><tr><td><?=e($p['payment_number'])?></td><td><?=e($p['reservation_reference'])?></td><td class="money"><?=money($p['amount'],$p['currency'])?></td><td><?=e($p['method'])?></td><td><?=statusBadge($p['status'])?></td><td><?=e($p['paid_at'])?></td></tr><?php endforeach;?></tbody></table><?php if(!$payments) echo emptyState('No payments found'); ?></div>
<h2>Deposits</h2><div class="table-wrap" role="region" aria-label="Deposits" tabindex="0"><table><thead><tr><th scope="col">Reservation</th><th scope="col">Amount</th><th scope="col">Retained</th><th scope="col">Status</th><th scope="col">Lifecycle</th></tr></thead><tbody><?php foreach($deposits as $d):?><tr><td><?=e($d['reference'])?></td><td class="money"><?=money($d['amount'],$d['currency'])?></td><td class="money"><?=money($d['retained_amount'],$d['currency'])?></td><td><?=statusBadge($d['status'])?></td><td><form method="post"><?=csrfField()?><input type="hidden" name="action" value="deposit_status"><input type="hidden" name="id" value="<?=e($d['id'])?>"><select name="status"><?php foreach(['requested','received','held','partially_retained','returned','disputed'] as $s):?><option <?=$s===$d['status']?'selected':''?>><?=e($s)?></option><?php endforeach;?></select><input name="retained_amount" value="<?=e($d['retained_amount'])?>" placeholder="Retained amount"><input name="reason" placeholder="Retention / dispute reason"><button class="btn secondary">Update</button></form></td></tr><?php endforeach;?></tbody></table><?php if(!$deposits) echo emptyState('No deposits found'); ?></div>
<?php if(canViewInvoiceSections()): ?><h2>Invoices</h2><div class="table-wrap" role="region" aria-label="Invoices" tabindex="0"><table><thead><tr><th scope="col">Number</th><th scope="col">Reservation</th><th scope="col">Total</th><th scope="col">Paid</th><th scope="col">Status</th></tr></thead><tbody><?php foreach($invoices as $i):?><tr><td><?=e($i['invoice_number'])?></td><td><?=e($i['reference'])?></td><td class="money"><?=money($i['total_amount'],$i['currency'])?></td><td class="money"><?=money($i['paid_amount'],$i['currency'])?></td><td><?=statusBadge($i['status'])?></td></tr><?php endforeach;?></tbody></table><?php if(!$invoices) echo emptyState('No invoices found'); ?></div><?php endif; ?>
</section>
<?php else: ?><section class="card"><h2>Payment access</h2><p>You can record payments for assigned reservations. Financial history, deposits, and invoice management require finance-management access.</p></section><?php endif; ?>
</div>
<?php backofficeFooter();
