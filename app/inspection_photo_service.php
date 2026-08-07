<?php

/* Inspection evidence is deliberately kept in one service: controllers never receive a path. */
function inspectionPhotoSlots(): array { return ['front','rear','left','right','interior','dashboard']; }
function inspectionPhotoRoot(): string { return dirname(__DIR__) . '/storage/inspection-photo-private'; }
function inspectionPhotoNormalPath(string $path): string { return rtrim(str_replace('\\','/', $path), '/'); }
function inspectionPhotoInside(string $path, string $root): bool { $path=inspectionPhotoNormalPath($path); $root=inspectionPhotoNormalPath($root); return $path===$root || str_starts_with($path,$root.'/'); }
function inspectionPhotoEnsureDirectory(string $directory): void {
    $root=inspectionPhotoRoot();
    if (is_link($directory) || !inspectionPhotoInside($directory,$root)) throw new RuntimeException('Photo storage unavailable.');
    if (!is_dir($directory) && !mkdir($directory,0750,true) && !is_dir($directory)) throw new RuntimeException('Photo storage unavailable.');
    $resolved=realpath($directory); $resolvedRoot=realpath($root);
    if ($resolved===false || $resolvedRoot===false || is_link($directory) || !inspectionPhotoInside($resolved,$resolvedRoot)) throw new RuntimeException('Photo storage unavailable.');
}
function inspectionPhotoPath(string $relative): ?string {
    if ($relative==='' || str_contains($relative,"\0") || !preg_match('#^inspection-photo-private/(?:staging/[a-f0-9]{24}|final/[0-9]+/[0-9]+)/[a-f0-9]{48}\.(?:jpg|png|webp)$#',$relative)) return null;
    inspectionPhotoEnsureDirectory(inspectionPhotoRoot());
    $path=dirname(__DIR__).'/storage/'.$relative; $root=realpath(inspectionPhotoRoot()); $dir=realpath(dirname($path));
    if ($root===false || $dir===false || is_link($path) || !inspectionPhotoInside($dir,$root)) return null;
    return $path;
}
function inspectionPhotoTestHook(string $stage, array $context=[]): void {
    if (PHP_SAPI==='cli' && defined('INSPECTION_PHOTO_TEST_HOOK') && INSPECTION_PHOTO_TEST_HOOK===true && function_exists('inspectionPhotoCliTestHook')) inspectionPhotoCliTestHook($stage,$context);
}
function inspectionPhotoNormalizeUploads(array $uploads): array {
    if (array_keys($uploads)!==inspectionPhotoSlots()) throw new InvalidArgumentException(t('validation.inspection_photo_bundle'));
    foreach ($uploads as $file) if (!is_array($file) || array_keys($file)!==['name','type','tmp_name','error','size']) throw new InvalidArgumentException(t('validation.inspection_photo_bundle'));
    return $uploads;
}
function inspectionPhotoValidateUpload(array $file): array {
    $tmp=(string)$file['tmp_name']; $cli=PHP_SAPI==='cli' && defined('INSPECTION_PHOTO_TEST_HOOK') && INSPECTION_PHOTO_TEST_HOOK===true;
    if (($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || $tmp==='' || str_contains($tmp,"\0") || (!$cli && !is_uploaded_file($tmp)) || ($cli && !is_file($tmp)) || is_link($tmp)) throw new InvalidArgumentException(t('validation.inspection_photo_upload'));
    $actualSize=@filesize($tmp); $min=(int)appConfig('inspection_photo_min_bytes'); $max=(int)appConfig('upload_max_bytes');
    if ($actualSize===false || $actualSize<$min || $actualSize>$max || (int)$file['size']!==$actualSize) throw new InvalidArgumentException(t('validation.inspection_photo_upload'));
    $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp']; $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp); $image=@getimagesize($tmp);
    $bytes=@file_get_contents($tmp); $expected=['image/jpeg'=>IMAGETYPE_JPEG,'image/png'=>IMAGETYPE_PNG,'image/webp'=>IMAGETYPE_WEBP];
    if (!isset($allowed[$mime]) || $image===false || !isset($expected[$mime]) || ($image[2]??0)!==$expected[$mime] || str_contains((string)$bytes,'<?') || (int)$image[0]<1 || (int)$image[1]<1 || (int)$image[0]>(int)appConfig('inspection_photo_max_dimension') || (int)$image[1]>(int)appConfig('inspection_photo_max_dimension')) throw new InvalidArgumentException(t('validation.inspection_photo_image'));
    return ['mime_type'=>$mime,'extension'=>$allowed[$mime],'file_size'=>$actualSize,'sha256'=>hash_file('sha256',$tmp),'original_name'=>basename(str_replace("\0",'',(string)$file['name'])),'tmp_name'=>$tmp];
}
function inspectionPhotoControlledDirectory(string $directory): bool {
    $root=realpath(inspectionPhotoRoot());$directoryReal=realpath($directory);
    if($root===false||$directoryReal===false||is_link($directory)||!inspectionPhotoInside($directoryReal,$root))return false;
    $relative='inspection-photo-private/'.ltrim(str_replace('\\','/',substr($directoryReal,strlen($root))),'/');
    return (bool)preg_match('#^inspection-photo-private/staging/[a-f0-9]{24}$#',$relative)||(bool)preg_match('#^inspection-photo-private/final/[0-9]+/[0-9]+$#',$relative);
}
function inspectionPhotoRemoveEmptyControlledDirectory(string $directory): void {
    if(!inspectionPhotoControlledDirectory($directory)||!is_dir($directory)||is_link($directory))return;
    $items=@scandir($directory);if($items===false||count($items)!==2)return;
    @rmdir($directory);
}
function inspectionPhotoStageBundle(array $uploads): array {
    $uploads=inspectionPhotoNormalizeUploads($uploads); inspectionPhotoEnsureDirectory(inspectionPhotoRoot()); $token=bin2hex(random_bytes(12)); $directory=inspectionPhotoRoot().'/staging/'.$token; inspectionPhotoEnsureDirectory($directory); $staged=[];
    try { foreach ($uploads as $slot=>$file) { $meta=inspectionPhotoValidateUpload($file); $name=bin2hex(random_bytes(24)).'.'.$meta['extension']; $absolute=$directory.'/'.$name; $cli=PHP_SAPI==='cli' && defined('INSPECTION_PHOTO_TEST_HOOK') && INSPECTION_PHOTO_TEST_HOOK===true; if (!($cli ? copy($meta['tmp_name'],$absolute) : move_uploaded_file($meta['tmp_name'],$absolute))) throw new RuntimeException('Photo staging failed.'); chmod($absolute,0640); $meta+=['slot'=>$slot,'staged_relative'=>'inspection-photo-private/staging/'.$token.'/'.$name,'staged_absolute'=>$absolute]; $staged[$slot]=$meta; inspectionPhotoTestHook('after_staged_file',['slot'=>$slot]); } inspectionPhotoTestHook('after_staging',$staged); return $staged; }
    catch (Throwable $e) { foreach ($staged as $meta) if (is_file($meta['staged_absolute'])) @unlink($meta['staged_absolute']); inspectionPhotoRemoveEmptyControlledDirectory($directory); throw $e; }
}
function inspectionPhotoOwnedStagingBundle(array $staged,bool $requireFiles=true): ?array {
    $paths=[];$directory=null;
    foreach($staged as $meta){
        if(!is_array($meta)||!isset($meta['staged_relative'],$meta['staged_absolute'])||!is_string($meta['staged_relative'])||!is_string($meta['staged_absolute'])||!preg_match('#^inspection-photo-private/staging/[a-f0-9]{24}/[a-f0-9]{48}\.(?:jpg|png|webp)$#',$meta['staged_relative']))return null;
        $path=inspectionPhotoPath($meta['staged_relative']);if($path===null||is_link($path)||($requireFiles&&!is_file($path))||inspectionPhotoNormalPath($path)!==inspectionPhotoNormalPath($meta['staged_absolute']))return null;
        $candidate=dirname($path);if($directory===null)$directory=$candidate;elseif(inspectionPhotoNormalPath($directory)!==inspectionPhotoNormalPath($candidate))return null;$paths[]=$path;
    }
    return $directory===null?null:['directory'=>$directory,'paths'=>array_values(array_unique($paths))];
}
function inspectionPhotoCompensateStagedBundle(array $staged,array $promoted=[]): void {
    foreach($promoted as $path)if(is_string($path)&&is_file($path)&&!is_link($path)){@unlink($path);inspectionPhotoRemoveEmptyControlledDirectory(dirname($path));}
    $owned=inspectionPhotoOwnedStagingBundle($staged,false);if($owned===null)return;
    foreach($owned['paths']as$path)if(is_file($path)&&!is_link($path))@unlink($path);
    inspectionPhotoRemoveEmptyControlledDirectory($owned['directory']);
}
function inspectionPhotoNormalizeStaged(array $staged): array {
    if(array_keys($staged)!==inspectionPhotoSlots()||inspectionPhotoOwnedStagingBundle($staged)===null)throw new InvalidArgumentException(t('validation.inspection_photo_bundle'));
    foreach($staged as$slot=>$meta)if(($meta['slot']??null)!==$slot||!isset($meta['mime_type'],$meta['file_size'],$meta['sha256'])||!is_string($meta['mime_type'])||!is_int($meta['file_size'])||!is_string($meta['sha256']))throw new InvalidArgumentException(t('validation.inspection_photo_bundle'));
    return$staged;
}
function inspectionPhotoScopedInspection(int $inspectionId, bool $lock=false): ?array { $ids=contractScopedAgencyIds(); if (!$ids) return null; $marks=implode(',',array_fill(0,count($ids),'?')); return dbFetchOne("SELECT * FROM vehicle_inspections WHERE id=? AND agency_id IN ($marks)".($lock?' FOR UPDATE':''),array_merge([$inspectionId],$ids)); }
function inspectionPhotoCompleteIdempotency(int $id,int $inspectionId): void { $changed=dbExecute("UPDATE rental_operation_idempotency_keys SET status='completed',completed_at=NOW(6),result_entity_type='inspection_photo_bundle',result_entity_id=:result WHERE id=:id AND status='in_progress'",['result'=>$inspectionId,'id'=>$id]); if($changed->rowCount()!==1) throw new DomainException(t('validation.contract_stale')); }
function inspectionPhotoPayload(int $inspectionId,int $agency,array $staged): array { return ['inspection_id'=>$inspectionId,'agency_id'=>$agency,'slots'=>array_map(static fn($x)=>['mime'=>$x['mime_type'],'size'=>(int)$x['file_size'],'sha256'=>$x['sha256']],$staged)]; }
function inspectionPhotoPersistBundle(int $inspectionId,array $staged,string $key): int {
    /* Ownership transfers at entry: every unsuccessful return compensates only this proven staging bundle. */
    $promoted=[];
    try { enforcePermission('inspection_photos.upload');$staged=inspectionPhotoNormalizeStaged($staged);$visible=inspectionPhotoScopedInspection($inspectionId);if(!$visible)throw new InvalidArgumentException(t('validation.inspection_photo_not_found'));$agency=(int)$visible['agency_id'];$result=contractWithRetry(function() use ($inspectionId,$staged,$key,$agency,&$promoted) { $inspection=inspectionPhotoScopedInspection($inspectionId,true); if (!$inspection || $inspection['status']!=='draft') throw new DomainException(t('validation.inspection_photo_state')); $actor=contractAcknowledgementActor($agency); $idem=contractAcquireIdempotency($agency,'inspection_photo_bundle',$key,inspectionPhotoPayload($inspectionId,$agency,$staged)); if ($idem['completed']) throw new ContractIdempotentReplay($idem['result_id']); $existing=dbFetchAll('SELECT id FROM inspection_photos WHERE inspection_id=:inspection AND agency_id=:agency AND archived_at IS NULL ORDER BY photo_slot,id FOR UPDATE',['inspection'=>$inspectionId,'agency'=>$agency]); if ($existing) throw new DomainException(t('validation.inspection_photo_exists')); $finalDir=inspectionPhotoRoot().'/final/'.$agency.'/'.$inspectionId; inspectionPhotoEnsureDirectory($finalDir); $ids=[];
        foreach ($staged as $slot=>$meta) { $name=bin2hex(random_bytes(24)).'.'.$meta['extension']; $final=$finalDir.'/'.$name; $relative='inspection-photo-private/final/'.$agency.'/'.$inspectionId.'/'.$name; dbExecute('INSERT INTO inspection_photos(agency_id,inspection_id,photo_type,photo_slot,storage_path,original_name,mime_type,file_size,sha256,protected_file,captured_at,created_by) VALUES(:agency,:inspection,:type,:slot,:path,:original,:mime,:size,:sha,1,NOW(6),:actor)',['agency'=>$agency,'inspection'=>$inspectionId,'type'=>$slot,'slot'=>$slot,'path'=>$relative,'original'=>$meta['original_name'],'mime'=>$meta['mime_type'],'size'=>$meta['file_size'],'sha'=>$meta['sha256'],'actor'=>$actor['id']]); inspectionPhotoTestHook('after_metadata_insert',['slot'=>$slot]); if (!rename($meta['staged_absolute'],$final)) throw new RuntimeException('Photo promotion failed.'); $promoted[]=$final; if ((int)filesize($final)!==(int)$meta['file_size'] || !hash_equals($meta['sha256'],hash_file('sha256',$final))) throw new RuntimeException('Photo verification failed.'); $ids[]=(int)db()->lastInsertId(); inspectionPhotoTestHook('after_promotion',['slot'=>$slot]); }
        inspectionPhotoTestHook('before_commit',['inspection_id'=>$inspectionId]); auditLog('inspection_photo_bundle_committed','inspection',$inspectionId,null,['photo_ids'=>$ids,'slots'=>array_keys($staged)],$agency); inspectionPhotoCompleteIdempotency((int)$idem['id'],$inspectionId); return $inspectionId; });inspectionPhotoCompensateStagedBundle($staged);return $result;
    } catch (ContractIdempotentReplay $replay) { inspectionPhotoCompensateStagedBundle($staged,$promoted);return $replay->result(); }
    catch (Throwable $e) { inspectionPhotoCompensateStagedBundle($staged,$promoted);throw $e; }
}
function inspectionPhotosForInspection(int $inspectionId): array { enforcePermission('inspection_photos.view'); $inspection=inspectionPhotoScopedInspection($inspectionId); if (!$inspection) throw new InvalidArgumentException(t('validation.inspection_photo_not_found')); return dbFetchAll('SELECT * FROM inspection_photos WHERE inspection_id=:inspection AND agency_id=:agency ORDER BY photo_slot,id',['inspection'=>$inspectionId,'agency'=>$inspection['agency_id']]); }
function inspectionPhotoAuthorizeRead(int $photoId): array { enforcePermission('inspection_photos.view'); $row=dbFetchOne('SELECT ip.* FROM inspection_photos ip JOIN vehicle_inspections vi ON vi.id=ip.inspection_id AND vi.agency_id=ip.agency_id WHERE ip.id=:id AND ip.archived_at IS NULL',['id'=>$photoId]); if (!$row || !in_array((int)$row['agency_id'],contractScopedAgencyIds(),true)) throw new InvalidArgumentException(t('validation.inspection_photo_not_found')); $path=inspectionPhotoPath((string)$row['storage_path']); if (!$path || !is_file($path) || is_link($path) || (int)filesize($path)!==(int)$row['file_size'] || !hash_equals((string)$row['sha256'],hash_file('sha256',$path))) throw new InvalidArgumentException(t('validation.inspection_photo_not_found')); $mime=(new finfo(FILEINFO_MIME_TYPE))->file($path); if (!in_array($mime,['image/jpeg','image/png','image/webp'],true) || !hash_equals((string)$row['mime_type'],$mime)) throw new InvalidArgumentException(t('validation.inspection_photo_not_found')); return ['row'=>$row,'path'=>$path,'mime'=>$mime]; }
/* Internal lifecycle verifier: callers already hold their authoritative rows and
 * never receive a path.  It deliberately validates every row rather than COUNT(*). */
function inspectionPhotoVerifyActiveBundle(int $inspectionId,int $agencyId,string $errorKey='validation.checkout_photo_bundle'): array {
    $rows=dbFetchAll('SELECT * FROM inspection_photos WHERE inspection_id=:inspection AND agency_id=:agency AND archived_at IS NULL ORDER BY photo_slot,id FOR UPDATE',['inspection'=>$inspectionId,'agency'=>$agencyId]);
    $slots=array_column($rows,'photo_slot');$required=inspectionPhotoSlots();sort($slots,SORT_STRING);sort($required,SORT_STRING);if(count($rows)!==count($required)||$slots!==$required)throw new DomainException(t($errorKey));
    foreach($rows as$row){$path=inspectionPhotoPath((string)$row['storage_path']);if(!$path||!is_file($path)||is_link($path)||(int)filesize($path)!==(int)$row['file_size']||!hash_equals((string)$row['sha256'],hash_file('sha256',$path)))throw new DomainException(t($errorKey));$mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)||!hash_equals((string)$row['mime_type'],$mime))throw new DomainException(t($errorKey));}
    return $rows;
}
function inspectionPhotoArchive(int $photoId,string $reason): void { enforcePermission('inspection_photos.archive'); $reason=trim($reason); if ($reason==='' || mb_strlen($reason)>255) throw new InvalidArgumentException(t('validation.inspection_photo_archive_reason')); withTransaction(function() use ($photoId,$reason) { $row=dbFetchOne('SELECT ip.*,vi.status FROM inspection_photos ip JOIN vehicle_inspections vi ON vi.id=ip.inspection_id AND vi.agency_id=ip.agency_id WHERE ip.id=:id FOR UPDATE',['id'=>$photoId]); if (!$row || !in_array((int)$row['agency_id'],contractScopedAgencyIds(),true)) throw new InvalidArgumentException(t('validation.inspection_photo_not_found')); if ($row['status']!=='draft' || $row['archived_at']) throw new DomainException(t('validation.inspection_photo_state')); dbExecute('UPDATE inspection_photos SET archived_at=NOW(6),archived_by=:actor,archive_reason=:reason WHERE id=:id AND archived_at IS NULL',['actor'=>currentUserId(),'reason'=>$reason,'id'=>$photoId]); auditLog('inspection_photo_archived','inspection_photo',$photoId,null,['inspection_id'=>$row['inspection_id'],'reason'=>$reason],$row['agency_id']); }); }
function inspectionPhotoCleanupTestScope(?string $scope): ?string {
    if($scope===null)return null;
    if(PHP_SAPI!=='cli'||!defined('INSPECTION_PHOTO_CLEANUP_TEST_HOOK')||INSPECTION_PHOTO_CLEANUP_TEST_HOOK!==true)throw new LogicException('Cleanup scope is unavailable.');
    if(!preg_match('#^inspection-photo-private/(?:staging/[a-f0-9]{24}|final/[0-9]+/[0-9]+)$#',$scope))throw new InvalidArgumentException('Invalid cleanup test scope.');
    return $scope;
}
function inspectionPhotoPruneEmptyControlledDirectories(?string $scope=null): int {
    $root=realpath(inspectionPhotoRoot());if($root===false)return 0;$removed=0;$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($iterator as $entry){if(!$entry->isDir()||$entry->isLink())continue;$directory=$entry->getPathname();$real=realpath($directory);if($real===false||!inspectionPhotoInside($real,$root))continue;$relative='inspection-photo-private/'.ltrim(str_replace('\\','/',substr($real,strlen($root))),'/');if($scope!==null&&!($relative===$scope||str_starts_with($relative,$scope.'/')))continue;if(!inspectionPhotoControlledDirectory($directory))continue;$before=is_dir($directory);inspectionPhotoRemoveEmptyControlledDirectory($directory);if($before&&!is_dir($directory))$removed++;}
    return $removed;
}
function inspectionPhotoCleanupOrphans(bool $execute=false,int $limit=100,?string $scope=null): array {
    if($limit<1||$limit>500)throw new InvalidArgumentException('Cleanup limit must be between 1 and 500.');$scope=inspectionPhotoCleanupTestScope($scope);inspectionPhotoEnsureDirectory(inspectionPhotoRoot());$root=realpath(inspectionPhotoRoot());$grace=(int)appConfig('inspection_photo_orphan_grace_seconds');$referenced=array_flip(array_column(dbFetchAll("SELECT storage_path FROM inspection_photos WHERE storage_path LIKE 'inspection-photo-private/%'"),'storage_path'));$eligible=[];$pattern='#^inspection-photo-private/(?:staging/[a-f0-9]{24}|final/[0-9]+/[0-9]+)/[a-f0-9]{48}\.(?:jpg|png|webp)$#';$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
    foreach($iterator as $file){if($file->isLink()||!$file->isFile())continue;$absolute=$file->getPathname();$resolved=realpath($absolute);if($resolved===false||!inspectionPhotoInside($resolved,$root))continue;$relative='inspection-photo-private/'.ltrim(str_replace('\\','/',substr($resolved,strlen($root))),'/');if(($scope!==null&&!str_starts_with($relative,$scope.'/'))||!preg_match($pattern,$relative)||isset($referenced[$relative])||$file->getMTime()>time()-$grace)continue;$eligible[]=['relative'=>$relative,'path'=>$resolved,'mtime'=>$file->getMTime()];}
    usort($eligible,static fn(array $a,array $b):int=>$a['mtime']<=>$b['mtime']?:strcmp($a['relative'],$b['relative']));$candidates=array_slice($eligible,0,$limit);$removed=[];if($execute)foreach($candidates as$candidate){if(is_file($candidate['path'])&&!is_link($candidate['path'])&&@unlink($candidate['path'])){$removed[]=$candidate['relative'];inspectionPhotoRemoveEmptyControlledDirectory(dirname($candidate['path']));}}
    $directoriesRemoved=$execute?inspectionPhotoPruneEmptyControlledDirectories($scope):0;return['would_remove'=>array_column($candidates,'relative'),'removed'=>$removed,'directories_removed'=>$directoriesRemoved];
}
