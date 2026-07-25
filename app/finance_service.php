<?php

/** Phase 5A authoritative finance write boundary. */

final class FinanceIdempotentReplay extends RuntimeException
{
    private $financeResult;

    public function __construct($result)
    {
        parent::__construct('Completed finance command replay.');
        $this->financeResult = $result;
    }

    public function result()
    {
        return $this->financeResult;
    }
}

function financePaymentMethods(): array
{
    return ['cash','card','bank_transfer','cheque','online','other'];
}

function financeAdjustmentTypes(): array
{
    return ['full_reversal','partial_refund','full_refund','excess_reallocation'];
}

function financeRequireCutover(): void
{
    if (!tableExists('payment_adjustments') || !tableExists('deposit_events') || !tableExists('cash_movements')) {
        throw new DomainException(t('validation.finance_read_only'));
    }
    $migration = dbFetchOne("SELECT version FROM schema_migrations WHERE version='006_finance_core'");
    if (!$migration) throw new DomainException(t('validation.finance_read_only'));
}

function financeAssertPermission(string $permission): void
{
    if (!can($permission)) throw new AuthorizationException(t('validation.not_authorized'));
}

function financeAssertAgency(int $agencyId): void
{
    if (currentUserRole() !== ROLE_OWNER && !in_array($agencyId, currentAgencyIds(), true)) {
        throw new AuthorizationException(t('validation.not_authorized'));
    }
}

function financeReason($value, string $message = 'validation.finance_reason'): string
{
    $reason = trim((string)$value);
    if (mb_strlen($reason) < 1 || mb_strlen($reason) > 255) throw new InvalidArgumentException(t($message));
    return $reason;
}

function financeMoneyCents($value, bool $allowZero = false): int
{
    $money = positiveMoney($value);
    $cents = $money === null ? null : moneyToCents($money);
    if ($cents === null || ($allowZero ? $cents < 0 : $cents <= 0)) throw new InvalidArgumentException(t('validation.finance_amount'));
    return $cents;
}

function financeIdempotencyToken(): string
{
    return bin2hex(random_bytes(32));
}

function financeIdempotencyField(string $operation): string
{
    return '<input type="hidden" name="idempotency_key" value="' . e(financeIdempotencyToken()) . '"><input type="hidden" name="operation_type" value="' . e($operation) . '">';
}

function financeIsRetryable(Throwable $exception): bool
{
    if (!$exception instanceof PDOException) return false;
    $state = (string)$exception->getCode();
    $driver = (int)($exception->errorInfo[1] ?? 0);
    return $state === '40001' || in_array($driver, [1205,1213], true);
}

function financeWithRetry(callable $callback)
{
    for ($attempt=1; $attempt<=3; $attempt++) {
        try {
            return withTransaction($callback);
        } catch (Throwable $exception) {
            if (!financeIsRetryable($exception) || $attempt === 3) throw $exception;
            usleep(random_int(20000*$attempt, 80000*$attempt));
        }
    }
    throw new RuntimeException('Finance transaction retry exhausted.');
}

function financeAcquireIdempotency(int $agencyId, string $operation, $rawKey): array
{
    $rawKey = strtolower(trim((string)$rawKey));
    if (!preg_match('/^[a-f0-9]{64}$/', $rawKey)) throw new InvalidArgumentException(t('validation.finance_idempotency'));
    $hash = hash('sha256', $rawKey);
    dbExecute(
        "INSERT INTO finance_idempotency_keys(agency_id,operation_type,key_hash,status,created_by)
         VALUES(:agency,:operation,:hash,'in_progress',:user)
         ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)",
        ['agency'=>$agencyId,'operation'=>$operation,'hash'=>$hash,'user'=>currentUserId()]
    );
    $id = (int)db()->lastInsertId();
    $row = dbFetchOne('SELECT * FROM finance_idempotency_keys WHERE id=:id FOR UPDATE',['id'=>$id]);
    if (!$row || $row['operation_type'] !== $operation) throw new DomainException(t('validation.finance_idempotency'));
    return ['id'=>$id,'completed'=>$row['status']==='completed','result_id'=>$row['result_entity_id']===null?null:(int)$row['result_entity_id']];
}

function financeCompleteIdempotency(int $id, string $entityType, int $entityId): void
{
    $statement = dbExecute(
        "UPDATE finance_idempotency_keys SET status='completed',result_entity_type=:type,result_entity_id=:entity,completed_at=NOW(6)
         WHERE id=:id AND status='in_progress'",
        ['type'=>$entityType,'entity'=>$entityId,'id'=>$id]
    );
    if ($statement->rowCount() !== 1) throw new DomainException(t('validation.finance_stale'));
}

function financeReplayIfCompleted(array $idempotency): void
{
    if ($idempotency['completed']) {
        throw new FinanceIdempotentReplay($idempotency['result_id']);
    }
}

function financeDerivedIdempotencyKey($rawKey, string $operation): string
{
    $rawKey = strtolower(trim((string)$rawKey));
    if (!preg_match('/^[a-f0-9]{64}$/', $rawKey)) {
        throw new InvalidArgumentException(t('validation.finance_idempotency'));
    }
    return hash_hmac('sha256', 'phase5a:' . $operation, $rawKey);
}

function financeAllocateNumber(int $agencyId, string $type): array
{
    $prefixes=['payment'=>'PAY','payment_adjustment'=>'PAD','invoice'=>'INV','credit_note'=>'CRN','deposit_event'=>'DEP','cash_movement'=>'CSH'];
    if (!isset($prefixes[$type])) throw new InvalidArgumentException('Invalid finance number type.');
    financeAssertAgency($agencyId);
    $pdo=db();
    if ($pdo->inTransaction()) throw new LogicException('Finance numbers must be allocated before the business transaction.');
    $pdo->beginTransaction();
    try {
        dbExecute("INSERT INTO financial_number_allocations(agency_id,number_type,status,allocated_by) VALUES(:agency,:type,'reserved',:user)",['agency'=>$agencyId,'type'=>$type,'user'=>currentUserId()]);
        $id=(int)$pdo->lastInsertId();
        $number=$prefixes[$type].'-'.date('Ymd').'-'.$id;
        $statement=dbExecute('UPDATE financial_number_allocations SET allocated_number=:number WHERE id=:id AND status=\'reserved\' AND allocated_number IS NULL',['number'=>$number,'id'=>$id]);
        if($statement->rowCount()!==1)throw new RuntimeException('Finance number allocation failed.');
        $pdo->commit();
        return ['id'=>$id,'number'=>$number,'type'=>$type];
    } catch(Throwable $exception) {
        if($pdo->inTransaction())$pdo->rollBack();
        throw $exception;
    }
}

function financeConsumeNumber(array $allocation, string $entityType, int $entityId): void
{
    $statement=dbExecute(
        "UPDATE financial_number_allocations SET status='consumed',entity_type=:type,entity_id=:entity,consumed_at=NOW(6)
         WHERE id=:id AND status='reserved' AND allocated_number=:number",
        ['type'=>$entityType,'entity'=>$entityId,'id'=>$allocation['id'],'number'=>$allocation['number']]
    );
    if($statement->rowCount()!==1)throw new DomainException(t('validation.finance_number'));
}

function financeVoidNumber(array $allocation, string $reason): void
{
    if(db()->inTransaction())return;
    dbExecute(
        "UPDATE financial_number_allocations SET status='voided',voided_at=NOW(6),void_reason=:reason
         WHERE id=:id AND status='reserved'",
        ['reason'=>substr(trim($reason),0,255)?:'Business validation rejected','id'=>$allocation['id']]
    );
}

function financeVoidReplayNumbers(array $allocations): void
{
    /* Strategy B for allocations made before the transaction: a completed
     * replay must account for every number reserved by this invocation.  The
     * status predicate in financeVoidNumber only transitions still-reserved
     * rows, so a consumed number can never be rewritten. */
    foreach ($allocations as $allocation) {
        if ($allocation) {
            financeVoidNumber($allocation, 'Idempotent replay; allocation unused');
        }
    }
}

function financeVoidNumbers(array $allocations, Throwable $exception): void
{
    if(!($exception instanceof InvalidArgumentException || $exception instanceof DomainException || $exception instanceof AuthorizationException))return;
    foreach($allocations as $allocation)financeVoidNumber($allocation,'Business validation rejected');
}

function financePreReadAgency(string $table, int $id): array
{
    $allowed=['reservations','payments','deposits','invoices','expenses','cash_registers'];
    if(!in_array($table,$allowed,true))throw new LogicException('Invalid finance aggregate.');
    if($table==='deposits')$row=dbFetchOne('SELECT id,agency_id,currency FROM deposits WHERE id=:id',['id'=>$id]);
    else $row=dbFetchOne("SELECT id,agency_id".($table==='reservations'||$table==='payments'||$table==='invoices'||$table==='cash_registers'?",currency":"")." FROM $table WHERE id=:id",['id'=>$id]);
    if(!$row)throw new InvalidArgumentException(t('validation.finance_not_found'));
    financeAssertAgency((int)$row['agency_id']);
    return$row;
}

