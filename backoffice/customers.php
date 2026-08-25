<?php
require_once __DIR__ . '/_layout.php';
requirePermission('customers.manage');
$agencyIds=currentAgencyIds();if(!$agencyIds)$agencyIds=[0];
$agencies=dbFetchAll('SELECT id,name FROM agencies WHERE archived_at IS NULL AND status=\'active\' ORDER BY name');
$agencyId=(int)($_GET['agency_id']??$agencyIds[0]);requireAgencyAccess($agencyId);
[$page,$size,$offset]=paginationParameters();$q=trim((string)($_GET['q']??''));$status=validateChoice($_GET['status']??'',approvedCustomerStatuses(),'');$type=validateChoice($_GET['type']??'',['individual','company'],'');$licence=validateChoice($_GET['licence']??'',['valid','expiring','expired','missing'],'');
$where=['c.agency_id=:agency','c.archived_at IS NULL'];$params=['agency'=>$agencyId];
if($q!==''){$where[]='(c.first_name LIKE :q1 OR c.last_name LIKE :q2 OR c.email LIKE :q3 OR c.phone LIKE :q4 OR c.identity_number LIKE :q5 OR c.licence_number LIKE :q6)';foreach(range(1,6)as$i)$params['q'.$i]='%'.$q.'%';}
if($status!==''){$where[]='c.status=:status';$params['status']=$status;}if($type!==''){$where[]='c.customer_type=:type';$params['type']=$type;}
if($licence==='valid')$where[]='c.licence_expires_at>DATE_ADD(CURDATE(),INTERVAL 30 DAY)';elseif($licence==='expiring')$where[]='c.licence_expires_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY)';elseif($licence==='expired')$where[]='c.licence_expires_at<CURDATE()';elseif($licence==='missing')$where[]='c.licence_expires_at IS NULL';
$whereSql=implode(' AND ',$where);$count=(int)dbFetchOne("SELECT COUNT(*) n FROM customers c WHERE $whereSql",$params)['n'];
$customers=dbFetchAll("SELECT c.*,COUNT(r.id) reservation_count,COALESCE(SUM(r.remaining_amount),0) outstanding_balance,MAX(r.return_at) last_rental FROM customers c LEFT JOIN reservations r ON r.customer_id=c.id AND r.archived_at IS NULL WHERE $whereSql GROUP BY c.id ORDER BY c.updated_at DESC,c.id DESC LIMIT $size OFFSET $offset",$params);$hasNext=$offset+count($customers)<$count;
backofficeHeader(t('page.customers.title'),'customers.php');
pageHeader('page.customers.title','page.customers.description',['breadcrumbs'=>[['label'=>'nav.customers']],'primary'=>['label'=>'action.add_customer','href'=>'customer_form.php']]);
?>
<form class="filters" method="get">
<label><?=e(t('common.search'))?><input name="q" value="<?=e($q)?>" placeholder="<?=e(t('placeholder.customer_search'))?>"></label>
<label><?=e(t('field.agency'))?><select name="agency_id"><?php foreach($agencies as$a):if(currentUserRole()!==ROLE_OWNER&&!in_array((int)$a['id'],$agencyIds,true))continue;?><option value="<?=e($a['id'])?>" <?=$agencyId===(int)$a['id']?'selected':''?>><?=e($a['name'])?></option><?php endforeach;?></select></label>
<label><?=e(t('common.status'))?><select name="status"><option value=""><?=e(t('common.all'))?></option><?php foreach(approvedCustomerStatuses()as$s):if($s==='archived')continue;?><option value="<?=e($s)?>" <?=$status===$s?'selected':''?>><?=e(translatedStatus($s))?></option><?php endforeach;?></select></label>
<label><?=e(t('field.type'))?><select name="type"><option value=""><?=e(t('common.all'))?></option><?php foreach(['individual','company']as$v):?><option value="<?=$v?>" <?=$type===$v?'selected':''?>><?=e(t('option.'.$v))?></option><?php endforeach;?></select></label>
<label><?=e(t('field.licence'))?><select name="licence"><option value=""><?=e(t('common.all'))?></option><?php foreach(['valid','expiring','expired','missing']as$v):?><option value="<?=$v?>" <?=$licence===$v?'selected':''?>><?=e(t('option.licence_'.$v))?></option><?php endforeach;?></select></label>
<button class="btn secondary"><?=e(t('common.filter'))?></button><a class="btn ghost" href="customers.php?agency_id=<?=e($agencyId)?>"><?=e(t('common.reset'))?></a></form>
<section class="card"><div class="section-heading"><div class="section-card-header"><h2><?=navigationIcon('customers')?><?=e(t('section.customer_records'))?></h2></div><span><?=e(t('message.record_count',['count'=>$count]))?></span></div>
<div class="table-wrap"><table><thead><tr><th><?=e(t('field.name'))?></th><th><?=e(t('field.contact'))?></th><th><?=e(t('field.licence'))?></th><th><?=e(t('field.rentals'))?></th><th><?=e(t('field.balance'))?></th><th><?=e(t('field.last_rental'))?></th><th><?=e(t('common.status'))?></th><th><?=e(t('common.actions'))?></th></tr></thead><tbody>
<?php foreach($customers as$c):?><tr><td><a href="customer_detail.php?id=<?=e($c['id'])?>"><strong><?=e($c['first_name'].' '.$c['last_name'])?></strong></a><br><small><?=e(t('option.'.$c['customer_type']))?></small></td><td><?=isolatedValue($c['email'],'email-value')?><br><?=isolatedValue($c['phone'],'phone-value')?></td><td><?=isolatedValue($c['licence_number'],'code-value')?><br><?=formattedDate($c['licence_expires_at'])?></td><td><?=e($c['reservation_count'])?></td><td><?=money($c['outstanding_balance'])?></td><td><?=formattedDateTime($c['last_rental'])?></td><td><?=statusBadge($c['status'])?></td><td><?=actionMenu([['label'=>'common.view','href'=>'customer_detail.php?id='.$c['id']],['label'=>'common.edit','href'=>'customer_form.php?id='.$c['id']],['label'=>'action.create_reservation','href'=>'reservation_form.php?customer_id='.$c['id'].'&agency_id='.$agencyId]])?></td></tr><?php endforeach;?>
</tbody></table><?php if(!$customers)echo emptyState('empty.no_filtered_records','empty.adjust_filters');?></div>
<?=pagination($page,$hasNext,'customers.php',['agency_id'=>$agencyId,'q'=>$q,'status'=>$status,'type'=>$type,'licence'=>$licence,'size'=>$size])?></section>
<?php backofficeFooter();
