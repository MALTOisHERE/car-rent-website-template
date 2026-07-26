<?php
if(PHP_SAPI!=='cli')exit(1);
$command=json_decode((string)file_get_contents($argv[1]??''),true);$result=$command['barrier'].'/'.$command['label'].'.result';
try{
    require_once dirname(__DIR__,2).'/app/application.php';
    $_SESSION=['user_id'=>(int)$command['user_id'],'role'=>$command['role'],'agency_ids'=>[(int)$command['agency_id']], '_authenticated_at'=>time()];
    file_put_contents($command['barrier'].'/'.$command['label'].'.ready','ready');
    $deadline=microtime(true)+20;while(!is_file($command['barrier'].'/go')&&microtime(true)<$deadline)usleep(5000);
    $args=$command['args'];$value=match($command['operation']){'create'=>contractCreateFromReservation($args),'issue'=>contractIssue($args),'cancel'=>contractCancel($args),default=>throw new InvalidArgumentException('operation')};
    file_put_contents($result,json_encode(['ok'=>true,'result'=>$value]));
}catch(Throwable$exception){file_put_contents($result,json_encode(['ok'=>false,'class'=>get_class($exception),'error'=>$exception->getMessage()]));}