function financeLockOpenRegister(int $agencyId, string $currency): array
{
    $register=dbFetchOne("SELECT * FROM cash_registers WHERE agency_id=:agency AND currency=:currency AND status='open' FOR UPDATE",['agency'=>$agencyId,'currency'=>$currency]);
    if(!$register)throw new DomainException(t('validation.cash_register_required'));
    return$register;
}

function financeInsertMovement(array $allocation,array $register,string $type,string $direction,int $amountCents,string $sourceType,int $sourceId,?string $reason=null): int
{
    dbExecute(
        "INSERT INTO cash_movements(agency_id,cash_register_id,movement_number,movement_type,direction,amount,currency,source_entity_type,source_entity_id,reason,status,created_by)
         VALUES(:agency,:register,:number,:type,:direction,:amount,:currency,:source_type,:source_id,:reason,'posted',:user)",
        ['agency'=>$register['agency_id'],'register'=>$register['id'],'number'=>$allocation['number'],'type'=>$type,'direction'=>$direction,'amount'=>centsToMoney($amountCents),'currency'=>$register['currency'],'source_type'=>$sourceType,'source_id'=>$sourceId,'reason'=>$reason,'user'=>currentUserId()]
    );
    $id=(int)db()->lastInsertId();
    financeConsumeNumber($allocation,'cash_movement',$id);
    return$id;
}

function financeReservationNetPaidCents(int $reservationId): int
{
    $row=dbFetchOne(
        "SELECT r.legacy_finance_paid_amount,
          COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.reservation_id=r.id AND p.is_legacy_opening=0 AND p.status<>'cancelled'),0) new_paid,
          COALESCE((SELECT SUM(a.amount) FROM payment_adjustments a JOIN payments p2 ON p2.id=a.payment_id WHERE p2.reservation_id=r.id AND a.status='posted'),0) adjusted
         FROM reservations r WHERE r.id=:id",
        ['id'=>$reservationId]
    );
    if(!$row)throw new InvalidArgumentException(t('validation.reservation_not_found'));
    return max(0,(moneyToCents($row['legacy_finance_paid_amount'])??0)+(moneyToCents($row['new_paid'])??0)-(moneyToCents($row['adjusted'])??0));
}

function financeInvoiceBalance(int $invoiceId): array
{
    $invoice=dbFetchOne('SELECT * FROM invoices WHERE id=:id',['id'=>$invoiceId]);
    if(!$invoice)throw new InvalidArgumentException(t('validation.invoice_not_found'));
    $paid=moneyToCents($invoice['opening_paid_amount'])??0;
    if($invoice['reservation_id']){
        /* Keep payments assigned to another invoice out of this invoice's
         * balance.  A reservation may receive a replacement invoice after
         * an earlier invoice was cancelled; only explicit payments for this
         * invoice (plus reservation-level, invoice-less payments) apply. */
        $new=dbFetchOne(
            "SELECT COALESCE(SUM(amount),0) amount FROM payments
             WHERE is_legacy_opening=0 AND status<>'cancelled'
               AND (invoice_id=:invoice OR (invoice_id IS NULL AND reservation_id=:reservation))",
            ['reservation'=>$invoice['reservation_id'],'invoice'=>$invoiceId]
        );
        $adjusted=dbFetchOne(
            "SELECT COALESCE(SUM(a.amount),0) amount
             FROM payment_adjustments a JOIN payments p ON p.id=a.payment_id
             WHERE a.status='posted'
               AND (p.invoice_id=:invoice OR (p.invoice_id IS NULL AND p.reservation_id=:reservation))",
            ['reservation'=>$invoice['reservation_id'],'invoice'=>$invoiceId]
        );
    }else{
        $new=dbFetchOne("SELECT COALESCE(SUM(amount),0) amount FROM payments WHERE invoice_id=:invoice AND is_legacy_opening=0 AND status<>'cancelled'",['invoice'=>$invoiceId]);
        $adjusted=dbFetchOne("SELECT COALESCE(SUM(a.amount),0) amount FROM payment_adjustments a JOIN payments p ON p.id=a.payment_id WHERE p.invoice_id=:invoice AND a.status='posted'",['invoice'=>$invoiceId]);
    }
    $paid=max(0,$paid+(moneyToCents($new['amount'])??0)-(moneyToCents($adjusted['amount'])??0));
    $creditRow=dbFetchOne("SELECT COALESCE(SUM(total_amount),0) amount FROM invoices WHERE original_invoice_id=:id AND invoice_type='credit_note' AND status='applied'",['id'=>$invoiceId]);
    $credited=moneyToCents($creditRow['amount'])??0;$total=moneyToCents($invoice['total_amount'])??0;$net=max(0,$total-$credited);$remaining=max(0,$net-$paid);$refundable=max(0,$paid-$net);
    $status='unpaid';
    if($refundable>0)$status='overpaid';elseif($credited===$total&&$refundable===0)$status='credited';elseif($remaining===0)$status='paid';elseif($paid>0)$status='partially_paid';elseif($credited>0)$status='partially_credited';
    return compact('paid','credited','total','net','remaining','refundable','status');
}

function financeRefreshInvoice(int $invoiceId): void
{
    $invoice=dbFetchOne('SELECT status,invoice_type FROM invoices WHERE id=:id FOR UPDATE',['id'=>$invoiceId]);
    if(!$invoice||$invoice['invoice_type']!=='invoice'||in_array($invoice['status'],['draft','cancelled'],true))return;
    $balance=financeInvoiceBalance($invoiceId);
    dbExecute('UPDATE invoices SET paid_amount=:paid,status=:status WHERE id=:id',['paid'=>centsToMoney($balance['paid']),'status'=>$balance['status'],'id'=>$invoiceId]);
}

function financeRefreshReservation(int $reservationId): array
{
    $reservation=dbFetchOne('SELECT id,total_amount FROM reservations WHERE id=:id FOR UPDATE',['id'=>$reservationId]);
    if(!$reservation)throw new InvalidArgumentException(t('validation.reservation_not_found'));
    $paid=financeReservationNetPaidCents($reservationId);$total=moneyToCents($reservation['total_amount'])??0;$remaining=max(0,$total-$paid);
    dbExecute('UPDATE reservations SET advance_amount=:paid,remaining_amount=:remaining,updated_by=:user WHERE id=:id',['paid'=>centsToMoney($paid),'remaining'=>centsToMoney($remaining),'user'=>currentUserId(),'id'=>$reservationId]);
    $invoice=dbFetchOne("SELECT id FROM invoices WHERE reservation_id=:reservation AND invoice_type='invoice' AND status NOT IN('draft','cancelled','credited') ORDER BY id DESC LIMIT 1 FOR UPDATE",['reservation'=>$reservationId]);
    if($invoice)financeRefreshInvoice((int)$invoice['id']);
    return ['paid'=>$paid,'remaining'=>$remaining,'total'=>$total];
}

function financePaymentProof(array $input): array
{
    return [
        'path'=>($input['proof_path']??'')?:null,
        'original_name'=>($input['proof_original_name']??'')?:null,
        'mime_type'=>($input['proof_mime_type']??'')?:null,
        'size'=>isset($input['proof_file_size'])?(int)$input['proof_file_size']:null,
    ];
}

function financeContractForReservation($value, int $reservationId): ?int
{
    if ($value === null || $value === '') return null;
    $contractId = (int)$value;
    $contract = dbFetchOne(
        'SELECT rc.id FROM rental_contracts rc WHERE rc.id=:id AND rc.reservation_id=:reservation FOR UPDATE',
        ['id'=>$contractId,'reservation'=>$reservationId]
    );
    if (!$contract) throw new DomainException(t('validation.contract_not_found'));
    return $contractId;
}

