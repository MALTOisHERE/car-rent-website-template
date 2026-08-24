<?php
if(PHP_SAPI!=='cli'||$argc!==2)exit(2);
$command=json_decode((string)file_get_contents($argv[1]),true);if(!is_array($command))exit(3);
require_once dirname(__DIR__,2).'/app/application.php';
$_SESSION=['user_id'=>(int)$command['user_id'],'role'=>(string)$command['role'],'agency_ids'=>array_map('intval',$command['agency_ids']??[]),'_authenticated_at'=>time()];
$barrier=(string)$command['barrier'];$label=(string)$command['label'];file_put_contents($barrier.'/'.$label.'.ready','ready');$deadline=microtime(true)+20;while(!is_file($barrier.'/go')&&microtime(true)<$deadline)usleep(10000);
try{$result=$command['operation']==='resolve'?vehicleDamageResolve($command['payload']):vehicleDamageCreate($command['payload']);$output=['ok'=>true,'result'=>$result];}
catch(Throwable$exception){$output=['ok'=>false,'class'=>get_class($exception),'error'=>$exception->getMessage()];}
file_put_contents($barrier.'/'.$label.'.result',json_encode($output,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
