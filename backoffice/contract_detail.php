<?php
require_once __DIR__.'/_layout.php';
requirePermission('contract.view');
$rawId=$_GET['id']??($_POST['contract_id']??null);$id=is_string($rawId)&&preg_match('/^[1-9]\d*$/',$rawId)?(int)$rawId:0;
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
        }elseif($action==='acknowledge'){
            $rawVersionId=$_POST['contract_version_id']??null;if(!is_string($rawVersionId)||!preg_match('/^[1-9]\d*$/',$rawVersionId))throw new InvalidArgumentException(t('validation.contract_version_not_found'));
            contractRecordAcknowledgement([
                'contract_id'=>$id,'contract_version_id'=>(int)$rawVersionId,
                'acknowledgement_type'=>$_POST['acknowledgement_type']??'', 'language_code'=>$_POST['language_code']??'',
                'party_name'=>$_POST['party_name']??'', 'acknowledgement_method'=>$_POST['acknowledgement_method']??'',
                'idempotency_key'=>$_POST['idempotency_key']??'',
            ]);
            flash('success',t('message.contract_acknowledged'));
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
$cCurrency=strtoupper((string)$contract['currency']);
$cRentalDays=null;
if($contract['pickup_at']&&$contract['return_at']){
    $cRentalDays=max(1,(int)ceil((strtotime($contract['return_at'])-strtotime($contract['pickup_at']))/86400));
}
?>
<section class="detail-hero"><div><div class="section-card-header"><h2><?=navigationIcon('rentals')?><?=isolatedValue($contract['contract_number'],'reference-value')?></h2></div><p><?=e($contract['agency_name'])?></p></div><?=statusBadge($contract['status'])?></section>
<div class="stat-grid">
<article class="stat-card"><span class="stat-icon"><?=navigationIcon('finance')?></span><div class="stat-body"><span><?=e(t('field.total'))?> (<?=e($cCurrency)?>)</span><strong><?=statMoney($contract['total_amount'],$contract['currency'])?></strong></div></article>
<?php if($contract['deposit_amount']!==null):?><article class="stat-card"><span class="stat-icon warning"><?=navigationIcon('finance')?></span><div class="stat-body"><span><?=e(t('field.deposit'))?> (<?=e($cCurrency)?>)</span><strong><?=statMoney($contract['deposit_amount'],$contract['currency'])?></strong></div></article><?php endif;?>
<?php if($cRentalDays!==null):?><article class="stat-card"><span class="stat-icon info"><?=navigationIcon('rentals')?></span><div class="stat-body"><span><?=e(t('field.rental_days'))?></span><strong><?=e($cRentalDays)?></strong></div></article><?php endif;?>
<article class="stat-card"><span class="stat-icon neutral"><?=navigationIcon('rentals')?></span><div class="stat-body"><span><?=e(t('field.current_version'))?></span><strong><?=e($contract['current_version']?:t('common.none'))?></strong></div></article>
</div>
<div class="grid"><section class="card"><div class="section-card-header"><h2><?=navigationIcon('customers')?><?=e(t('section.contract_identity'))?></h2></div><dl class="detail-list">
<div><dt><?=e(t('field.number'))?></dt><dd><?=isolatedValue($contract['contract_number'],'reference-value')?></dd></div>
<div><dt><?=e(t('field.agency'))?></dt><dd><?=e($contract['agency_name'])?></dd></div>
<div><dt><?=e(t('field.reservation'))?></dt><dd><a href="reservation_detail.php?id=<?=e($contract['reservation_id'])?>"><?=isolatedValue($contract['reference'],'reference-value')?></a></dd></div>
<div><dt><?=e(t('field.issued'))?></dt><dd><?=formattedDateTime($contract['issued_at'])?></dd></div>
<?php if($contract['signed_at']):?><div><dt><?=e(t('field.signed'))?></dt><dd><?=formattedDateTime($contract['signed_at'])?></dd></div><?php endif;?>
<?php if($contract['cancelled_at']):?><div><dt><?=e(t('field.cancelled'))?></dt><dd><?=formattedDateTime($contract['cancelled_at'])?><br><?=e($contract['cancellation_reason'])?></dd></div><?php endif;?>
</dl></section><section class="card"><div class="section-card-header"><h2><?=navigationIcon('rentals')?><?=e(t('section.rental_summary'))?></h2></div><dl class="detail-list">
<div><dt><?=e(t('field.customer'))?></dt><dd><?=e($contract['first_name'].' '.$contract['last_name'])?></dd></div>
<div><dt><?=e(t('field.vehicle'))?></dt><dd><?=e(trim($contract['brand'].' '.$contract['model']))?> <?=isolatedValue($contract['registration_number'],'registration-value')?></dd></div>
<div><dt><?=e(t('field.period'))?></dt><dd><?=formattedDateTime($contract['pickup_at'])?> — <?=formattedDateTime($contract['return_at'])?></dd></div>
</dl></section></div>
<?php if(($contract['status']==='draft'&&can('contract.issue'))||(in_array($contract['status'],['draft','issued'],true)&&can('contract.cancel'))):?><section class="card"><div class="section-card-header"><h2><?=navigationIcon('rentals')?><?=e(t('section.allowed_actions'))?></h2></div><div class="inline-actions">
<?php if($contract['status']==='draft'&&can('contract.issue')):?><form method="post"><?=csrfField()?><?=contractIdempotencyField('contract.issue')?><input type="hidden" name="contract_id" value="<?=e($id)?>"><input type="hidden" name="action" value="issue"><button class="btn primary" data-confirm="<?=e(t('confirm.issue_contract'))?>"><?=e(t('action.issue_contract'))?></button></form><?php endif;?>
<?php if(in_array($contract['status'],['draft','issued'],true)&&can('contract.cancel')):?><form method="post"><?=csrfField()?><?=contractIdempotencyField('contract.cancel')?><input type="hidden" name="contract_id" value="<?=e($id)?>"><input type="hidden" name="action" value="cancel"><label><?=e(t('field.cancellation_reason'))?><input name="reason" maxlength="255" required></label><button class="btn danger" data-confirm="<?=e(t('confirm.cancel_contract'))?>"><?=e(t('action.cancel_contract'))?></button></form><?php endif;?>
</div></section><?php endif;?>
<?php $ackByType=[];foreach($data['acknowledgements']as$ack)$ackByType[$ack['acknowledgement_type']]=$ack;?>
<section class="card"><div class="section-card-header"><h2><?=navigationIcon('rentals')?><?=e(t('section.contract_acknowledgements'))?></h2></div>
<?php if($contract['status']==='issued'):?><p><?=e(t('message.contract_acknowledgement_help'))?></p><?php endif;?>
<div class="table-wrap"><table><thead><tr><th><?=e(t('field.type'))?></th><th><?=e(t('field.party_name'))?></th><th><?=e(t('field.actor'))?></th><th><?=e(t('field.language'))?></th><th><?=e(t('field.method'))?></th><th><?=e(t('field.date_time'))?></th></tr></thead><tbody>
<?php foreach(['customer','agency_representative']as$type):$ack=$ackByType[$type]??null;?><tr><td><?=e(t('acknowledgement.'.$type))?></td><?php if($ack):?><td><?=e($ack['party_name'])?></td><td><?=e($ack['recorded_by_name'])?></td><td><?=e(t('language.'.$ack['language_code']))?></td><td><?=e(t('acknowledgement.method_'.$ack['acknowledgement_method']))?></td><td><?=formattedDateTime($ack['acknowledged_at'])?></td><?php else:?><td colspan="5"><?=e(t('common.pending'))?></td><?php endif;?></tr><?php endforeach;?></tbody></table></div>
<?php if($contract['status']==='issued'&&$contract['current_version_id']):?><div class="inline-actions">
<?php foreach(['customer','agency_representative']as$type):if(!isset($ackByType[$type])&&contractCanRecordAcknowledgement($contract,$type)): $party=$type==='customer'?trim($contract['first_name'].' '.$contract['last_name']):trim((string)(dbFetchOne('SELECT fullname FROM users WHERE id=:id',['id'=>currentUserId()])['fullname']??''));?><form method="post"><?=csrfField()?><?=contractIdempotencyField('contract_acknowledgement')?><input type="hidden" name="contract_id" value="<?=e($id)?>"><input type="hidden" name="action" value="acknowledge"><input type="hidden" name="contract_version_id" value="<?=e($contract['current_version_id'])?>"><input type="hidden" name="acknowledgement_type" value="<?=e($type)?>"><input type="hidden" name="language_code" value="<?=e($contract['current_version_language'])?>"><input type="hidden" name="acknowledgement_method" value="in_person"><input type="hidden" name="party_name" value="<?=e($party)?>"><button class="btn primary"><?=e(t('action.acknowledge_'.$type))?></button></form><?php endif;endforeach;?></div><?php endif;?>
</section>
<section class="card"><div class="section-card-header"><h2><?=navigationIcon('rentals')?><?=e(t('section.contract_versions'))?></h2></div><div class="table-wrap"><table><thead><tr><th><?=e(t('field.version'))?></th><th><?=e(t('field.language'))?></th><th><?=e(t('field.digest'))?></th><th><?=e(t('field.created'))?></th><th><?=e(t('common.actions'))?></th></tr></thead><tbody><?php foreach($data['versions']as$version):?><tr><td><?=e($version['version_number'])?></td><td><?=e(t('language.'.$version['language_code']))?></td><td><code><?=e($version['snapshot_sha256'])?></code></td><td><?=formattedDateTime($version['created_at'])?></td><td><a href="contract_print.php?id=<?=e($id)?>&lang=<?=e($version['language_code'])?>" target="_blank"><?=e(t('common.print_pdf'))?></a></td></tr><?php endforeach;?></tbody></table><?php if(!$data['versions'])echo emptyState('empty.no_contract_versions','message.contract_issue_creates_versions');?></div></section>
<section class="card"><div class="section-card-header"><h2><?=navigationIcon('rentals')?><?=e(t('section.contract_history'))?></h2></div><div class="table-wrap"><table><thead><tr><th><?=e(t('field.date_time'))?></th><th><?=e(t('field.transition'))?></th><th><?=e(t('field.reason'))?></th><th><?=e(t('field.actor'))?></th></tr></thead><tbody><?php foreach($data['history']as$history):?><tr><td><?=formattedDateTime($history['occurred_at'])?></td><td><?=e($history['from_status']?translatedStatus($history['from_status']).' → ':'')?><?=e(translatedStatus($history['to_status']))?></td><td><?=e($history['reason'])?></td><td><?=e($history['changed_by_name'])?></td></tr><?php endforeach;?></tbody></table></div></section>
<?php backofficeFooter();