function financeRecordPayment(array $input,bool $allowExcess): int
{
    financeRequireCutover();financeAssertPermission($allowExcess?'payments.excess':'payments.create');
    $reservationId=(int)($input['reservation_id']??0);$pre=financePreReadAgency('reservations',$reservationId);$agencyId=(int)$pre['agency_id'];$currency=$pre['currency'];
    $amount=financeMoneyCents($input['amount']??'');$method=validateChoice($input['method']??'',financePaymentMethods(),null);if(!$method)throw new InvalidArgumentException(t('validation.finance_method'));
    if($method==='other'&&trim((string)($input['reference']??''))===''&&trim((string)($input['notes']??''))==='')throw new InvalidArgumentException(t('validation.finance_other_method'));
    $paidAt=validDateTimeValue($input['paid_at']??'');if(!$paidAt)throw new InvalidArgumentException(t('validation.finance_date'));
    $reason=$allowExcess?financeReason($input['reason']??'','validation.finance_excess_reason'):null;$proof=financePaymentProof($input);
    $allocations=[];$paymentNumber=$allocations[]=financeAllocateNumber($agencyId,'payment');$movementNumber=$method==='cash'?($allocations[]=financeAllocateNumber($agencyId,'cash_movement')):null;
    $adjustmentNumber=$allowExcess?($allocations[]=financeAllocateNumber($agencyId,'payment_adjustment')):null;$requestNumber=$allowExcess?($allocations[]=financeAllocateNumber($agencyId,'deposit_event')):null;$receivedNumber=$allowExcess?($allocations[]=financeAllocateNumber($agencyId,'deposit_event')):null;$depositMovement=$allowExcess&&$method==='cash'?($allocations[]=financeAllocateNumber($agencyId,'cash_movement')):null;
    try{return financeWithRetry(function()use($input,$allowExcess,$reservationId,$agencyId,$currency,$amount,$method,$paidAt,$reason,$proof,$paymentNumber,$movementNumber,$adjustmentNumber,$requestNumber,$receivedNumber,$depositMovement){
        $idem=financeAcquireIdempotency($agencyId,$allowExcess?'payment.excess':'payment.create',$input['idempotency_key']??'');financeReplayIfCompleted($idem);
        $register=$method==='cash'?financeLockOpenRegister($agencyId,$currency):null;
        $reservation=dbFetchOne('SELECT * FROM reservations WHERE id=:id FOR UPDATE',['id'=>$reservationId]);if(!$reservation||(int)$reservation['agency_id']!==$agencyId)throw new InvalidArgumentException(t('validation.reservation_not_found'));financeAssertAgency($agencyId);
        $balance=financeRefreshReservation($reservationId);
        $invoiceId=($input['invoice_id']??'')!==''?(int)$input['invoice_id']:null;
        $invoiceRemaining=null;
        if($invoiceId){
            $invoice=dbFetchOne("SELECT * FROM invoices WHERE id=:id AND reservation_id=:reservation AND agency_id=:agency AND invoice_type='invoice' AND invoice_number IS NOT NULL AND issued_at IS NOT NULL AND status NOT IN('draft','cancelled','credited') FOR UPDATE",['id'=>$invoiceId,'reservation'=>$reservationId,'agency'=>$agencyId]);
            if(!$invoice)throw new DomainException(t('validation.invoice_not_found'));
            $invoiceBalance=financeInvoiceBalance($invoiceId);
            $invoiceRemaining=$invoiceBalance['remaining'];
            if($invoiceRemaining<=0)throw new DomainException(t('validation.finance_overpayment'));
        }
        $maximumPayable=min($balance['remaining'],$invoiceRemaining??$balance['remaining']);
        if(!$allowExcess&&$amount>$maximumPayable)throw new DomainException(t('validation.finance_overpayment'));
        if($allowExcess&&$amount<=$maximumPayable)throw new DomainException(t('validation.finance_excess_required'));
        $contractId=financeContractForReservation($input['contract_id']??null,$reservationId);
        dbExecute("INSERT INTO payments(agency_id,reservation_id,contract_id,invoice_id,payment_number,amount,currency,paid_at,method,reference,status,is_legacy_opening,proof_path,proof_original_name,proof_mime_type,proof_file_size,notes,created_by)
          VALUES(:agency,:reservation,:contract,:invoice,:number,:amount,:currency,:paid_at,:method,:reference,'paid',0,:path,:original,:mime,:size,:notes,:user)",[
          'agency'=>$agencyId,'reservation'=>$reservationId,'contract'=>$contractId,'invoice'=>$invoiceId,'number'=>$paymentNumber['number'],'amount'=>centsToMoney($amount),'currency'=>$currency,'paid_at'=>$paidAt->format('Y-m-d H:i:s'),'method'=>$method,'reference'=>trim((string)($input['reference']??''))?:null,'path'=>$proof['path'],'original'=>$proof['original_name'],'mime'=>$proof['mime_type'],'size'=>$proof['size'],'notes'=>trim((string)($input['notes']??''))?:null,'user'=>currentUserId()]);
        $paymentId=(int)db()->lastInsertId();financeConsumeNumber($paymentNumber,'payment',$paymentId);$payable=min($amount,$maximumPayable);$excess=$amount-$payable;$depositId=null;
        if($allowExcess){
            dbExecute("INSERT INTO deposits(agency_id,reservation_id,contract_id,amount,received_amount,currency,status,received_at,retained_amount,returned_amount,legacy_opening_received_amount,legacy_opening_retained_amount,legacy_opening_returned_amount,event_tracking_started_at,created_by,updated_by)
             VALUES(:agency,:reservation,:contract,:amount,:amount2,:currency,'received',NOW(6),0,0,0,0,0,NOW(6),:user,:user2)",['agency'=>$agencyId,'reservation'=>$reservationId,'contract'=>$contractId,'amount'=>centsToMoney($excess),'amount2'=>centsToMoney($excess),'currency'=>$currency,'user'=>currentUserId(),'user2'=>currentUserId()]);$depositId=(int)db()->lastInsertId();
            $requestId=financeInsertDepositEvent($requestNumber,$agencyId,$depositId,'requested',$excess,$currency,null,$paymentId,$reason);$receivedId=financeInsertDepositEvent($receivedNumber,$agencyId,$depositId,'received',$excess,$currency,$method,$paymentId,$reason);
            dbExecute("INSERT INTO payment_adjustments(agency_id,payment_id,destination_deposit_id,adjustment_number,adjustment_type,amount,currency,method,reason,status,created_by) VALUES(:agency,:payment,:deposit,:number,'excess_reallocation',:amount,:currency,NULL,:reason,'posted',:user)",['agency'=>$agencyId,'payment'=>$paymentId,'deposit'=>$depositId,'number'=>$adjustmentNumber['number'],'amount'=>centsToMoney($excess),'currency'=>$currency,'reason'=>$reason,'user'=>currentUserId()]);$adjustmentId=(int)db()->lastInsertId();financeConsumeNumber($adjustmentNumber,'payment_adjustment',$adjustmentId);
            if($register){financeInsertMovement($movementNumber,$register,'payment_in','in',$payable,'payment',$paymentId,$reason);financeInsertMovement($depositMovement,$register,'deposit_in','in',$excess,'deposit_event',$receivedId,$reason);}
        }elseif($register)financeInsertMovement($movementNumber,$register,'payment_in','in',$amount,'payment',$paymentId,null);
        $after=financeRefreshReservation($reservationId);auditLog($allowExcess?'payment.excess_allocated':'payment.recorded','payment',$paymentId,null,['number'=>$paymentNumber['number'],'amount'=>centsToMoney($amount),'reservation_remaining_before'=>centsToMoney($balance['remaining']),'invoice_remaining_before'=>$invoiceRemaining===null?null:centsToMoney($invoiceRemaining),'payable'=>centsToMoney($payable),'excess'=>centsToMoney($excess),'method'=>$method,'deposit_id'=>$depositId,'remaining'=>centsToMoney($after['remaining'])],$agencyId);financeCompleteIdempotency($idem['id'],'payment',$paymentId);return$paymentId;
    });}catch(FinanceIdempotentReplay$replay){financeVoidReplayNumbers($allocations);return(int)$replay->result();}catch(Throwable$exception){financeVoidNumbers($allocations,$exception);throw$exception;}
}

function recordSettledPayment(array $input): int{return financeRecordPayment($input,false);}
function createExcessPayment(array $input): int{return financeRecordPayment($input,true);}

function financeInsertDepositEvent(array $allocation,int $agencyId,int $depositId,string $type,int $amount,string $currency,?string $method,?int $paymentId,?string $reason): int
{
    dbExecute("INSERT INTO deposit_events(agency_id,deposit_id,event_number,event_type,amount,currency,method,payment_id,reason,status,created_by) VALUES(:agency,:deposit,:number,:type,:amount,:currency,:method,:payment,:reason,'posted',:user)",['agency'=>$agencyId,'deposit'=>$depositId,'number'=>$allocation['number'],'type'=>$type,'amount'=>centsToMoney($amount),'currency'=>$currency,'method'=>$method,'payment'=>$paymentId,'reason'=>$reason,'user'=>currentUserId()]);$id=(int)db()->lastInsertId();financeConsumeNumber($allocation,'deposit_event',$id);return$id;
}

