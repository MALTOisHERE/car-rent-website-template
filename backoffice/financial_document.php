<?php
require_once __DIR__ . '/../app/application.php';requireAuthentication('../account/login.php');
if(!can('finance.evidence.view')){protectedFileNotFound();exit;}
try{
    $kind=(string)($_GET['kind']??'');$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!$id||!in_array($kind,['payment_proof','expense_receipt'],true))throw new InvalidArgumentException('not found');
    [$ids,$ph]=financeScopedPlaceholders(currentAgencyIds());
    if($kind==='payment_proof')$document=dbFetchOne("SELECT id,proof_path storage_path,proof_mime_type mime_type FROM payments WHERE id=? AND agency_id IN ($ph) AND proof_path IS NOT NULL AND proof_mime_type IS NOT NULL",array_merge([(int)$id],$ids));
    else $document=dbFetchOne("SELECT id,receipt_path storage_path,receipt_mime_type mime_type FROM expenses WHERE id=? AND agency_id IN ($ph) AND archived_at IS NULL AND receipt_path IS NOT NULL AND receipt_mime_type IS NOT NULL",array_merge([(int)$id],$ids));
    if(!$document)throw new InvalidArgumentException('not found');deliverAuthorizedProtectedFile($document,$kind==='payment_proof'?'payment-proof':'expense-receipt');
}catch(InvalidArgumentException$e){protectedFileNotFound();}
