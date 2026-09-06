<?php
require_once __DIR__ . '/_layout.php';
requirePermission('payments.create');
$canManageFinance=canViewFinanceHistory();

[$agencyIds,$ph]=financeScopedPlaceholders(currentAgencyIds());
$payments=$canManageFinance?dbFetchAll("SELECT p.id,p.payment_number,p.amount,p.currency,p.method,p.status,p.paid_at,r.reference reservation_reference FROM payments p JOIN reservations r ON r.id=p.reservation_id WHERE p.agency_id IN ($ph) ORDER BY p.paid_at DESC,p.id DESC LIMIT 100",$agencyIds):[];
backofficeHeader(t('page.finance.title'),'finance.php');
pageHeader('page.finance.title','page.finance.description',[
    'breadcrumbs'=>[['label'=>'nav.finance'],['label'=>'nav.payments']],
    'primary'=>['label'=>'action.record_payment','href'=>'payment_form.php'],
    'secondary'=>can('invoices.manage')?['label'=>'page.invoices.title','href'=>'invoices.php']:null,
]);
?>
<div class="grid"><section class="card full">
<?php if($canManageFinance):?>
<div class="section-card-header"><h2><?=navigationIcon('finance')?><?=e(t('section.recent_payments'))?></h2><?php if(can('payments.excess')):?><a class="btn secondary compact" href="excess_payment_form.php"><?=e(t('action.allocate_excess'))?></a><?php endif;?></div>
<div class="table-wrap"><table><thead><tr><th><?=e(t('field.number'))?></th><th><?=e(t('field.reservation'))?></th><th><?=e(t('field.amount'))?></th><th><?=e(t('field.method'))?></th><th><?=e(t('common.status'))?></th></tr></thead><tbody><?php foreach($payments as$p):?><tr><td><a class="detail-link" href="payment_detail.php?id=<?=e($p['id'])?>"><?=isolatedValue($p['payment_number'],'reference-value')?></a></td><td><?=isolatedValue($p['reservation_reference'],'reference-value')?></td><td><?=money($p['amount'],$p['currency'])?></td><td><?=e(t('option.'.$p['method']))?></td><td><?=statusBadge($p['status'])?></td></tr><?php endforeach;?></tbody></table></div><?php if(!$payments)echo emptyState('empty.no_payments');?>
<?php else:?><div class="section-card-header"><h2><?=navigationIcon('finance')?><?=e(t('section.payment_access'))?></h2></div><p><?=e(t('message.payment_access'))?></p><?php endif;?>
</section></div>
<?php backofficeFooter();
