<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/application.php';
try { $count=withTransaction(fn()=>expirePendingReservations()); echo "Expired $count pending reservation(s).\n"; }
catch(Throwable $exception){error_log('[scheduler] pending expiration failed: '.$exception->getMessage());fwrite(STDERR,"Pending expiration failed.\n");exit(1);}

