<?php
if(PHP_SAPI!=='cli')exit(1);
define('VEHICLE_DAMAGE_CONTROLLER_TEST_HOOK',true);
function vehicleDamageControllerTestHook(): void { throw new RuntimeException('P5C1_INTERNAL SQLSTATE PDOException C:\\private\\inspection-photo-private payload_hash key_hash'); }
$command=json_decode((string)file_get_contents($argv[1]??''),true);session_name('rental_agency_session');session_id((string)$command['session']);session_start();$_SERVER['REQUEST_METHOD']='POST';
$_POST=['action'=>'vehicle_damage_create','inspection_id'=>(string)$command['inspection_id'],'zone'=>'door','damage_type'=>'dent','description'=>'safe description','severity'=>'minor','idempotency_key'=>(string)$command['idempotency_key'],'_csrf'=>(string)$command['csrf']];
require dirname(__DIR__,2).'/backoffice/incidents.php';