function appendPaymentAdjustment(array $input): int
{
    financeRequireCutover();financeAssertPermission('payments.adjust');$paymentId=(int)($input['payment_id']??0);$pre=financePreReadAgency('payments',$paymentId);$agencyId=(int)$pre['agency_id'];$currency=$pre['currency'];$type=validateChoice($input['adjustment_type']??'',array_slice(financeAdjustmentTypes(),0,3),null);if(!$type)throw new InvalidArgumentException(t('validation.finance_adjustment'));
    $method=validateChoice($input['method']??'',financePaymentMethods(),null);if(!$method)throw new InvalidArgumentException(t('validation.finance_method'));$reason=financeReason($input['reason']??'');$requested=$type==='partial_refund'?financeMoneyCents($input['amount']??''):0;
    $allocations=[];$number=$allocations[]=financeAllocateNumber($agencyId,'payment_adjustment');$movement=$method==='cash'?($allocations[]=financeAllocateNumber($agencyId,'cash_movement')):null;
    try{return financeWithRetry(function()use($input,$paymentId,$agencyId,$currency,$type,$method,$reason,$requested,$number,$movement){$idem=financeAcquireIdempotency($agencyId,'payment.adjust',$input['idempotency_key']??'');financeReplayIfCompleted($idem);$register=$method==='cash'?financeLockOpenRegister($agencyId,$currency):null;$payment=dbFetchOne('SELECT * FROM payments WHERE id=:id AND agency_id=:agency FOR UPDATE',['id'=>$paymentId,'agency'=>$agencyId]);if(!$payment)throw new InvalidArgumentException(t('validation.finance_not_found'));$sum=moneyToCents(dbFetchOne("SELECT COALESCE(SUM(amount),0) amount FROM payment_adjustments WHERE payment_id=:id AND status='posted'",['id'=>$paymentId])['amount'])??0;$original=moneyToCents($payment['amount'])??0;$remaining=$original-$sum;if($remaining<=0)throw new DomainException(t('validation.finance_no_adjustable'));
        if($type==='full_reversal'){if($sum!==0)throw new DomainException(t('validation.finance_reversal_after_adjustment'));$amount=$original;}elseif($type==='full_refund')$amount=$remaining;else{$amount=$requested;if($amount>=$remaining)throw new DomainException(t('validation.finance_partial_refund'));}
        dbExecute("INSERT INTO payment_adjustments(agency_id,payment_id,adjustment_number,adjustment_type,amount,currency,method,reference,reason,status,created_by) VALUES(:agency,:payment,:number,:type,:amount,:currency,:method,:reference,:reason,'posted',:user)",['agency'=>$agencyId,'payment'=>$paymentId,'number'=>$number['number'],'type'=>$type,'amount'=>centsToMoney($amount),'currency'=>$currency,'method'=>$method,'reference'=>trim((string)($input['reference']??''))?:null,'reason'=>$reason,'user'=>currentUserId()]);$id=(int)db()->lastInsertId();financeConsumeNumber($number,'payment_adjustment',$id);$newSum=$sum+$amount;$status=$type==='full_reversal'?'reversed':($newSum===$original?'refunded':'partially_adjusted');$statement=dbExecute('UPDATE payments SET status=:status WHERE id=:id AND status=:old',['status'=>$status,'id'=>$paymentId,'old'=>$payment['status']]);if($statement->rowCount()!==1)throw new DomainException(t('validation.finance_stale'));if($register)financeInsertMovement($movement,$register,'refund_out','out',$amount,'payment_adjustment',$id,$reason);if($payment['reservation_id'])$balance=financeRefreshReservation((int)$payment['reservation_id']);elseif($payment['invoice_id'])financeRefreshInvoice((int)$payment['invoice_id']);auditLog('payment.adjusted','payment',$paymentId,['status'=>$payment['status'],'adjusted'=>centsToMoney($sum)],['status'=>$status,'adjustment_id'=>$id,'type'=>$type,'amount'=>centsToMoney($amount),'adjusted'=>centsToMoney($newSum),'reason'=>$reason],$agencyId);financeCompleteIdempotency($idem['id'],'payment_adjustment',$id);return$id;});}catch(FinanceIdempotentReplay$replay){financeVoidReplayNumbers($allocations);return(int)$replay->result();}catch(Throwable$exception){financeVoidNumbers($allocations,$exception);throw$exception;}
}

function createDeposit(array $input): int
{
    financeRequireCutover();financeAssertPermission('deposits.manage');$reservationId=(int)($input['reservation_id']??0);$pre=financePreReadAgency('reservations',$reservationId);$agencyId=(int)$pre['agency_id'];$amount=financeMoneyCents($input['amount']??'');$allocation=financeAllocateNumber($agencyId,'deposit_event');
    try{return financeWithRetry(function()use($input,$reservationId,$pre,$agencyId,$amount,$allocation){$idem=financeAcquireIdempotency($agencyId,'deposit.create',$input['idempotency_key']??'');financeReplayIfCompleted($idem);$reservation=dbFetchOne('SELECT * FROM reservations WHERE id=:id FOR UPDATE',['id'=>$reservationId]);if(!$reservation||(int)$reservation['agency_id']!==$agencyId)throw new InvalidArgumentException(t('validation.reservation_not_found'));$contractId=financeContractForReservation($input['contract_id']??null,$reservationId);dbExecute("INSERT INTO deposits(agency_id,reservation_id,contract_id,amount,received_amount,currency,status,retained_amount,returned_amount,legacy_opening_received_amount,legacy_opening_retained_amount,legacy_opening_returned_amount,event_tracking_started_at,created_by,updated_by) VALUES(:agency,:reservation,:contract,:amount,0,:currency,'requested',0,0,0,0,0,NOW(6),:user,:user2)",['agency'=>$agencyId,'reservation'=>$reservationId,'contract'=>$contractId,'amount'=>centsToMoney($amount),'currency'=>$pre['currency'],'user'=>currentUserId(),'user2'=>currentUserId()]);$id=(int)db()->lastInsertId();financeInsertDepositEvent($allocation,$agencyId,$id,'requested',$amount,$pre['currency'],null,null,trim((string)($input['reason']??''))?:null);auditLog('deposit.created','deposit',$id,null,['amount'=>centsToMoney($amount),'status'=>'requested'],$agencyId);financeCompleteIdempotency($idem['id'],'deposit',$id);return$id;});}catch(FinanceIdempotentReplay$replay){financeVoidReplayNumbers([$allocation]);return(int)$replay->result();}catch(Throwable$exception){financeVoidNumbers([$allocation],$exception);throw$exception;}
}

function financeDepositTotals(array $deposit): array
{
    if($deposit['legacy_opening_received_amount']===null||$deposit['legacy_opening_returned_amount']===null)throw new DomainException(t('validation.deposit_unresolved'));
    $events=dbFetchOne("SELECT COALESCE(SUM(CASE WHEN event_type='received' THEN amount ELSE 0 END),0) received,COALESCE(SUM(CASE WHEN event_type IN('partially_retained','fully_retained') THEN amount ELSE 0 END),0) retained,COALESCE(SUM(CASE WHEN event_type IN('partially_returned','returned') THEN amount ELSE 0 END),0) returned FROM deposit_events WHERE deposit_id=:id AND status='posted'",['id'=>$deposit['id']]);
    return ['received'=>(moneyToCents($deposit['legacy_opening_received_amount'])??0)+(moneyToCents($events['received'])??0),'retained'=>(moneyToCents($deposit['legacy_opening_retained_amount'])??0)+(moneyToCents($events['retained'])??0),'returned'=>(moneyToCents($deposit['legacy_opening_returned_amount'])??0)+(moneyToCents($events['returned'])??0)];
}

