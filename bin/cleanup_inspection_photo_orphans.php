<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/app/application.php';
$execute=false;$dryRun=false;$limit=100;
foreach(array_slice($argv,1) as $argument){
    if($argument==='--execute')$execute=true;
    elseif($argument==='--dry-run')$dryRun=true;
    elseif(str_starts_with($argument,'--limit=')){ $value=substr($argument,8);if(!preg_match('/^[1-9][0-9]*$/',$value)||(int)$value>500){fwrite(STDERR,"Usage: php bin/cleanup_inspection_photo_orphans.php [--dry-run|--execute] [--limit=1..500]\n");exit(2);}$limit=(int)$value; }
    else { fwrite(STDERR,"Usage: php bin/cleanup_inspection_photo_orphans.php [--dry-run|--execute] [--limit=1..500]\n");exit(2); }
}
if($execute&&$dryRun){fwrite(STDERR,"Usage: php bin/cleanup_inspection_photo_orphans.php [--dry-run|--execute] [--limit=1..500]\n");exit(2);}
try{$result=inspectionPhotoCleanupOrphans($execute,$limit);$paths=$execute?$result['removed']:$result['would_remove'];echo ($execute?'Removed ':'Would remove ').count($paths)." stale inspection-photo files.\n";foreach($paths as $relative)echo $relative."\n";exit(0);}catch(Throwable $exception){fwrite(STDERR,"Inspection-photo cleanup failed.\n");exit(1);}
