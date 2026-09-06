<?php
require_once __DIR__ . '/_layout.php';
requirePermission('users.manage');
if(requestMethod()==='POST'){
    requireCsrfPost();$action=$_POST['action']??'';
    if($action==='status'&&currentUserRole()!==ROLE_OWNER){
        $targetId=(int)($_POST['id']??0);$scopedIds=currentAgencyIds();
        if(!$scopedIds)throw new DomainException(t('validation.no_agency_assignment'));
        $scopePlaceholders=implode(',',array_fill(0,count($scopedIds),'?'));
        $scopeParameters=array_merge([$targetId],$scopedIds);
        if(!dbFetchOne("SELECT ua.user_id FROM user_agencies ua WHERE ua.user_id=? AND ua.agency_id IN ($scopePlaceholders) LIMIT 1",$scopeParameters))throw new DomainException(t('validation.user_scope'));
    }
    try{
        if($action==='status'){
            $id=(int)($_POST['id']??0);$status=validateChoice($_POST['status']??'',['active','inactive'],null);$user=dbFetchOne('SELECT * FROM users WHERE id=:id',['id'=>$id]);if(!$user||!$status)throw new InvalidArgumentException(t('validation.invalid_user_status'));if($user['role']===ROLE_OWNER&&currentUserRole()!==ROLE_OWNER)throw new DomainException(t('validation.owner_change'));if($id===currentUserId()&&$status==='inactive')throw new DomainException(t('validation.self_deactivation'));dbExecute('UPDATE users SET status=:status,sessions_invalid_before=IF(:status2=\'inactive\',NOW(),sessions_invalid_before) WHERE id=:id',['status'=>$status,'status2'=>$status,'id'=>$id]);auditLog('user.status_changed','user',$id,['status'=>$user['status']],['status'=>$status]);flash('success',t('message.user_status_updated'));
        }
    }catch(InvalidArgumentException|DomainException $exception){flash('danger',$exception->getMessage());}catch(Throwable $exception){reportDatabaseError($exception,'User management failed');flash('danger',t('message.user_failed'));}safeRedirect('users.php');
}
$scopedIds=currentAgencyIds();
if(currentUserRole()===ROLE_OWNER){$users=dbFetchAll('SELECT u.*,GROUP_CONCAT(a.name ORDER BY a.name SEPARATOR ", ") agency_names FROM users u LEFT JOIN user_agencies ua ON ua.user_id=u.id LEFT JOIN agencies a ON a.id=ua.agency_id WHERE u.archived_at IS NULL GROUP BY u.id ORDER BY u.fullname');}
else{$scopePlaceholders=implode(',',array_fill(0,count($scopedIds),'?'));$users=$scopedIds?dbFetchAll("SELECT u.*,GROUP_CONCAT(DISTINCT a.name ORDER BY a.name SEPARATOR ', ') agency_names FROM users u JOIN user_agencies access_scope ON access_scope.user_id=u.id AND access_scope.agency_id IN ($scopePlaceholders) LEFT JOIN user_agencies ua ON ua.user_id=u.id LEFT JOIN agencies a ON a.id=ua.agency_id WHERE u.archived_at IS NULL GROUP BY u.id ORDER BY u.fullname",$scopedIds):[];}
backofficeHeader(t('page.users.title'),'users.php');?><?php pageHeader('page.users.title', 'page.users.description', ['breadcrumbs'=>[['label'=>'nav.administration'],['label'=>'nav.users']],'primary'=>['label'=>'action.add_user','href'=>'user_form.php']]); ?><div class="grid"><section class="card full"><div class="section-card-header"><h2><?=navigationIcon('admin')?><?=e(t('section.users'))?></h2></div><div class="table-wrap"><table><tr><th><?=e(t('field.name'))?></th><th><?=e(t('field.role'))?></th><th><?=e(t('nav.agencies'))?></th><th><?=e(t('common.status'))?></th><th><?=e(t('field.last_login'))?></th></tr><?php foreach($users as $user):?><tr><td><?=e($user['fullname'])?><br><small><?=isolatedValue($user['email'],'email-value')?></small></td><td><?=roleBadge($user['role'])?></td><td><?=e($user['agency_names'])?></td><td><form method="post"><?=csrfField()?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?=e($user['id'])?>"><select name="status" onchange="this.form.submit()"><?php foreach(['active','inactive'] as $status):?><option value="<?=e($status)?>" <?=$user['status']===$status?'selected':''?>><?=e(translatedStatus($status))?></option><?php endforeach;?></select></form></td><td><?=formattedDateTime($user['last_login_at'])?></td></tr><?php endforeach;?></table></div></section></div><?php backofficeFooter();