function appendDepositEvent(array $input): int
{
    financeRequireCutover();financeAssertPermission('deposits.manage');$depositId=(int)($input['deposit_id']??0);$pre=financePreReadAgency('deposits',$depositId);$agencyId=(int)$pre['agency_id'];$currency=$pre['currency'];$type=validateChoice($input['event_type']??'',['received','held','partially_retained','fully_retained','partially_returned','returned'],null);if(!$type)throw new InvalidArgumentException(t('validation.deposit_event'));
    $method=in_array($type,['received','partially_returned','returned'],true)?validateChoice($input['method']??'',financePaymentMethods(),null):null;if(in_array($type,['received','partially_returned','returned'],true)&&!$method)throw new InvalidArgumentException(t('validation.finance_method'));$amount=$type==='held'?0:financeMoneyCents($input['amount']??'');$reason=in_array($type,['partially_retained','fully_retained'],true)?financeReason($input['reason']??''):trim((string)($input['reason']??''));
    $allocations=[];$number=$allocations[]=financeAllocateNumber($agencyId,'deposit_event');$cash=in_array($type,['received','partially_returned','returned'],true)&&$method==='cash';$movement=$cash?($allocations[]=financeAllocateNumber($agencyId,'cash_movement')):null;
    try{return financeWithRetry(function()use($input,$depositId,$agencyId,$currency,$type,$method,$amount,$reason,$number,$movement,$cash){$idem=financeAcquireIdempotency($agencyId,'deposit.event',$input['idempotency_key']??'');financeReplayIfCompleted($idem);$register=$cash?financeLockOpenRegister($agencyId,$currency):null;$deposit=dbFetchOne('SELECT * FROM deposits WHERE id=:id AND agency_id=:agency FOR UPDATE',['id'=>$depositId,'agency'=>$agencyId]);if(!$deposit)throw new InvalidArgumentException(t('validation.finance_not_found'));$totals=financeDepositTotals($deposit);$requested=moneyToCents($deposit['amount'])??0;$liability=$totals['received']-$totals['retained']-$totals['returned'];if($liability<0)throw new DomainException(t('validation.deposit_invariant'));
        if($type==='received'&&$totals['received']+$amount>$requested)throw new DomainException(t('validation.deposit_received_cap'));if($type==='held'&&$totals['received']<=0)throw new DomainException(t('validation.deposit_event'));if(in_array($type,['partially_retained','partially_returned'],true)&&$amount>=$liability)throw new DomainException(t('validation.deposit_partial'));if(in_array($type,['fully_retained','returned'],true)&&$amount!==$liability)throw new DomainException(t('validation.deposit_terminal'));if(in_array($type,['partially_retained','fully_retained','partially_returned','returned'],true)&&$liability<=0)throw new DomainException(t('validation.deposit_invariant'));
        $id=financeInsertDepositEvent($number,$agencyId,$depositId,$type,$amount,$currency,$method,null,$reason?:null);if($type==='received')$totals['received']+=$amount;elseif(in_array($type,['partially_retained','fully_retained'],true))$totals['retained']+=$amount;elseif(in_array($type,['partially_returned','returned'],true))$totals['returned']+=$amount;if($totals['retained']+$totals['returned']>$totals['received'])throw new DomainException(t('validation.deposit_invariant'));
        $status=$type;$statement=dbExecute("UPDATE deposits SET status=:status,received_amount=:received,retained_amount=:retained,returned_amount=:returned,received_at=CASE WHEN :received2>0 THEN COALESCE(received_at,NOW(6)) ELSE received_at END,returned_at=CASE WHEN :status2='returned' THEN NOW(6) ELSE returned_at END,retention_reason=CASE WHEN :retained2>0 THEN :reason ELSE retention_reason END,updated_by=:user WHERE id=:id AND updated_at=:updated",['status'=>$status,'received'=>centsToMoney($totals['received']),'retained'=>centsToMoney($totals['retained']),'returned'=>centsToMoney($totals['returned']),'received2'=>$totals['received'],'status2'=>$status,'retained2'=>$totals['retained'],'reason'=>$reason?:null,'user'=>currentUserId(),'id'=>$depositId,'updated'=>$deposit['updated_at']]);if($statement->rowCount()!==1)throw new DomainException(t('validation.finance_stale'));if($register){$movementType=$type==='received'?'deposit_in':'deposit_return_out';$direction=$type==='received'?'in':'out';financeInsertMovement($movement,$register,$movementType,$direction,$amount,'deposit_event',$id,$reason?:null);}auditLog('deposit.event_recorded','deposit',$depositId,['status'=>$deposit['status'],'received'=>$deposit['received_amount'],'retained'=>$deposit['retained_amount'],'returned'=>$deposit['returned_amount']],['event_id'=>$id,'event_type'=>$type,'amount'=>centsToMoney($amount),'received'=>centsToMoney($totals['received']),'retained'=>centsToMoney($totals['retained']),'returned'=>centsToMoney($totals['returned'])],$agencyId);financeCompleteIdempotency($idem['id'],'deposit_event',$id);return$id;});}catch(FinanceIdempotentReplay$replay){financeVoidReplayNumbers($allocations);return(int)$replay->result();}catch(Throwable$exception){financeVoidNumbers($allocations,$exception);throw$exception;}
}

function resolveLegacyDepositOpening(array $input): bool
{
    financeRequireCutover();financeAssertPermission('deposits.manage');$depositId=(int)($input['deposit_id']??0);$pre=financePreReadAgency('deposits',$depositId);$agencyId=(int)$pre['agency_id'];$received=financeMoneyCents($input['received_amount']??'',true);$retained=financeMoneyCents($input['retained_amount']??'',true);$returned=financeMoneyCents($input['returned_amount']??'',true);if($retained+$returned>$received)throw new DomainException(t('validation.deposit_invariant'));$reason=financeReason($input['reason']??'');
    return financeWithRetry(function()use($input,$depositId,$agencyId,$received,$retained,$returned,$reason){$idem=financeAcquireIdempotency($agencyId,'deposit.resolve_legacy',$input['idempotency_key']??'');if($idem['completed'])return true;$row=dbFetchOne('SELECT * FROM deposits WHERE id=:id AND agency_id=:agency FOR UPDATE',['id'=>$depositId,'agency'=>$agencyId]);if(!$row)throw new InvalidArgumentException(t('validation.finance_not_found'));if($row['legacy_opening_received_amount']!==null&&$row['legacy_opening_returned_amount']!==null)throw new DomainException(t('validation.deposit_already_resolved'));if(!hash_equals((string)$row['updated_at'],(string)($input['updated_at']??'')))throw new DomainException(t('validation.finance_stale'));$statement=dbExecute('UPDATE deposits SET legacy_opening_received_amount=:received,legacy_opening_retained_amount=:retained,legacy_opening_returned_amount=:returned,received_amount=:received2,retained_amount=:retained2,returned_amount=:returned2,legacy_opening_resolved_at=NOW(6),legacy_opening_resolved_by=:user,legacy_opening_resolution_reason=:reason,updated_by=:user2 WHERE id=:id AND updated_at=:updated',['received'=>centsToMoney($received),'retained'=>centsToMoney($retained),'returned'=>centsToMoney($returned),'received2'=>centsToMoney($received),'retained2'=>centsToMoney($retained),'returned2'=>centsToMoney($returned),'user'=>currentUserId(),'reason'=>$reason,'user2'=>currentUserId(),'id'=>$depositId,'updated'=>$row['updated_at']]);if($statement->rowCount()!==1)throw new DomainException(t('validation.finance_stale'));auditLog('deposit.legacy_opening_resolved','deposit',$depositId,['received'=>$row['legacy_opening_received_amount'],'retained'=>$row['legacy_opening_retained_amount'],'returned'=>$row['legacy_opening_returned_amount']],['received'=>centsToMoney($received),'retained'=>centsToMoney($retained),'returned'=>centsToMoney($returned),'reason'=>$reason,'resolved_by'=>currentUserId()],$agencyId);financeCompleteIdempotency($idem['id'],'deposit',$depositId);return true;});
}

function createInvoiceDraftFromReservation(array $input): int
{
    financeRequireCutover();financeAssertPermission('invoices.manage');$reservationId=(int)($input['reservation_id']??0);$pre=financePreReadAgency('reservations',$reservationId);$agencyId=(int)$pre['agency_id'];$language=validateChoice($input['language_code']??language(),supportedLanguages(),'en');
    return financeWithRetry(function()use($input,$reservationId,$agencyId,$language){$idem=financeAcquireIdempotency($agencyId,'invoice.draft',$input['idempotency_key']??'');if($idem['completed'])return(int)$idem['result_id'];$reservation=dbFetchOne('SELECT * FROM reservations WHERE id=:id FOR UPDATE',['id'=>$reservationId]);if(!$reservation)throw new InvalidArgumentException(t('validation.reservation_not_found'));if(dbFetchOne("SELECT id FROM invoices WHERE reservation_id=:id AND invoice_type='invoice' AND status NOT IN('cancelled','credited') FOR UPDATE",['id'=>$reservationId]))throw new DomainException(t('validation.invoice_duplicate'));$subtotal=(moneyToCents($reservation['total_amount'])??0)-(moneyToCents($reservation['tax_amount'])??0);dbExecute("INSERT INTO invoices(agency_id,customer_id,reservation_id,invoice_number,invoice_type,language_code,status,currency,subtotal,tax_amount,total_amount,paid_amount,opening_paid_amount,issued_at,due_at,created_by) VALUES(:agency,:customer,:reservation,NULL,'invoice',:language,'draft',:currency,:subtotal,:tax,:total,0,:opening,NULL,NULL,:user)",['agency'=>$agencyId,'customer'=>$reservation['customer_id'],'reservation'=>$reservationId,'language'=>$language,'currency'=>$reservation['currency'],'subtotal'=>centsToMoney($subtotal),'tax'=>$reservation['tax_amount'],'total'=>$reservation['total_amount'],'opening'=>$reservation['legacy_finance_paid_amount'],'user'=>currentUserId()]);$id=(int)db()->lastInsertId();dbExecute('INSERT INTO invoice_items(invoice_id,description,quantity,unit_price,tax_rate,line_total) VALUES(:invoice,:description,:quantity,:unit,:tax,:total)',['invoice'=>$id,'description'=>'Vehicle rental '.$reservation['reference'],'quantity'=>$reservation['rental_days'],'unit'=>$reservation['daily_price'],'tax'=>$reservation['tax_rate']??0,'total'=>$reservation['total_amount']]);auditLog('invoice.draft_created','invoice',$id,null,['reservation_id'=>$reservationId,'total'=>$reservation['total_amount'],'language'=>$language],$agencyId);financeCompleteIdempotency($idem['id'],'invoice',$id);return$id;});
}

