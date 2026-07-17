<?php
require_once __DIR__ . '/_layout.php';
requirePermission('invoices.manage');
$ids = currentAgencyIds(); if (!$ids) $ids = [0];
$ph = implode(',', array_fill(0, count($ids), '?'));
$invoices = dbFetchAll("SELECT i.*,c.first_name,c.last_name,c.company_name,r.reference FROM invoices i JOIN customers c ON c.id=i.customer_id LEFT JOIN reservations r ON r.id=i.reservation_id WHERE i.agency_id IN ($ph) ORDER BY i.issued_at DESC", $ids);
backofficeHeader('Invoices', 'invoices.php');
pageHeader('Invoice register', 'Review issued invoices, balances, status, and printable documents.', [
    'breadcrumbs'=>[['label'=>'Finance'],['label'=>'Invoices']],
    'primary'=>['label'=>'Create invoice','href'=>'finance.php#record-payment'],
]);
?>
<div class="table-wrap" role="region" aria-label="Invoice register" tabindex="0"><table><thead><tr>
<th scope="col">Number</th><th scope="col">Customer</th><th scope="col">Reservation</th><th scope="col">Issued</th><th scope="col">Total</th><th scope="col">Paid</th><th scope="col">Status</th><th scope="col">Document</th>
</tr></thead><tbody><?php foreach($invoices as $invoice):?><tr>
<td><?=e($invoice['invoice_number'])?></td><td><?=e($invoice['company_name']?:$invoice['first_name'].' '.$invoice['last_name'])?></td><td><?=e($invoice['reference'])?></td><td><?=e($invoice['issued_at'])?></td>
<td class="money"><?=money($invoice['total_amount'],$invoice['currency'])?></td><td class="money"><?=money($invoice['paid_amount'],$invoice['currency'])?></td><td><?=statusBadge($invoice['status'])?></td>
<td><a class="btn secondary" target="_blank" rel="noopener" href="invoice_print.php?id=<?=e($invoice['id'])?>">Print / PDF</a></td>
</tr><?php endforeach;?></tbody></table><?php if(!$invoices) echo emptyState('No invoices found'); ?></div>
<?php backofficeFooter();
