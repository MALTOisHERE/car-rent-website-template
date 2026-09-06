<?php
require_once __DIR__ . '/_layout.php';
requirePermission('deposits.manage');

[$agencyIds,$ph]=financeScopedPlaceholders(currentAgencyIds());
$deposits=dbFetchAll("SELECT d.id,d.amount,d.received_amount,d.retained_amount,d.returned_amount,d.currency,d.status,r.reference FROM deposits d JOIN reservations r ON r.id=d.reservation_id WHERE d.agency_id IN ($ph) ORDER BY d.created_at DESC,d.id DESC LIMIT 100",$agencyIds);
backofficeHeader(t('page.deposits.title'),'deposits.php');
pageHeader('page.deposits.title','page.deposits.description',[
    'breadcrumbs'=>[['label'=>'nav.finance'],['label'=>'nav.deposits']],
    'primary'=>['label'=>'action.request_deposit','href'=>'deposit_form.php'],
]);
?>
<section class="card"><div class="table-wrap"><table><thead><tr><th><?=e(t('field.reservation'))?></th><th><?=e(t('field.received'))?></th><th><?=e(t('field.retained'))?></th><th><?=e(t('field.returned'))?></th><th><?=e(t('common.status'))?></th></tr></thead><tbody><?php foreach($deposits as$d):?><tr><td><a class="detail-link" href="deposit_detail.php?id=<?=e($d['id'])?>"><?=isolatedValue($d['reference'],'reference-value')?></a></td><td><?=money($d['received_amount']??0,$d['currency'])?></td><td><?=money($d['retained_amount'],$d['currency'])?></td><td><?=money($d['returned_amount']??0,$d['currency'])?></td><td><?=statusBadge($d['status'])?></td></tr><?php endforeach;?></tbody></table><?php if(!$deposits)echo emptyState('empty.no_records');?></div></section>
<?php backofficeFooter();