function issueInvoice(array $input): int
{
    financeRequireCutover();financeAssertPermission('invoices.manage');$invoiceId=(int)($input['invoice_id']??0);$pre=financePreReadAgency('invoices',$invoiceId);$agencyId=(int)$pre['agency_id'];$allocation=financeAllocateNumber($agencyId,'invoice');
    try{return financeWithRetry(function()use($input,$invoiceId,$agencyId,$allocation){$idem=financeAcquireIdempotency($agencyId,'invoice.issue',$input['idempotency_key']??'');financeReplayIfCompleted($idem);$invoice=dbFetchOne('SELECT * FROM invoices WHERE id=:id AND agency_id=:agency FOR UPDATE',['id'=>$invoiceId,'agency'=>$agencyId]);if(!$invoice||$invoice['invoice_type']!=='invoice'||$invoice['status']!=='draft')throw new DomainException(t('validation.invoice_not_draft'));$balance=financeInvoiceBalance($invoiceId);$status=$balance['status'];$statement=dbExecute('UPDATE invoices SET invoice_number=:number,status=:status,paid_amount=:paid,issued_at=NOW(6),due_at=DATE_ADD(NOW(6),INTERVAL 7 DAY) WHERE id=:id AND status=\'draft\' AND invoice_number IS NULL',['number'=>$allocation['number'],'status'=>$status,'paid'=>centsToMoney($balance['paid']),'id'=>$invoiceId]);if($statement->rowCount()!==1)throw new DomainException(t('validation.finance_stale'));financeConsumeNumber($allocation,'invoice',$invoiceId);auditLog('invoice.issued','invoice',$invoiceId,['status'=>'draft'],['status'=>$status,'number'=>$allocation['number'],'paid'=>centsToMoney($balance['paid'])],$agencyId);financeCompleteIdempotency($idem['id'],'invoice',$invoiceId);return$invoiceId;});}catch(FinanceIdempotentReplay$replay){financeVoidReplayNumbers([$allocation]);return(int)$replay->result();}catch(Throwable$exception){financeVoidNumbers([$allocation],$exception);throw$exception;}
}

function createAndIssueInvoiceFromReservation(array $input): int
{
    /*
     * Compatibility orchestration is one command, so it needs one
     * authoritative idempotency record.  Calling the independent draft and
     * issue commands with derived keys leaves a replay looking like two
     * unrelated operations (and permits a partially completed orchestration
     * to be observed between them).  Keep the public draft/issue APIs
     * independently idempotent, but perform this compatibility path in one
     * transaction and one finance_idempotency_keys row.
     */
    financeRequireCutover();
    financeAssertPermission('invoices.manage');
    $reservationId=(int)($input['reservation_id']??0);
    $pre=financePreReadAgency('reservations',$reservationId);
    $agencyId=(int)$pre['agency_id'];
    $language=validateChoice($input['language_code']??language(),supportedLanguages(),'en');
    $allocation=financeAllocateNumber($agencyId,'invoice');
    try {
        return financeWithRetry(function() use ($input,$reservationId,$agencyId,$language,$allocation) {
            $idem=financeAcquireIdempotency($agencyId,'invoice.create_issue',$input['idempotency_key']??'');
            financeReplayIfCompleted($idem);
            $reservation=dbFetchOne('SELECT * FROM reservations WHERE id=:id AND agency_id=:agency FOR UPDATE',['id'=>$reservationId,'agency'=>$agencyId]);
            if(!$reservation) throw new InvalidArgumentException(t('validation.reservation_not_found'));
            if(dbFetchOne("SELECT id FROM invoices WHERE reservation_id=:id AND invoice_type='invoice' AND status NOT IN('cancelled','credited') FOR UPDATE",['id'=>$reservationId])) {
                throw new DomainException(t('validation.invoice_duplicate'));
            }
            $subtotal=(moneyToCents($reservation['total_amount'])??0)-(moneyToCents($reservation['tax_amount'])??0);
            dbExecute(
                "INSERT INTO invoices(agency_id,customer_id,reservation_id,invoice_number,invoice_type,language_code,status,currency,subtotal,tax_amount,total_amount,paid_amount,opening_paid_amount,issued_at,due_at,created_by)
                 VALUES(:agency,:customer,:reservation,NULL,'invoice',:language,'draft',:currency,:subtotal,:tax,:total,0,:opening,NULL,NULL,:user)",
                ['agency'=>$agencyId,'customer'=>$reservation['customer_id'],'reservation'=>$reservationId,'language'=>$language,
                 'currency'=>$reservation['currency'],'subtotal'=>centsToMoney($subtotal),'tax'=>$reservation['tax_amount'],
                 'total'=>$reservation['total_amount'],'opening'=>$reservation['legacy_finance_paid_amount'],'user'=>currentUserId()]
            );
            $invoiceId=(int)db()->lastInsertId();
            dbExecute(
                'INSERT INTO invoice_items(invoice_id,description,quantity,unit_price,tax_rate,line_total) VALUES(:invoice,:description,:quantity,:unit,:tax,:total)',
                ['invoice'=>$invoiceId,'description'=>'Vehicle rental '.$reservation['reference'],'quantity'=>$reservation['rental_days'],
                 'unit'=>$reservation['daily_price'],'tax'=>$reservation['tax_rate']??0,'total'=>$reservation['total_amount']]
            );
            $balance=financeInvoiceBalance($invoiceId);
            $status=$balance['status'];
            $statement=dbExecute(
                "UPDATE invoices SET invoice_number=:number,status=:status,paid_amount=:paid,issued_at=NOW(6),due_at=DATE_ADD(NOW(6),INTERVAL 7 DAY)
                 WHERE id=:id AND status='draft' AND invoice_number IS NULL",
                ['number'=>$allocation['number'],'status'=>$status,'paid'=>centsToMoney($balance['paid']),'id'=>$invoiceId]
            );
            if($statement->rowCount()!==1) throw new DomainException(t('validation.finance_stale'));
            financeConsumeNumber($allocation,'invoice',$invoiceId);
            auditLog('invoice.draft_created','invoice',$invoiceId,null,
                ['reservation_id'=>$reservationId,'total'=>$reservation['total_amount'],'language'=>$language],$agencyId);
            auditLog('invoice.issued','invoice',$invoiceId,['status'=>'draft'],
                ['status'=>$status,'number'=>$allocation['number'],'paid'=>centsToMoney($balance['paid'])],$agencyId);
            financeCompleteIdempotency($idem['id'],'invoice',$invoiceId);
            return $invoiceId;
        });
    } catch(FinanceIdempotentReplay $replay) {
        financeVoidReplayNumbers([$allocation]);
        return (int)$replay->result();
    } catch(Throwable $exception) {
        financeVoidNumbers([$allocation],$exception);
        throw $exception;
    }
}

function cancelInvoiceDraft(array $input): bool
{
    financeRequireCutover();financeAssertPermission('invoices.manage');$invoiceId=(int)($input['invoice_id']??0);$pre=financePreReadAgency('invoices',$invoiceId);$agencyId=(int)$pre['agency_id'];$reason=financeReason($input['reason']??'');return financeWithRetry(function()use($input,$invoiceId,$agencyId,$reason){$idem=financeAcquireIdempotency($agencyId,'invoice.cancel_draft',$input['idempotency_key']??'');if($idem['completed'])return true;$invoice=dbFetchOne('SELECT * FROM invoices WHERE id=:id AND agency_id=:agency FOR UPDATE',['id'=>$invoiceId,'agency'=>$agencyId]);if(!$invoice||$invoice['status']!=='draft'||$invoice['invoice_number']!==null)throw new DomainException(t('validation.invoice_not_draft'));$statement=dbExecute("UPDATE invoices SET status='cancelled',cancelled_at=NOW(6),cancelled_by=:user,cancellation_reason=:reason WHERE id=:id AND status='draft'",['user'=>currentUserId(),'reason'=>$reason,'id'=>$invoiceId]);if($statement->rowCount()!==1)throw new DomainException(t('validation.finance_stale'));auditLog('invoice.draft_cancelled','invoice',$invoiceId,['status'=>'draft'],['status'=>'cancelled','reason'=>$reason],$agencyId);financeCompleteIdempotency($idem['id'],'invoice',$invoiceId);return true;});
}

