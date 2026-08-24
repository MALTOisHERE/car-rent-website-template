<?php
require_once __DIR__ . '/_layout.php';
requirePermission('invoices.manage');
$ids = currentAgencyIds(); if (!$ids) $ids = [0];
$ph = implode(',', array_fill(0, count($ids), '?'));
$invoices = dbFetchAll("SELECT i.*,c.first_name,c.last_name,c.company_name,r.reference FROM invoices i JOIN customers c ON c.id=i.customer_id LEFT JOIN reservations r ON r.id=i.reservation_id WHERE i.agency_id IN ($ph) ORDER BY i.issued_at DESC", $ids);
backofficeHeader(t('page.invoices.title'), 'invoices.php');
pageHeader('page.invoices.title', 'page.invoices.description', [
    'breadcrumbs'=>[['label'=>'nav.finance'],['label'=>'nav.invoices']],
    'primary'=>['label'=>'action.create_invoice','href'=>'finance.php#record-payment'],
]);
?>
<div class="table-wrap" role="region" aria-label="<?=e(t('page.invoices.title'))?>" tabindex="0"><table><thead><tr>
<th scope="col"><?=e(t('field.number'))?></th><th scope="col"><?=e(t('field.customer'))?></th><th scope="col"><?=e(t('field.reservation'))?></th><th scope="col"><?=e(t('field.issued'))?></th><th scope="col"><?=e(t('field.total'))?></th><th scope="col"><?=e(t('status.paid'))?></th><th scope="col"><?=e(t('common.status'))?></th><th scope="col"><?=e(t('field.documents'))?></th>
</tr></thead><tbody><?php foreach($invoices as $invoice):?><tr>
<td><a href="invoice_detail.php?id=<?=e($invoice['id'])?>"><?=isolatedValue($invoice['invoice_number']?:t('status.draft'),'reference-value')?></a></td><td><?=e($invoice['company_name']?:$invoice['first_name'].' '.$invoice['last_name'])?></td><td><?=isolatedValue($invoice['reference'],'reference-value')?></td><td><?=formattedDateTime($invoice['issued_at'])?></td>
<td class="money"><?=money($invoice['total_amount'],$invoice['currency'])?></td><td class="money"><?=money($invoice['paid_amount'],$invoice['currency'])?></td><td><?=statusBadge($invoice['status'])?></td>
<td><?php if($invoice['issued_at']):?><a class="btn secondary" target="_blank" rel="noopener" href="invoice_print.php?id=<?=e($invoice['id'])?>"><?=e(t('common.print_pdf'))?></a><?php endif;?></td>
</tr><?php endforeach;?></tbody></table><?php if(!$invoices) echo emptyState('empty.no_invoices'); ?></div>
<?php backofficeFooter();
