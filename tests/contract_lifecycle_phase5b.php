<?php
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/../app/application.php';
require_once __DIR__.'/helpers/phase5b_fixture.php';

$failures=[];$assert=function($condition,$message)use(&$failures){if(!$condition)$failures[]=$message;};
$throws=function(callable$callback,string$class)use(&$failures){try{$callback();$failures[]='Expected '.$class.' was not thrown.';return null;}catch(Throwable$exception){if(!$exception instanceof$class)$failures[]='Expected '.$class.', got '.get_class($exception).': '.$exception->getMessage();return$exception;}};
$run='P5B_TEST_'.strtoupper(bin2hex(random_bytes(4)));$runtime=__DIR__.'/../storage/'.strtolower($run);$fixture=[];$other=[];
$cleanup=function()use(&$fixture,&$other,$runtime,&$failures){try{if($other)phase5bCleanup($other);if($fixture)phase5bCleanup($fixture);}catch(Throwable$e){$failures[]='Cleanup failed: '.$e->getMessage();}if(is_dir($runtime)){foreach(glob($runtime.'/*')?:[]as$f)if(is_file($f))unlink($f);@rmdir($runtime);}};
register_shutdown_function($cleanup);

try{
    contractRequireCutover();mkdir($runtime,0750,true);$fixture=phase5bFixture($run);$other=phase5bFixture($run.'_OTHER');
    $assert(can('contract.view')===false,'Customer unexpectedly has contract access before authentication fixture.');
    phase5bAs($fixture,ROLE_OWNER);foreach(['contract.view','contract.create','contract.issue','contract.cancel']as$permission)$assert(can($permission),'Owner missing '.$permission);
    phase5bAs($fixture,ROLE_AGENCY_MANAGER);
    foreach(['contract.view','contract.create','contract.issue','contract.cancel']as$permission)$assert(can($permission),'Manager missing '.$permission);
    phase5bAs($fixture,ROLE_RENTAL_AGENT);foreach(['contract.view','contract.create','contract.issue']as$permission)$assert(can($permission),'Rental agent missing '.$permission);$assert(!can('contract.cancel'),'Rental agent can cancel contracts');
    phase5bAs($fixture,ROLE_FLEET_AGENT);$assert(!can('contract.view')&&!can('contract.create')&&!can('contract.issue')&&!can('contract.cancel'),'Fleet agent has contract lifecycle access');
    phase5bAs($fixture,ROLE_ACCOUNTANT);$assert(!can('contract.view')&&!can('contract.create'),'Accountant has contract operational access');

    phase5bAs($fixture,ROLE_AGENCY_MANAGER);$reservation=phase5bCreateReservation($fixture,'CREATE');
    $create=['reservation_id'=>$reservation,'idempotency_key'=>phase5bToken()];$contract=contractCreateFromReservation($create);
    $row=dbFetchOne('SELECT * FROM rental_contracts WHERE id=:id',['id'=>$contract]);
    $assert($row['status']==='draft'&&(int)$row['current_version']===0&&$row['current_version_id']===null&&$row['issued_at']===null,'Creation did not produce one clean draft');
    $assert(contractCreateFromReservation($create)===$contract,'Identical creation replay did not return original result');
    $throws(fn()=>contractCreateFromReservation(['reservation_id'=>$reservation,'idempotency_key'=>phase5bToken()]),DomainException::class);
    $assert((int)dbFetchOne("SELECT COUNT(*) n FROM rental_contracts WHERE reservation_id=:id AND status IN('draft','issued','signed','active')",['id'=>$reservation])['n']===1,'Duplicate live contract was created');
    $assert((int)dbFetchOne('SELECT COUNT(*) n FROM contract_status_history WHERE contract_id=:id',['id'=>$contract])['n']===1,'Draft history baseline missing or duplicated');

    $otherReservation=phase5bCreateReservation($other,'IDOR');phase5bAs($fixture,ROLE_AGENCY_MANAGER);
    $idor=$throws(fn()=>contractCreateFromReservation(['reservation_id'=>$otherReservation,'idempotency_key'=>phase5bToken()]),InvalidArgumentException::class);
    $assert($idor&&$idor->getMessage()===t('validation.contract_reservation_not_found'),'Cross-agency reservation did not return generic not found');

    $issue=['contract_id'=>$contract,'idempotency_key'=>phase5bToken()];$assert(contractIssue($issue)===$contract,'Issue did not return contract ID');$assert(contractIssue($issue)===$contract,'Issue replay did not return original result');
    $row=dbFetchOne('SELECT * FROM rental_contracts WHERE id=:id',['id'=>$contract]);$versions=dbFetchAll('SELECT * FROM contract_versions WHERE contract_id=:id ORDER BY language_code',['id'=>$contract]);
    $assert($row['status']==='issued'&&(int)$row['current_version']===1&&(int)$row['current_version_id']>0&&$row['issued_at']!==null,'Issue lifecycle state is incomplete');
    $assert(array_column($versions,'language_code')===['ar','en','fr'],'Issue did not create exact AR/EN/FR version set');
    foreach($versions as$version)$assert((int)$version['version_number']===1&&hash('sha256',$version['snapshot_json'])===$version['snapshot_sha256'],'Version number or digest is invalid');
    $current=dbFetchOne('SELECT * FROM contract_versions WHERE id=:id',['id'=>$row['current_version_id']]);$assert($current&&$current['language_code']==='en','Authoritative current version does not reference EN version 1');
    $throws(fn()=>contractIssue(['contract_id'=>$contract,'idempotency_key'=>phase5bToken()]),DomainException::class);

    $draftReservation=phase5bCreateReservation($fixture,'CANCEL_DRAFT');$draft=contractCreateFromReservation(['reservation_id'=>$draftReservation,'idempotency_key'=>phase5bToken()]);
    $throws(fn()=>contractCancel(['contract_id'=>$draft,'reason'=>'','idempotency_key'=>phase5bToken()]),InvalidArgumentException::class);
    phase5bAs($fixture,ROLE_RENTAL_AGENT);$throws(fn()=>contractCancel(['contract_id'=>$draft,'reason'=>'Denied','idempotency_key'=>phase5bToken()]),AuthorizationException::class);
    phase5bAs($fixture,ROLE_AGENCY_MANAGER);$cancelDraft=['contract_id'=>$draft,'reason'=>'P5B_TEST draft cancellation','idempotency_key'=>phase5bToken()];$assert(contractCancel($cancelDraft)===$draft&&contractCancel($cancelDraft)===$draft,'Draft cancellation or replay failed');
    $cancelled=dbFetchOne('SELECT * FROM rental_contracts WHERE id=:id',['id'=>$draft]);$assert($cancelled['status']==='cancelled'&&$cancelled['cancelled_at']&&$cancelled['cancelled_by']==$fixture['users'][ROLE_AGENCY_MANAGER]&&$cancelled['cancellation_reason']==='P5B_TEST draft cancellation','Draft cancellation metadata is incomplete');

    $issuedReservation=phase5bCreateReservation($fixture,'CANCEL_ISSUED');$issued=contractCreateFromReservation(['reservation_id'=>$issuedReservation,'idempotency_key'=>phase5bToken()]);contractIssue(['contract_id'=>$issued,'idempotency_key'=>phase5bToken()]);$versionCount=(int)dbFetchOne('SELECT COUNT(*) n FROM contract_versions WHERE contract_id=:id',['id'=>$issued])['n'];contractCancel(['contract_id'=>$issued,'reason'=>'P5B_TEST issued cancellation','idempotency_key'=>phase5bToken()]);$assert((int)dbFetchOne('SELECT COUNT(*) n FROM contract_versions WHERE contract_id=:id',['id'=>$issued])['n']===$versionCount,'Cancellation deleted immutable versions');
    $throws(fn()=>contractCancel(['contract_id'=>$issued,'reason'=>'Again','idempotency_key'=>phase5bToken()]),DomainException::class);

    $terminalReservation=phase5bCreateReservation($fixture,'TERMINAL');$terminal=contractCreateFromReservation(['reservation_id'=>$terminalReservation,'idempotency_key'=>phase5bToken()]);contractIssue(['contract_id'=>$terminal,'idempotency_key'=>phase5bToken()]);
    dbExecute("UPDATE rental_contracts SET status='signed',signed_at=NOW(6) WHERE id=:id",['id'=>$terminal]);
    $throws(fn()=>contractCancel(['contract_id'=>$terminal,'reason'=>'Forbidden terminal cancellation','idempotency_key'=>phase5bToken()]),DomainException::class);

    $historyBefore=(int)dbFetchOne('SELECT COUNT(*) n FROM contract_status_history WHERE contract_id=:id',['id'=>$contract])['n'];
    $assert($historyBefore===2,'Issue history was not append-only with exactly two events');
    $source=(string)file_get_contents(__DIR__.'/../app/contract_service.php');$controller=(string)file_get_contents(__DIR__.'/../backoffice/contracts.php');
    $assert(!preg_match('/function\s+contract(StatusHistoryUpdate|StatusHistoryDelete|VersionUpdate|VersionDelete)/',$source),'Mutable history/version service exists');
    $assert(!str_contains($controller,"value=\"status\"")&&!str_contains($controller,'amendContract')&&!str_contains($controller,'UPDATE rental_contracts'),'Register still exposes arbitrary status mutation or amendments');
    $throws(fn()=>transitionReservationWorkspace($reservation,'active'),DomainException::class);
    $assert((int)(dbFetchOne('SELECT COUNT(id) n FROM audit_logs WHERE agency_id=:agency AND action IN(:created,:issued,:cancelled)',['agency'=>$fixture['agency_id'],'created'=>'contract.created','issued'=>'contract.issued','cancelled'=>'contract.cancelled'])['n']??0)>=5,'Contract lifecycle audit rows are missing');

    $base=['user_id'=>$fixture['users'][ROLE_AGENCY_MANAGER],'role'=>ROLE_AGENCY_MANAGER,'agency_id'=>$fixture['agency_id']];
    $r=phase5bCreateReservation($fixture,'CONC_CREATE');$pair=phase5bPair($runtime,$base+['operation'=>'create','args'=>['reservation_id'=>$r,'idempotency_key'=>phase5bToken()]],$base+['operation'=>'create','args'=>['reservation_id'=>$r,'idempotency_key'=>phase5bToken()]]);
    $assert(((int)$pair['a']['ok']+(int)$pair['b']['ok'])===1&&(int)dbFetchOne("SELECT COUNT(*) n FROM rental_contracts WHERE reservation_id=:id AND status IN('draft','issued','signed','active')",['id'=>$r])['n']===1,'Different-key concurrent creation did not serialize');
    $r=phase5bCreateReservation($fixture,'CONC_REPLAY');$token=phase5bToken();$args=['reservation_id'=>$r,'idempotency_key'=>$token];$pair=phase5bPair($runtime,$base+['operation'=>'create','args'=>$args],$base+['operation'=>'create','args'=>$args]);
    $assert($pair['a']['ok']&&$pair['b']['ok']&&$pair['a']['result']===$pair['b']['result']&&(int)dbFetchOne('SELECT COUNT(*) n FROM rental_contracts WHERE reservation_id=:id',['id'=>$r])['n']===1,'Identical concurrent creation replay failed');
    $r=phase5bCreateReservation($fixture,'CONC_ISSUE');$c=contractCreateFromReservation(['reservation_id'=>$r,'idempotency_key'=>phase5bToken()]);$pair=phase5bPair($runtime,$base+['operation'=>'issue','args'=>['contract_id'=>$c,'idempotency_key'=>phase5bToken()]],$base+['operation'=>'issue','args'=>['contract_id'=>$c,'idempotency_key'=>phase5bToken()]]);
    $assert(((int)$pair['a']['ok']+(int)$pair['b']['ok'])===1&&(int)dbFetchOne('SELECT COUNT(*) n FROM contract_versions WHERE contract_id=:id AND version_number=1',['id'=>$c])['n']===3&&dbFetchOne('SELECT status FROM rental_contracts WHERE id=:id',['id'=>$c])['status']==='issued','Concurrent issue created mixed or duplicate state');
    $r=phase5bCreateReservation($fixture,'CONC_RACE');$c=contractCreateFromReservation(['reservation_id'=>$r,'idempotency_key'=>phase5bToken()]);$pair=phase5bPair($runtime,$base+['operation'=>'issue','args'=>['contract_id'=>$c,'idempotency_key'=>phase5bToken()]],$base+['operation'=>'cancel','args'=>['contract_id'=>$c,'reason'=>'P5B_TEST race cancellation','idempotency_key'=>phase5bToken()]]);
    $race=dbFetchOne('SELECT status,current_version FROM rental_contracts WHERE id=:id',['id'=>$c]);$raceVersions=(int)dbFetchOne('SELECT COUNT(*) n FROM contract_versions WHERE contract_id=:id',['id'=>$c])['n'];
    $assert(((int)$pair['a']['ok']+(int)$pair['b']['ok'])>=1&&(($race['status']==='issued'&&$raceVersions===3)||($race['status']==='cancelled'&&in_array($raceVersions,[0,3],true))),'Issue-versus-cancellation left mixed state');
    $assert((int)dbFetchOne("SELECT COUNT(*) n FROM rental_operation_idempotency_keys WHERE origin_agency_id=:agency AND status='in_progress'",['agency'=>$fixture['agency_id']])['n']===0,'Unresolved idempotency residue remains');
}catch(Throwable$exception){$failures[]='Unexpected Phase 5B lifecycle failure: '.$exception->getMessage().' at '.basename($exception->getFile()).':'.$exception->getLine();}

$cleanup();$fixture=[];$other=[];
if($failures){foreach(array_unique($failures)as$failure)fwrite(STDERR,'FAIL: '.$failure.PHP_EOL);exit(1);}
echo "Phase 5B.1 contract lifecycle tests passed: RBAC, scope, create, issue, cancel, immutable snapshots, history, audit, idempotency, and true concurrency.\n";
