<?php
require_once __DIR__ . '/_layout.php';
requirePermission('payments.manage');

if(requestMethod()==='POST'){
    requireCsrfPost();
    try{
        $agencyId=(int)($_POST['agency_id']??0);requireAgencyAccess($agencyId);$action=$_POST['action']??'';
        if($action==='open'){
            $opening=positiveMoney($_POST['opening_balance']??'');$date=validDateValue($_POST['business_date']??'');
            if($opening===null||!$date)throw new InvalidArgumentException('Enter a valid business date and opening balance.');
            dbExecute("INSERT INTO cash_registers(agency_id,business_date,opening_balance,status,opened_by)VALUES(:agency,:date,:opening,'open',:user)",['agency'=>$agencyId,'date'=>$date->format('Y-m-d'),'opening'=>$opening,'user'=>currentUserId()]);
            auditLog('cash_register.opened','cash_register',db()->lastInsertId(),null,['opening_balance'=>$opening],$agencyId);flash('success','Cash register opened.');
        }elseif($action==='close'){
            $register=dbFetchOne("SELECT * FROM cash_registers WHERE id=:id AND status='open' FOR UPDATE",['id'=>(int)($_POST['id']??0)]);if(!$register)throw new InvalidArgumentException('Open cash register not found.');requireAgencyAccess($register['agency_id']);
            $cash=dbFetchOne("SELECT COALESCE(SUM(amount),0) total FROM payments WHERE agency_id=:agency AND method='cash' AND status='paid' AND DATE(paid_at)=:date",['agency'=>$register['agency_id'],'date'=>$register['business_date']])['total'];
            $openingCents=moneyToCents($register['opening_balance']);$cashCents=moneyToCents($cash);$actualCents=moneyToCents($_POST['actual_balance']??'');if($openingCents===null||$cashCents===null||$actualCents===null||$actualCents<0)throw new InvalidArgumentException('Enter a valid counted cash balance.');
            $expectedCents=$openingCents+$cashCents;$differenceCents=$actualCents-$expectedCents;$expected=centsToMoney($expectedCents);$actual=centsToMoney($actualCents);$difference=centsToMoney($differenceCents);
            dbExecute("UPDATE cash_registers SET expected_balance=:expected,actual_balance=:actual,difference_amount=:difference,status='closed',closed_at=NOW(),closed_by=:user,notes=:notes WHERE id=:id",['expected'=>$expected,'actual'=>$actual,'difference'=>$difference,'user'=>currentUserId(),'notes'=>trim((string)($_POST['notes']??'')),'id'=>$register['id']]);
            auditLog('cash_register.closed','cash_register',$register['id'],['status'=>'open'],['status'=>'closed','expected'=>$expected,'actual'=>$actual,'difference'=>$difference],$register['agency_id']);flash('success','Cash register closed.');
        }
    }catch(InvalidArgumentException|DomainException $exception){flash('danger',$exception->getMessage());}
    catch(Throwable $exception){reportDatabaseError($exception,'Cash register operation failed');flash('danger','The cash register operation could not be completed.');}
    safeRedirect('cash.php');
}

$ids=currentAgencyIds();if(!$ids)$ids=[0];$ph=implode(',',array_fill(0,count($ids),'?'));
$agencies=dbFetchAll("SELECT id,name FROM agencies WHERE id IN ($ph)",$ids);
$registers=dbFetchAll("SELECT cr.*,a.name agency_name FROM cash_registers cr JOIN agencies a ON a.id=cr.agency_id WHERE cr.agency_id IN ($ph) ORDER BY cr.business_date DESC LIMIT 60",$ids);
backofficeHeader('Cash register','cash.php'); ?>
<div class="page-head"><h1>Daily cash register</h1></div><div class="grid"><section class="card"><h2>Open day</h2><form method="post"><?=csrfField()?><input type="hidden" name="action" value="open"><label>Agency<select name="agency_id"><?php foreach($agencies as $a):?><option value="<?=e($a['id'])?>"><?=e($a['name'])?></option><?php endforeach;?></select></label><label>Business date<input type="date" name="business_date" value="<?=e(date('Y-m-d'))?>"></label><label>Opening balance<input name="opening_balance" value="0.00"></label><button class="btn primary">Open register</button></form></section><section class="card"><h2>Register history</h2><div class="table-wrap"><table><tr><th>Date</th><th>Agency</th><th>Opening</th><th>Expected</th><th>Actual</th><th>Difference</th><th>Status / close</th></tr><?php foreach($registers as $r):?><tr><td><?=e($r['business_date'])?></td><td><?=e($r['agency_name'])?></td><td><?=money($r['opening_balance'])?></td><td><?=money($r['expected_balance'])?></td><td><?=money($r['actual_balance'])?></td><td><?=money($r['difference_amount'])?></td><td><?=statusBadge($r['status'])?><?php if($r['status']==='open'):?><form method="post"><?=csrfField()?><input type="hidden" name="action" value="close"><input type="hidden" name="agency_id" value="<?=e($r['agency_id'])?>"><input type="hidden" name="id" value="<?=e($r['id'])?>"><input name="actual_balance" required placeholder="Counted balance"><input name="notes" placeholder="Closing notes"><button class="btn primary">Close</button></form><?php endif;?></td></tr><?php endforeach;?></table></div></section></div><?php backofficeFooter();
