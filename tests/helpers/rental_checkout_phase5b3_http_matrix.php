<?php
$matrixFixture=[];$agentFixture=[];$matrixFiles=[];$matrixProcess=null;$matrixLogs=[];
try{
    $matrixFixture=phase5bFixture('P5B3HTTP_M'.strtoupper(bin2hex(random_bytes(4))));
    $agentFixture=phase5bFixture('P5B3HTTP_A'.strtoupper(bin2hex(random_bytes(4))));
    $matrixCtx=p5b3HttpEligible($matrixFixture,$matrixFiles);
    $agentCtx=p5b3HttpEligible($agentFixture,$matrixFiles);
    [$matrixManager,$matrixCsrf]=p5b3HttpSession($matrixFixture['users'][ROLE_AGENCY_MANAGER],ROLE_AGENCY_MANAGER,$matrixFixture['agency_id']);
    [$agentSession,$agentCsrf]=p5b3HttpSession($agentFixture['users'][ROLE_RENTAL_AGENT],ROLE_RENTAL_AGENT,$agentFixture['agency_id']);
    [$customerSession,$customerCsrf]=p5b3HttpSession($matrixFixture['users'][ROLE_ACCOUNTANT],ROLE_CUSTOMER,$matrixFixture['agency_id']);
    [$inactiveManager,$inactiveManagerCsrf]=p5b3HttpSession($matrixFixture['users'][ROLE_AGENCY_MANAGER],ROLE_AGENCY_MANAGER,$matrixFixture['agency_id']);
    [$inactiveAgent,$inactiveAgentCsrf]=p5b3HttpSession($matrixFixture['users'][ROLE_RENTAL_AGENT],ROLE_RENTAL_AGENT,$matrixFixture['agency_id']);
    [$noAgency,$noAgencyCsrf]=p5b3HttpSession($matrixFixture['users'][ROLE_RENTAL_AGENT],ROLE_RENTAL_AGENT,$matrixFixture['agency_id']);
    session_name('rental_agency_session');session_id($noAgency);session_start();$_SESSION['agency_ids']=[];session_write_close();

    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$err);
    if(!$socket)throw new RuntimeException($err);
    $name=(string)stream_socket_get_name($socket,false);fclose($socket);
    $port=(int)substr(strrchr($name,':'),1);$root=dirname(__DIR__,2);
    $matrixLogs=[tempnam($root.'/storage','p5b3-matrix-out-'),tempnam($root.'/storage','p5b3-matrix-err-')];
    $matrixProcess=proc_open(
        [PHP_BINARY,'-d','session.save_path='.$root.'/storage','-S','127.0.0.1:'.$port,'dev_router.php'],
        [['pipe','r'],['file',$matrixLogs[0],'a'],['file',$matrixLogs[1],'a']],$pipes,$root
    );
    if(!is_resource($matrixProcess))throw new RuntimeException('matrix server');
    fclose($pipes[0]);
    $request=static function(string $url,string $session,string $method='GET',array $data=[]):array{
        $header='Cookie: rental_agency_session='.$session."\r\n";
        $options=['method'=>$method,'header'=>$header,'timeout'=>3,'ignore_errors'=>true,'max_redirects'=>0];
        if($method==='POST'){$options['header'].="Content-Type: application/x-www-form-urlencoded\r\n";$options['content']=http_build_query($data);}
        $body=(string)file_get_contents($url,false,stream_context_create(['http'=>$options]));
        return['body'=>$body,'status'=>implode(' ',$http_response_header??[])];
    };
    $url='http://127.0.0.1:'.$port.'/backoffice/inspections.php';
    for($i=0;$i<30;$i++){try{$probe=$request($url,$matrixManager);if(($probe['body']??'')!=='')break;}catch(Throwable){}usleep(100000);}
    $post=['action'=>'rental_checkout','inspection_id'=>$matrixCtx['inspection'],'idempotency_key'=>phase5bToken(),'mileage'=>'125','fuel_level'=>'50.00','handed_over_at'=>(new DateTimeImmutable('now'))->format('Y-m-d H:i:s')];

    $invalidCsrf=$request($url,$matrixManager,'POST',$post+['_csrf'=>'invalid']);
    $assert(str_contains($invalidCsrf['status'],'419'),'Present invalid CSRF accepted');
    foreach([['array',['x']],['abc','abc'],['zero','0'],['negative','-1'],['decimal','1.5'],['whitespace',' '.$matrixCtx['inspection']]]as[$label,$id]){
        $response=$request($url,$matrixManager,'POST',array_replace($post,['_csrf'=>$matrixCsrf,'inspection_id'=>$id,'idempotency_key'=>phase5bToken()]));
        $assert(str_contains($response['status'],'302')&&dbFetchOne('SELECT status FROM reservations WHERE id=:id',['id'=>$matrixCtx['reservation']])['status']==='ready','Malformed inspection '.$label.' was not safely refused: '.$response['status']);
    }
    foreach(['en'=>'Complete checkout','fr'=>'Finaliser le départ','ar'=>'إتمام التسليم']as$lang=>$label){
        $page=$request($url.'?lang='.$lang,$matrixManager);
        $assert(str_contains($page['body'],$label),'Missing rendered '.$lang.' checkout action: '.$page['status']);
        $assert(str_contains($page['body'],'lang="'.$lang.'"'),'Rendered page language mismatch '.$lang);
        if($lang==='ar')$assert(str_contains($page['body'],'dir="rtl"')&&str_contains($page['body'],'<bdi class="bidi-value reference-value">'),'Arabic RTL/bidi isolation missing');
    }
    dbExecute("UPDATE inspection_photos SET archived_at=NOW(6),archived_by=:actor,archive_reason='ui guard' WHERE inspection_id=:inspection AND photo_slot='front'",['actor'=>$matrixFixture['users'][ROLE_AGENCY_MANAGER],'inspection'=>$matrixCtx['inspection']]);
    $incompletePage=$request($url.'?lang=en',$matrixManager);
    $assert(!str_contains($incompletePage['body'],'name="action" value="rental_checkout"'),'Checkout form rendered with five active slots/one archived required slot');
    dbExecute("UPDATE inspection_photos SET archived_at=NULL,archived_by=NULL,archive_reason=NULL WHERE inspection_id=:inspection AND photo_slot='front'",['inspection'=>$matrixCtx['inspection']]);
    $restoredPage=$request($url.'?lang=en',$matrixManager);
    $assert(str_contains($restoredPage['body'],'name="action" value="rental_checkout"'),'Checkout form did not return for the exact six-slot set');
    $uiSource=(string)file_get_contents($root.'/backoffice/inspections.php');
    foreach(inspectionPhotoSlots()as$slot)$assert(str_contains($uiSource,"SUM(ip.photo_slot='$slot')=1"),'UI query lacks exact '.$slot.' slot proof');
    $assert(substr_count($uiSource,'ip.agency_id=vi.agency_id')>=2,'UI photo subqueries are not agency scoped');

    $customer=$request($url,$customerSession,'POST',$post+['_csrf'=>$customerCsrf]);
    $noAgencyResult=$request($url,$noAgency,'POST',$post+['_csrf'=>$noAgencyCsrf]);
    $assert((str_contains($customer['status'],'403')||str_contains($customer['status'],'302'))&&str_contains($noAgencyResult['status'],'302'),'Customer or no-agency actor was not refused');
    $foreignResult=$request($url,$matrixManager,'POST',array_replace($post,['_csrf'=>$matrixCsrf,'inspection_id'=>$agentCtx['inspection'],'idempotency_key'=>phase5bToken()]));
    $assert(str_contains($foreignResult['status'],'302')&&dbFetchOne('SELECT status FROM reservations WHERE id=:id',['id'=>$agentCtx['reservation']])['status']==='ready','Cross-agency HTTP inspection was not refused');
    dbExecute("UPDATE users SET status='inactive' WHERE id IN(:manager,:agent)",['manager'=>$matrixFixture['users'][ROLE_AGENCY_MANAGER],'agent'=>$matrixFixture['users'][ROLE_RENTAL_AGENT]]);
    $inactiveM=$request($url,$inactiveManager,'POST',$post+['_csrf'=>$inactiveManagerCsrf]);
    $inactiveA=$request($url,$inactiveAgent,'POST',$post+['_csrf'=>$inactiveAgentCsrf]);
    $assert((str_contains($inactiveM['status'],'302')||str_contains($inactiveM['status'],'403'))&&(str_contains($inactiveA['status'],'302')||str_contains($inactiveA['status'],'403')),'Inactive actor was not refused');
    dbExecute("UPDATE users SET status='active' WHERE id IN(:manager,:agent)",['manager'=>$matrixFixture['users'][ROLE_AGENCY_MANAGER],'agent'=>$matrixFixture['users'][ROLE_RENTAL_AGENT]]);

    $agentPost=['action'=>'rental_checkout','inspection_id'=>$agentCtx['inspection'],'idempotency_key'=>phase5bToken(),'mileage'=>'125','fuel_level'=>'50.00','handed_over_at'=>(new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),'_csrf'=>$agentCsrf];
    $agentResult=$request($url,$agentSession,'POST',$agentPost);
    $assert(str_contains($agentResult['status'],'302')&&dbFetchOne('SELECT status FROM reservations WHERE id=:id',['id'=>$agentCtx['reservation']])['status']==='active','Rental-agent checkout failed');
    foreach(['invalid_csrf'=>$invalidCsrf,'customer'=>$customer,'no_agency'=>$noAgencyResult,'foreign'=>$foreignResult,'inactive_manager'=>$inactiveM,'inactive_agent'=>$inactiveA,'agent_success'=>$agentResult,'page'=>$page]as$case=>$response){
        $tokenPattern=$case==='page'?'':'|'.preg_quote($matrixManager,'/').'|'.preg_quote($matrixCsrf,'/');
        $leak=preg_match('/SQLSTATE|PDOException|SELECT\s+.+\s+FROM|INSERT\s+INTO|UPDATE\s+.+\s+SET|stack trace|[A-Z]:\\\\|inspection-photo-private|payload_hash|key_hash'.$tokenPattern.'/i',$response['body']??'',$match);
        $assert(!$leak,'HTTP matrix leaked sensitive data in '.$case.': '.($match[0]??''));
    }
}catch(Throwable$e){$fail[]='HTTP matrix failed: '.$e->getMessage();}
finally{
    if(is_resource($matrixProcess)){proc_terminate($matrixProcess);proc_close($matrixProcess);}
    foreach($matrixLogs as$file)if($file&&is_file($file))unlink($file);
    foreach($matrixFiles as$file){if(is_file($file))unlink($file);inspectionPhotoRemoveEmptyControlledDirectory(dirname($file));}
    foreach([$matrixFixture,$agentFixture]as$owned)if($owned)@rmdir(inspectionPhotoRoot().'/final/'.(int)$owned['agency_id']);
    try{if($agentFixture)phase5bCleanup($agentFixture);if($matrixFixture)phase5bCleanup($matrixFixture);}catch(Throwable$e){$fail[]='HTTP matrix cleanup '.$e->getMessage();}
}
