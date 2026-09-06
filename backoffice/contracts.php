<?php
require_once __DIR__.'/_layout.php';
requirePermission('contract.view');

$filters=['status'=>$_GET['status']??'','reservation_id'=>(int)($_GET['reservation_id']??0)];
$contracts=contractScopedList($filters);
backofficeHeader(t('page.contracts.title'),'contracts.php');
pageHeader('page.contracts.title','page.contracts.description',[
    'breadcrumbs'=>[['label'=>'nav.rentals'],['label'=>'nav.contracts']],
    'primary'=>can('contract.create') ? ['label'=>'action.create_contract','href'=>'contract_form.php'] : null,
]);
?>
<form class="filters" method="get"><label><?=e(t('common.status'))?><select name="status"><option value=""><?=e(t('common.all'))?></option><?php foreach(contractLifecycleStatuses()as$status):?><option value="<?=e($status)?>" <?=$filters['status']===$status?'selected':''?>><?=e(translatedStatus($status))?></option><?php endforeach;?></select></label><button class="btn secondary"><?=e(t('common.filter'))?></button><a class="btn ghost" href="contracts.php"><?=e(t('common.reset'))?></a></form>
<section class="card"><div class="section-heading"><div class="section-card-header"><h2><?=navigationIcon('rentals')?><?=e(t('section.contract_register'))?></h2></div><span><?=e(t('message.record_count',['count'=>count($contracts)]))?></span></div><div class="table-wrap"><table><thead><tr><th><?=e(t('field.number'))?></th><th><?=e(t('field.agency'))?></th><th><?=e(t('field.reservation'))?></th><th><?=e(t('field.customer'))?></th><th><?=e(t('field.vehicle'))?></th><th><?=e(t('field.version'))?></th><th><?=e(t('common.status'))?></th><th><?=e(t('common.actions'))?></th></tr></thead><tbody>
<?php foreach($contracts as$contract):?><tr><td><?=isolatedValue($contract['contract_number'],'reference-value')?></td><td><?=e($contract['agency_name'])?></td><td><?=isolatedValue($contract['reference'],'reference-value')?></td><td><?=e($contract['first_name'].' '.$contract['last_name'])?></td><td><?=isolatedValue($contract['registration_number'],'registration-value')?></td><td><?=e($contract['current_version'])?></td><td><?=statusBadge($contract['status'])?></td><td><?=actionMenu([['label'=>'common.view','href'=>'contract_detail.php?id='.$contract['id']]])?></td></tr><?php endforeach;?>
</tbody></table><?php if(!$contracts)echo emptyState('empty.no_filtered_records','empty.adjust_filters');?></div></section>
<?php backofficeFooter();