function issueCreditNote(array $input): int
{
    financeRequireCutover();financeAssertPermission('invoices.manage');$originalId=(int)($input['invoice_id']??0);$pre=financePreReadAgency('invoices',$originalId);$agencyId=(int)$pre['agency_id'];$amount=financeMoneyCents($input['amount']??'');$reason=financeReason($input['reason']??'');$allocation=financeAllocateNumber($agencyId,'credit_note');
    try{return financeWithRetry(function()use($input,$originalId,$agencyId,$amount,$reason,$allocation){$idem=financeAcquireIdempotency($agencyId,'credit_note.issue',$input['idempotency_key']??'');financeReplayIfCompleted($idem);$original=dbFetchOne('SELECT * FROM invoices WHERE id=:id AND agency_id=:agency FOR UPDATE',['id'=>$originalId,'agency'=>$agencyId]);if(!$original||$original['invoice_type']!=='invoice'||in_array($original['status'],['draft','cancelled','credited'],true))throw new DomainException(t('validation.credit_note_invoice'));$balance=financeInvoiceBalance($originalId);$credited=$balance['credited'];$total=$balance['total'];if($credited+$amount+$balance['paid']>$total)throw new DomainException(t('validation.credit_note_cap'));dbExecute("INSERT INTO invoices(agency_id,customer_id,reservation_id,contract_id,original_invoice_id,invoice_number,invoice_type,language_code,status,currency,subtotal,tax_amount,total_amount,paid_amount,opening_paid_amount,issued_at,credit_reason,created_by) VALUES(:agency,:customer,:reservation,:contract,:original,:number,'credit_note',:language,'applied',:currency,:amount,0,:amount2,0,0,NOW(6),:reason,:user)",['agency'=>$agencyId,'customer'=>$original['customer_id'],'reservation'=>$original['reservation_id'],'contract'=>$original['contract_id'],'original'=>$originalId,'number'=>$allocation['number'],'language'=>$original['language_code'],'currency'=>$original['currency'],'amount'=>centsToMoney($amount),'amount2'=>centsToMoney($amount),'reason'=>$reason,'user'=>currentUserId()]);$id=(int)db()->lastInsertId();dbExecute('INSERT INTO invoice_items(invoice_id,description,quantity,unit_price,tax_rate,line_total) VALUES(:invoice,:description,1,:amount,0,:amount2)',['invoice'=>$id,'description'=>$reason,'amount'=>centsToMoney($amount),'amount2'=>centsToMoney($amount)]);financeConsumeNumber($allocation,'credit_note',$id);financeRefreshInvoice($originalId);auditLog('credit_note.issued','invoice',$id,null,['number'=>$allocation['number'],'original_invoice_id'=>$originalId,'amount'=>centsToMoney($amount),'reason'=>$reason],$agencyId);financeCompleteIdempotency($idem['id'],'invoice',$id);return$id;});}catch(FinanceIdempotentReplay$replay){financeVoidReplayNumbers([$allocation]);return(int)$replay->result();}catch(Throwable$exception){financeVoidNumbers([$allocation],$exception);throw$exception;}
}

function createExpense(array $input): int
{
    financeRequireCutover();financeAssertPermission('expenses.manage');$agencyId=(int)($input['agency_id']??0);financeAssertAgency($agencyId);$amount=financeMoneyCents($input['amount']??'');$method=validateChoice($input['method']??'',financePaymentMethods(),null);if(!$method)throw new InvalidArgumentException(t('validation.finance_method'));$date=validDateValue($input['expense_date']??'');if(!$date)throw new InvalidArgumentException(t('validation.finance_date'));$metadata=$input['receipt']??[];
    return financeWithRetry(function()use($input,$agencyId,$amount,$method,$date,$metadata){$idem=financeAcquireIdempotency($agencyId,'expense.create',$input['idempotency_key']??'');if($idem['completed'])return(int)$idem['result_id'];$vehicleId=($input['vehicle_id']??'')!==''?(int)$input['vehicle_id']:null;if($vehicleId&&!dbFetchOne('SELECT id FROM vehicles WHERE id=:id AND agency_id=:agency FOR UPDATE',['id'=>$vehicleId,'agency'=>$agencyId]))throw new DomainException(t('validation.finance_not_found'));$contractId=($input['contract_id']??'')!==''?(int)$input['contract_id']:null;if($contractId&&!dbFetchOne('SELECT rc.id FROM rental_contracts rc JOIN reservations r ON r.id=rc.reservation_id WHERE rc.id=:id AND r.agency_id=:agency FOR UPDATE',['id'=>$contractId,'agency'=>$agencyId]))throw new DomainException(t('validation.finance_not_found'));$original=($input['original_expense_id']??'')!==''?(int)$input['original_expense_id']:null;$type=$original?'compensation':'expense';$direction=$original?'reduction':'outflow';if($original){$row=dbFetchOne("SELECT * FROM expenses WHERE id=:id AND agency_id=:agency AND status='approved' FOR UPDATE",['id'=>$original,'agency'=>$agencyId]);if(!$row)throw new DomainException(t('validation.expense_original'));}
        dbExecute("INSERT INTO expenses(agency_id,vehicle_id,contract_id,original_expense_id,category,expense_type,direction,supplier,description,amount,currency,method,expense_date,status,receipt_path,receipt_original_name,receipt_mime_type,receipt_file_size,created_by) VALUES(:agency,:vehicle,:contract,:original,:category,:type,:direction,:supplier,:description,:amount,:currency,:method,:date,'pending',:path,:name,:mime,:size,:user)",['agency'=>$agencyId,'vehicle'=>$vehicleId,'contract'=>$contractId,'original'=>$original,'category'=>validateChoice($input['category']??'',['maintenance','repair','insurance','technical_inspection','tax','cleaning','fuel','leasing','parking','fine','partner_commission','administration','other'],'other'),'type'=>$type,'direction'=>$direction,'supplier'=>trim((string)($input['supplier']??''))?:null,'description'=>financeReason($input['description']??'','validation.expense_description'),'amount'=>centsToMoney($amount),'currency'=>dbFetchOne('SELECT currency FROM agencies WHERE id=:id',['id'=>$agencyId])['currency'],'method'=>$method,'date'=>$date->format('Y-m-d'),'path'=>$metadata['path']??null,'name'=>$metadata['original_name']??null,'mime'=>$metadata['mime_type']??null,'size'=>$metadata['size']??null,'user'=>currentUserId()]);$id=(int)db()->lastInsertId();auditLog($type==='compensation'?'expense.compensation_created':'expense.created','expense',$id,null,['amount'=>centsToMoney($amount),'method'=>$method,'type'=>$type,'original_expense_id'=>$original],$agencyId);financeCompleteIdempotency($idem['id'],'expense',$id);return$id;});
}

function createCompensatingExpense(array $input): int{return createExpense($input);}

function decideExpense(array $input): bool
{
    financeRequireCutover();financeAssertPermission('expenses.decide');$expenseId=(int)($input['expense_id']??0);$pre=financePreReadAgency('expenses',$expenseId);$agencyId=(int)$pre['agency_id'];$decision=validateChoice($input['decision']??'',['approved','rejected'],null);if(!$decision)throw new InvalidArgumentException(t('validation.expense_decision'));$reason=financeReason($input['reason']??'');$preExpense=dbFetchOne('SELECT method,currency FROM expenses WHERE id=:id',['id'=>$expenseId]);$cash=$decision==='approved'&&$preExpense&&$preExpense['method']==='cash';$allocations=[];$movement=$cash?($allocations[]=financeAllocateNumber($agencyId,'cash_movement')):null;
    try{return financeWithRetry(function()use($input,$expenseId,$agencyId,$decision,$reason,$cash,$movement,$preExpense){$idem=financeAcquireIdempotency($agencyId,'expense.decide',$input['idempotency_key']??'');if($idem['completed']){if($cash)throw new FinanceIdempotentReplay($idem['result_id']);return true;}$register=$cash?financeLockOpenRegister($agencyId,$preExpense['currency']):null;$expense=dbFetchOne('SELECT * FROM expenses WHERE id=:id AND agency_id=:agency FOR UPDATE',['id'=>$expenseId,'agency'=>$agencyId]);if(!$expense||$expense['status']!=='pending')throw new DomainException(t('validation.expense_not_pending'));$self=(int)$expense['created_by']===(int)currentUserId();$exception=!empty($input['owner_exception']);$exceptionReason=null;if($self){if(currentUserRole()!==ROLE_OWNER||!$exception)throw new DomainException(t('validation.expense_self_decision'));$exceptionReason=financeReason($input['owner_exception_reason']??'','validation.expense_owner_exception');}elseif($exception)throw new DomainException(t('validation.expense_owner_exception'));
        if($decision==='approved'&&$expense['expense_type']==='compensation'){$original=dbFetchOne("SELECT * FROM expenses WHERE id=:id AND agency_id=:agency AND status='approved' FOR UPDATE",['id'=>$expense['original_expense_id'],'agency'=>$agencyId]);if(!$original)throw new DomainException(t('validation.expense_original'));$used=moneyToCents(dbFetchOne("SELECT COALESCE(SUM(amount),0) amount FROM expenses WHERE original_expense_id=:id AND status='approved' AND expense_type='compensation'",['id'=>$original['id']])['amount'])??0;if($used+(moneyToCents($expense['amount'])??0)>(moneyToCents($original['amount'])??0))throw new DomainException(t('validation.expense_compensation_cap'));}
        $statement=dbExecute("UPDATE expenses SET status=:status,approved_at=CASE WHEN :approved='approved' THEN NOW(6) ELSE approved_at END,approved_by=CASE WHEN :approved2='approved' THEN :user ELSE approved_by END,decided_at=NOW(6),decided_by=:user2,decision_reason=:reason,owner_exception_used=:exception,owner_exception_reason=:exception_reason WHERE id=:id AND status='pending' AND updated_at=:updated",['status'=>$decision,'approved'=>$decision,'approved2'=>$decision,'user'=>currentUserId(),'user2'=>currentUserId(),'reason'=>$reason,'exception'=>$exception?1:0,'exception_reason'=>$exceptionReason,'id'=>$expenseId,'updated'=>$expense['updated_at']]);if($statement->rowCount()!==1)throw new DomainException(t('validation.finance_stale'));if($register){$amount=moneyToCents($expense['amount'])??0;$type=$expense['expense_type']==='compensation'?'manual_in':'expense_out';$direction=$expense['expense_type']==='compensation'?'in':'out';financeInsertMovement($movement,$register,$type,$direction,$amount,'expense',$expenseId,$reason);}auditLog('expense.'.$decision,'expense',$expenseId,['status'=>'pending','created_by'=>$expense['created_by']],['status'=>$decision,'reason'=>$reason,'decided_by'=>currentUserId(),'owner_exception'=>$exception,'owner_exception_reason'=>$exceptionReason],$agencyId);financeCompleteIdempotency($idem['id'],'expense',$expenseId);return true;});}catch(FinanceIdempotentReplay$replay){financeVoidReplayNumbers($allocations);return true;}catch(Throwable$exception){financeVoidNumbers($allocations,$exception);throw$exception;}
}

