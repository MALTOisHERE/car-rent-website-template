<?php

function p5c1Eligible(array $fixture,string $suffix,array &$temporaryFiles): array
{
    $context=p5b6PrepareCheckout($fixture,$suffix,$temporaryFiles);p5b6Checkout($context);$context=p5b6PrepareReturn($fixture,$context,$temporaryFiles);p5b6Checkin($context,'damaged');
    return$context;
}

function p5c1Command(array $context,?string $key=null): array
{
    return['inspection_id'=>$context['return_inspection'],'zone'=>'right rear door','damage_type'=>'dent','description'=>'Dent and paint damage recorded after return.','severity'=>'moderate','idempotency_key'=>$key??phase5bToken()];
}

function p5c1FinanceCounters(array $fixture,array $context): array
{
    $agency=(int)$fixture['agency_id'];$reservation=(int)$context['reservation'];$contract=(int)$context['contract'];$customer=(int)$context['customer'];$result=[];
    $queries=[
        'payments'=>['SELECT COUNT(*) n FROM payments WHERE agency_id=:agency AND (reservation_id=:reservation OR contract_id=:contract)',['agency'=>$agency,'reservation'=>$reservation,'contract'=>$contract]],
        'invoices'=>['SELECT COUNT(*) n FROM invoices WHERE agency_id=:agency AND (reservation_id=:reservation OR contract_id=:contract OR customer_id=:customer)',['agency'=>$agency,'reservation'=>$reservation,'contract'=>$contract,'customer'=>$customer]],
        'invoice_items'=>['SELECT COUNT(*) n FROM invoice_items ii JOIN invoices i ON i.id=ii.invoice_id WHERE i.agency_id=:agency AND (i.reservation_id=:reservation OR i.contract_id=:contract OR i.customer_id=:customer)',['agency'=>$agency,'reservation'=>$reservation,'contract'=>$contract,'customer'=>$customer]],
        'deposits'=>['SELECT COUNT(*) n FROM deposits WHERE agency_id=:agency AND (reservation_id=:reservation OR contract_id=:contract)',['agency'=>$agency,'reservation'=>$reservation,'contract'=>$contract]],
        'expenses'=>['SELECT COUNT(*) n FROM expenses WHERE agency_id=:agency AND vehicle_id=:vehicle',['agency'=>$agency,'vehicle'=>$context['vehicle']]],
        'payment_adjustments'=>['SELECT COUNT(*) n FROM payment_adjustments pa JOIN payments p ON p.id=pa.payment_id AND p.agency_id=pa.agency_id WHERE pa.agency_id=:agency AND (p.reservation_id=:reservation OR p.contract_id=:contract)',['agency'=>$agency,'reservation'=>$reservation,'contract'=>$contract]],
        'deposit_events'=>['SELECT COUNT(*) n FROM deposit_events de JOIN deposits d ON d.id=de.deposit_id AND d.agency_id=de.agency_id WHERE de.agency_id=:agency AND (d.reservation_id=:reservation OR d.contract_id=:contract)',['agency'=>$agency,'reservation'=>$reservation,'contract'=>$contract]],
        'cash_movements'=>['SELECT COUNT(*) n FROM cash_movements WHERE agency_id=:agency',['agency'=>$agency]],
        'finance_idempotency_keys'=>['SELECT COUNT(*) n FROM finance_idempotency_keys WHERE agency_id=:agency',['agency'=>$agency]],
        'financial_number_allocations'=>['SELECT COUNT(*) n FROM financial_number_allocations WHERE agency_id=:agency',['agency'=>$agency]],
        'cash_registers'=>['SELECT COUNT(*) n FROM cash_registers WHERE agency_id=:agency',['agency'=>$agency]],
    ];
    foreach($queries as$name=>[$sql,$params])$result[$name]=(int)dbFetchOne($sql,$params)['n'];
    return$result;
}

function p5c1LaterPhaseCounters(array $context): array
{
    $result=[];foreach(['accidents','fines','maintenance_records']as$table)$result[$table]=(int)dbFetchOne("SELECT COUNT(*) n FROM $table WHERE vehicle_id=:vehicle",['vehicle'=>$context['vehicle']])['n'];return$result;
}

function p5c1PhotoEvidence(int $inspectionId): array
{
    return dbFetchAll('SELECT id,photo_slot,storage_path,mime_type,file_size,sha256,archived_at FROM inspection_photos WHERE inspection_id=:inspection ORDER BY id',['inspection'=>$inspectionId]);
}

function p5c1Cleanup(array $fixtures,array $temporaryFiles=[]): void
{
    if(db()->inTransaction())db()->rollBack();
    foreach($fixtures as$fixture){
        if(empty($fixture['agency_id']))continue;$agency=(int)$fixture['agency_id'];
        dbExecute('DELETE vd FROM vehicle_damages vd JOIN vehicle_inspections vi ON vi.id=vd.inspection_id WHERE vi.agency_id=:agency',['agency'=>$agency]);
        p5b6RemoveFixtureFiles($fixture);phase5bCleanup($fixture);
    }
    foreach($temporaryFiles as$file)if(is_string($file)&&is_file($file))@unlink($file);
}

function p5c1Pair(array $commands): array
{
    $root=dirname(__DIR__,2);$barrier=$root.'/storage/p5c1-worker-'.bin2hex(random_bytes(6));mkdir($barrier,0750,true);$processes=[];$results=[];
    foreach($commands as$label=>$command){$command['barrier']=$barrier;$command['label']=$label;$input=$barrier.'/'.$label.'.json';file_put_contents($input,json_encode($command,JSON_UNESCAPED_SLASHES));$processes[$label]=proc_open([PHP_BINARY,'-d','session.save_path='.$root.'/storage',__DIR__.'/vehicle_damage_phase5c1_worker.php',$input],[['pipe','r'],['file',$barrier.'/'.$label.'.out','a'],['file',$barrier.'/'.$label.'.err','a']],$pipes,$root);if(is_resource($processes[$label]))fclose($pipes[0]);}
    $deadline=microtime(true)+20;while(array_filter(array_keys($commands),static fn($label)=>!is_file($barrier.'/'.$label.'.ready'))&&microtime(true)<$deadline)usleep(10000);file_put_contents($barrier.'/go','go');
    foreach($processes as$label=>$process){if(is_resource($process))proc_close($process);$path=$barrier.'/'.$label.'.result';$results[$label]=is_file($path)?json_decode((string)file_get_contents($path),true):['ok'=>false,'error'=>'missing result'];}
    foreach(glob($barrier.'/*')?:[]as$file)if(is_file($file))unlink($file);rmdir($barrier);return$results;
}
