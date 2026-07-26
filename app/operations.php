<?php

/** Narrow compatibility wrapper. New callers must supply an idempotency key. */
function createContractFromReservation($reservationId, $languageCode = 'fr', $idempotencyKey = null)
{
    return contractCreateFromReservation([
        'reservation_id'=>(int)$reservationId,
        'idempotency_key'=>$idempotencyKey?:contractIdempotencyToken(),
    ]);
}

/** Amendments are deliberately unavailable until Phase 5B.3. */
function amendContract($contractId, $reason, $languageCode = 'fr')
{
    throw new DomainException(t('validation.contract_amendment_unavailable'));
}

function recordPayment(array $input)
{
    return recordSettledPayment($input);
}

function createInvoiceFromReservation($reservationId, $idempotencyKey = null)
{
    return createAndIssueInvoiceFromReservation([
        'reservation_id'=>(int)$reservationId,
        'language_code'=>language(),
        'idempotency_key'=>$idempotencyKey ?: financeIdempotencyToken(),
    ]);
}

function updateDepositStatus($depositId,$newStatus,$retainedAmount='0.00',$reason=null)
{
    $deposit=dbFetchOne('SELECT * FROM deposits WHERE id=:id',['id'=>(int)$depositId]);if(!$deposit)throw new InvalidArgumentException(t('validation.finance_not_found'));
    $eventMap=['received'=>'received','held'=>'held','partially_retained'=>'partially_retained','fully_retained'=>'fully_retained','returned'=>'returned'];$event=$eventMap[$newStatus]??null;if(!$event)throw new DomainException(t('validation.deposit_event'));
    $amount=$event==='held'?'0.00':($event==='received'?centsToMoney(max(0,(moneyToCents($deposit['amount'])??0)-(moneyToCents($deposit['received_amount'])??0))):($event==='returned'?centsToMoney(max(0,(moneyToCents($deposit['received_amount'])??0)-(moneyToCents($deposit['retained_amount'])??0)-(moneyToCents($deposit['returned_amount'])??0))):centsToMoney(max(0,(moneyToCents($retainedAmount)??0)-(moneyToCents($deposit['retained_amount'])??0)))));
    return appendDepositEvent(['deposit_id'=>(int)$depositId,'event_type'=>$event,'amount'=>$amount,'method'=>'cash','reason'=>$reason,'idempotency_key'=>financeIdempotencyToken()]);
}

function createInspection(array $input)
{
    throw new DomainException(t('validation.inspection_cutover_read_only'));
}
