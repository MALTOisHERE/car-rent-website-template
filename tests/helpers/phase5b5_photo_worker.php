<?php
if(PHP_SAPI!=='cli'||$argc!==2)exit(2);
define('INSPECTION_PHOTO_TEST_HOOK',true);function inspectionPhotoCliTestHook(string $stage,array $context=[]):void{}
$command=json_decode((string)file_get_contents($argv[1]),true);if(!is_array($command))exit(2);require_once dirname(__DIR__,2).'/app/application.php';
$_SESSION=['user_id'=>(int)$command['user_id'],'role'=>ROLE_AGENCY_MANAGER,'agency_ids'=>[(int)$command['agency_id']],'_authenticated_at'=>time()];file_put_contents($command['barrier'].'/'.$command['label'].'.ready','1');$deadline=microtime(true)+20;while(!is_file($command['barrier'].'/go')&&microtime(true)<$deadline)usleep(10000);
try{$result=inspectionPhotoPersistBundle((int)$command['inspection_id'],inspectionPhotoStageBundle($command['uploads']),(string)$command['key']);file_put_contents($command['barrier'].'/'.$command['label'].'.result',json_encode(['ok'=>true,'result'=>$result]));}catch(Throwable $e){file_put_contents($command['barrier'].'/'.$command['label'].'.result',json_encode(['ok'=>false,'class'=>get_class($e),'message'=>$e->getMessage()]));}
