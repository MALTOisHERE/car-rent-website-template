<?php
require_once __DIR__.'/_layout.php';
requirePermission('contract.view');
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:(int)($_POST['contract_id']??0);
if(!$id){http_response_code(404);exit(t('validation.contract_not_found'));}
if(requestMethod()==='POST'){
    requireCsrfPost();$action=(string)($_POST['action']??'');
    try{
        if($action==='issue'){
            contractIssue(['contract_id'=>$id,'idempotency_key'=>$_POST['idempotency_key']??'']);
            flash('success',t('message.contract_issued'));
        }elseif($action==='cancel'){
            contractCancel(['contract_id'=>$id,'reason'=>$_POST['reason']??'','idempotency_key'=>$_POST['idempotency_key']??'']);
            flash('success',t('message.contract_cancelled'));
        }else throw new InvalidArgumentException(t('validation.invalid_action'));
    }catch(InvalidArgumentException|DomainException|AuthorizationException$exception){
        flash('danger',$exception->getMessage());
    }catch(Throwable$exception){
        reportDatabaseError($exception,'Contract detail operation failed');flash('danger',t('message.contract_failed'));
    }
    safeRedirect('contract_detail.php?id='.$id);
}
try{$data=contractScopedDetail($id);}catch(InvalidArgumentException$exception){http_response_code(404);exit(t('validation.contract_not_found'));}
$contract=$data['contract'];
backofficeHeader(t('page.contract_detail.title'),'contracts.php');
pageHeader('page.contract_detail.title','page.contract_detail.description',[
    'breadcrumbs'=>[['label'=>'nav.contracts','href'=>'contracts.php'],['label'=>$contract['contract_number']]],
    'secondary'=>['label'=>'common.back','href'=>'contracts.php'],
]);
?>
<div class="grid"><section class="card"><h2><?=e(t('section.contract_identity'))?></h2><dl class="detail-list">
<div><dt><?=e(t('field.number'))?></dt><dd><?=isolatedValue($contract['contract_number'],'reference-value')?></dd></div>
<div><dt><?=e(t('field.agency'))?></dt><dd><?=e($contract['agency_name'])?></dd></div>
<div><dt><?=e(t('field.reservation'))?></dt><dd><a href="reservation_detail.php?id=<?=e($contract['reservation_id'])?>"><?=isolatedValue($contract['reference'],'reference-value')?></a></dd></div>
<div><dt><?=e(t('common.status'))?></dt><dd><?=statusBadge($contract['status'])?></dd></div>
<div><dt><?=e(t('field.current_version'))?></dt><dd><?=e($contract['current_version']?:t('common.none'))?><?php if($contract['current_version_id']):?> · #<?=e($contract['current_version_id'])?><?php endif;?></dd></div>
<div><dt><?=e(t('field.issued'))?></dt><dd><?=formattedDateTime($contract['issued_at'])?></dd></div>
<?php if($contract['cancelled_at']):?><div><dt><?=e(t('field.cancelled'))?></dt><dd><?=formattedDateTime($contract['cancelled_at'])?><br><?=e($contract['cancellation_reason'])?></dd></div><?php endif;?>
</dl></section><section class="card"><h2><?=e(t('section.rental_summary'))?></h2><p><strong><?=e(t('field.customer'))?>:</strong> <?=e($contract['first_name'].' '.$contract['last_name'])?></p><p><strong><?=e(t('field.vehicle'))?>:</strong> <?=e(trim($contract['brand'].' '.$contract['model']))?> <?=isolatedValue($contract['registration_number'],'registration-value')?></p><p><strong><?=e(t('field.period'))?>:</strong> <?=formattedDateTime($contract['pickup_at'])?> — <?=formattedDateTime($contract['return_at'])?></p><p><strong><?=e(t('field.total'))?>:</strong> <?=money($contract['total_amount'],$contract['currency'])?></p></section></div>
<?php if(($contract['status']==='draft'&&can('contract.issue'))||(in_array($contract['status'],['draft','issued'],true)&&can('contract.cancel'))):?><section class="card"><h2><?=e(t('section.allowed_actions'))?></h2><div class="inline-actions">
<?php if($contract['status']==='draft'&&can('contract.issue')):?><form method="post"><?=csrfField()?><?=contractIdempotencyField('contract.issue')?><input type="hidden" name="contract_id" value="<?=e($id)?>"><input type="hidden" name="action" value="issue"><button class="btn primary" data-confirm="<?=e(t('confirm.issue_contract'))?>"><?=e(t('action.issue_contract'))?></button></form><?php endif;?>
<?php if(in_array($contract['status'],['draft','issued'],true)&&can('contract.cancel')):?><form method="post"><?=csrfField()?><?=contractIdempotencyField('contract.cancel')?><input type="hidden" name="contract_id" value="<?=e($id)?>"><input type="hidden" name="action" value="cancel"><label><?=e(t('field.cancellation_reason'))?><input name="reason" maxlength="255" required></label><button class="btn danger" data-confirm="<?=e(t('confirm.cancel_contract'))?>"><?=e(t('action.cancel_contract'))?></button></form><?php endif;?>
</div></section><?php endif;?>
<section class="card"><h2><?=e(t('section.contract_versions'))?></h2><div class="table-wrap"><table><thead><tr><th><?=e(t('field.version'))?></th><th><?=e(t('field.language'))?></th><th><?=e(t('field.digest'))?></th><th><?=e(t('field.created'))?></th><th><?=e(t('common.actions'))?></th></tr></thead><tbody><?php foreach($data['versions']as$version):?><tr><td><?=e($version['version_number'])?></td><td><?=e(t('language.'.$version['language_code']))?></td><td><code><?=e($version['snapshot_sha256'])?></code></td><td><?=formattedDateTime($version['created_at'])?></td><td><a href="contract_print.php?id=<?=e($id)?>&lang=<?=e($version['language_code'])?>" target="_blank"><?=e(t('common.print_pdf'))?></a></td></tr><?php endforeach;?></tbody></table><?php if(!$data['versions'])echo emptyState('empty.no_contract_versions','message.contract_issue_creates_versions');?></div></section>
<section class="card"><h2><?=e(t('section.contract_history'))?></h2><div class="table-wrap"><table><thead><tr><th><?=e(t('field.date_time'))?></th><th><?=e(t('field.transition'))?></th><th><?=e(t('field.reason'))?></th><th><?=e(t('field.actor'))?></th></tr></thead><tbody><?php foreach($data['history']as$history):?><tr><td><?=formattedDateTime($history['occurred_at'])?></td><td><?=e($history['from_status']?translatedStatus($history['from_status']).' → ':'')?><?=e(translatedStatus($history['to_status']))?></td><td><?=e($history['reason'])?></td><td><?=e($history['changed_by_name'])?></td></tr><?php endforeach;?></tbody></table></div></section>
<?php backofficeFooter();