function openCashRegister(array $input): int
{
    financeRequireCutover();financeAssertPermission('cash.manage');$agencyId=(int)($input['agency_id']??0);financeAssertAgency($agencyId);$opening=financeMoneyCents($input['opening_balance']??'0',true);$date=validDateValue($input['business_date']??'');if(!$date)throw new InvalidArgumentException(t('validation.cash_open_fields'));$agency=dbFetchOne('SELECT currency FROM agencies WHERE id=:id',['id'=>$agencyId]);if(!$agency)throw new InvalidArgumentException(t('validation.finance_not_found'));
    return financeWithRetry(function()use($input,$agencyId,$opening,$date,$agency){$idem=financeAcquireIdempotency($agencyId,'cash.open',$input['idempotency_key']??'');if($idem['completed'])return(int)$idem['result_id'];if(dbFetchOne("SELECT id FROM cash_registers WHERE agency_id=:agency AND status='open' FOR UPDATE",['agency'=>$agencyId]))throw new DomainException(t('validation.cash_already_open'));dbExecute("INSERT INTO cash_registers(agency_id,business_date,currency,opening_balance,legacy_net_movement_amount,movement_tracking_started_at,status,opened_by) VALUES(:agency,:date,:currency,:opening,0,NOW(6),'open',:user)",['agency'=>$agencyId,'date'=>$date->format('Y-m-d'),'currency'=>$agency['currency'],'opening'=>centsToMoney($opening),'user'=>currentUserId()]);$id=(int)db()->lastInsertId();auditLog('cash_register.opened','cash_register',$id,null,['opening_balance'=>centsToMoney($opening),'business_date'=>$date->format('Y-m-d'),'currency'=>$agency['currency']],$agencyId);financeCompleteIdempotency($idem['id'],'cash_register',$id);return$id;});
}

function postManualCashMovement(array $input): int
{
    financeRequireCutover();financeAssertPermission('cash.manage');$registerId=(int)($input['cash_register_id']??0);$pre=financePreReadAgency('cash_registers',$registerId);$agencyId=(int)$pre['agency_id'];$type=validateChoice($input['movement_type']??'',['manual_in','manual_out'],null);if(!$type)throw new InvalidArgumentException(t('validation.cash_movement'));$amount=financeMoneyCents($input['amount']??'');$reason=financeReason($input['reason']??'');$allocation=financeAllocateNumber($agencyId,'cash_movement');
    try{return financeWithRetry(function()use($input,$registerId,$agencyId,$pre,$type,$amount,$reason,$allocation){$idem=financeAcquireIdempotency($agencyId,'cash.manual',$input['idempotency_key']??'');financeReplayIfCompleted($idem);$register=financeLockOpenRegister($agencyId,$pre['currency']);if((int)$register['id']!==$registerId)throw new DomainException(t('validation.cash_register_required'));$id=financeInsertMovement($allocation,$register,$type,$type==='manual_in'?'in':'out',$amount,'finance_idempotency',(int)$idem['id'],$reason);auditLog('cash.movement_posted','cash_movement',$id,null,['type'=>$type,'amount'=>centsToMoney($amount),'reason'=>$reason,'register_id'=>$registerId],$agencyId);financeCompleteIdempotency($idem['id'],'cash_movement',$id);return$id;});}catch(FinanceIdempotentReplay$replay){financeVoidReplayNumbers([$allocation]);return(int)$replay->result();}catch(Throwable$exception){financeVoidNumbers([$allocation],$exception);throw$exception;}
}

function closeCashRegister(array $input): bool
{
    financeRequireCutover();financeAssertPermission('cash.manage');$registerId=(int)($input['cash_register_id']??0);$pre=financePreReadAgency('cash_registers',$registerId);$agencyId=(int)$pre['agency_id'];$counted=financeMoneyCents($input['actual_balance']??'',true);$allocation=financeAllocateNumber($agencyId,'cash_movement');$used=false;
    try{$result=financeWithRetry(function()use($input,$registerId,$agencyId,$counted,$allocation,&$used){$idem=financeAcquireIdempotency($agencyId,'cash.close',$input['idempotency_key']??'');financeReplayIfCompleted($idem);$register=dbFetchOne('SELECT * FROM cash_registers WHERE id=:id AND agency_id=:agency FOR UPDATE',['id'=>$registerId,'agency'=>$agencyId]);if(!$register||$register['status']!=='open')throw new DomainException(t('validation.open_register_not_found'));$boundary=dbFetchOne('SELECT NOW(6) boundary')['boundary'];$sums=dbFetchOne("SELECT COALESCE(SUM(CASE WHEN direction='in' THEN amount ELSE 0 END),0) incoming,COALESCE(SUM(CASE WHEN direction='out' THEN amount ELSE 0 END),0) outgoing FROM cash_movements WHERE cash_register_id=:id AND status='posted' AND occurred_at<=:boundary",['id'=>$registerId,'boundary'=>$boundary]);$expected=(moneyToCents($register['opening_balance'])??0)+(moneyToCents($register['legacy_net_movement_amount'])??0)+(moneyToCents($sums['incoming'])??0)-(moneyToCents($sums['outgoing'])??0);$difference=$counted-$expected;$reason=trim((string)($input['reason']??''));if($difference!==0)$reason=financeReason($reason,'validation.cash_difference_reason');if($difference!==0){financeInsertMovement($allocation,$register,'closing_adjustment',$difference>0?'in':'out',abs($difference),'cash_register',$registerId,$reason);$used=true;}$statement=dbExecute("UPDATE cash_registers SET expected_balance=:expected,actual_balance=:actual,difference_amount=:difference,difference_reason=:reason,status='closed',closed_at=NOW(6),closing_boundary_at=:boundary,closed_by=:user,notes=:notes WHERE id=:id AND status='open'",['expected'=>centsToMoney($expected),'actual'=>centsToMoney($counted),'difference'=>centsToMoney($difference),'reason'=>$reason?:null,'boundary'=>$boundary,'user'=>currentUserId(),'notes'=>trim((string)($input['notes']??''))?:null,'id'=>$registerId]);if($statement->rowCount()!==1)throw new DomainException(t('validation.finance_stale'));auditLog('cash_register.closed','cash_register',$registerId,['status'=>'open'],['status'=>'closed','expected'=>centsToMoney($expected),'counted'=>centsToMoney($counted),'difference'=>centsToMoney($difference),'reason'=>$reason?:null,'boundary'=>$boundary],$agencyId);financeCompleteIdempotency($idem['id'],'cash_register',$registerId);return true;});if(!$used)financeVoidNumber($allocation,'No closing difference; no movement required');return$result;}catch(FinanceIdempotentReplay$replay){financeVoidReplayNumbers([$allocation]);return true;}catch(Throwable$exception){financeVoidNumbers([$allocation],$exception);throw$exception;}
}

function financeScopedPlaceholders(array $ids): array
{
    $ids=array_values(array_map('intval',$ids));if(!$ids)$ids=[0];return [$ids,implode(',',array_fill(0,count($ids),'?'))];
}
