<?php

/** Phase 5B.4 is the sole authority permitted to complete an active rental. */
final class RentalCheckinReplay extends RuntimeException
{
    private int $result;

    public function __construct(int $result)
    {
        $this->result = $result;
    }

    public function result(): int
    {
        return $this->result;
    }
}

function rentalCheckinHook(string $stage, array $context = []): void
{
    if (PHP_SAPI === 'cli'
        && defined('RENTAL_CHECKIN_TEST_HOOK')
        && RENTAL_CHECKIN_TEST_HOOK === true
        && function_exists('rentalCheckinCliTestHook')) {
        rentalCheckinCliTestHook($stage, $context);
    }
}

function rentalCheckinInteger($value, string $key): int
{
    if (!is_int($value) && (!is_string($value) || !preg_match('/^\d+$/', $value))) {
        throw new InvalidArgumentException(t($key));
    }
    $value = (int) $value;
    if ($value < 0) {
        throw new InvalidArgumentException(t($key));
    }
    return $value;
}

function rentalCheckinFuel($value): string
{
    $value = trim((string) $value);
    if (!preg_match('/^\d{1,3}(?:\.\d{1,2})?$/', $value) || (float) $value < 0 || (float) $value > 100) {
        throw new InvalidArgumentException(t('validation.checkin_fuel'));
    }
    return number_format((float) $value, 2, '.', '');
}

function rentalCheckinTime($value): DateTimeImmutable
{
    $time = validDateTimeValue($value);
    if (!$time || $time > (new DateTimeImmutable('now'))->modify('+5 minutes')) {
        throw new InvalidArgumentException(t('validation.checkin_returned_at'));
    }
    return $time;
}

function rentalCheckinStoredTime($value, string $key): DateTimeImmutable
{
    $value = (string) $value;
    foreach (['Y-m-d H:i:s.u', 'Y-m-d H:i:s'] as $format) {
        $time = DateTimeImmutable::createFromFormat('!' . $format, $value);
        if ($time && $time->format($format) === $value && (int) $time->format('Y') >= 1900) {
            return $time;
        }
    }
    throw new DomainException(t($key));
}

function rentalCheckinCondition($value): string
{
    if (!is_string($value) || !in_array($value, ['good', 'dirty', 'damaged'], true)) {
        throw new InvalidArgumentException(t('validation.checkin_condition'));
    }
    return $value;
}

function rentalCheckinComment($value): string
{
    if (!is_string($value) && $value !== null) {
        throw new InvalidArgumentException(t('validation.checkin_comment'));
    }
    $comment = trim((string) $value);
    if (mb_strlen($comment) > 1000 || strip_tags($comment) !== $comment) {
        throw new InvalidArgumentException(t('validation.checkin_comment'));
    }
    return $comment;
}

function rentalCheckinVehicleState(string $condition): string
{
    // Dirty is an observed return condition, not an automatic maintenance decision.
    return $condition === 'damaged' ? 'damaged' : 'available';
}

function rentalCheckinCompleteIdempotency(int $id, int $reservationId): void
{
    $changed = dbExecute(
        "UPDATE rental_operation_idempotency_keys
         SET status='completed',result_entity_type='rental_checkin',result_entity_id=:result,completed_at=NOW(6)
         WHERE id=:id AND status='in_progress'",
        ['result' => $reservationId, 'id' => $id]
    );
    if ($changed->rowCount() !== 1) {
        throw new DomainException(t('validation.contract_stale'));
    }
}

function rentalCheckin(array $command): int
{
    contractRequireCutover();
    enforcePermission('rental.checkin');

    $reservationId = rentalCheckinInteger($command['reservation_id'] ?? null, 'validation.checkin_not_found');
    $contractId = rentalCheckinInteger($command['contract_id'] ?? null, 'validation.checkin_not_found');
    $inspectionId = rentalCheckinInteger($command['inspection_id'] ?? null, 'validation.checkin_not_found');
    $mileage = rentalCheckinInteger($command['mileage'] ?? null, 'validation.checkin_mileage');
    $fuel = rentalCheckinFuel($command['fuel_level'] ?? null);
    $returnedAt = rentalCheckinTime($command['returned_at'] ?? null);
    $condition = rentalCheckinCondition($command['vehicle_condition'] ?? null);
    $comment = rentalCheckinComment($command['comment'] ?? null);

    $visible = contractScopedReservation($reservationId);
    if (!$visible) {
        throw new InvalidArgumentException(t('validation.checkin_not_found'));
    }
    $agency = (int) $visible['agency_id'];
    $expectedVehicle = (int) $visible['vehicle_id'];
    $expectedCustomer = (int) $visible['customer_id'];
    $actor = contractAcknowledgementActor($agency);

    try {
        return contractWithRetry(function () use ($command, $reservationId, $contractId, $inspectionId, $mileage, $fuel, $returnedAt, $condition, $comment, $agency, $expectedVehicle, $expectedCustomer, $actor) {
            $payload = [
                'agency_id' => $agency,
                'reservation_id' => $reservationId,
                'contract_id' => $contractId,
                'vehicle_id' => $expectedVehicle,
                'customer_id' => $expectedCustomer,
                'inspection_id' => $inspectionId,
                'mileage' => $mileage,
                'fuel_level' => $fuel,
                'returned_at' => $returnedAt->format('Y-m-d H:i:s'),
                'vehicle_condition' => $condition,
                'comment' => $comment,
                'actor_id' => (int) $actor['id'],
            ];
            $idem = contractAcquireIdempotency($agency, 'rental_checkin', $command['idempotency_key'] ?? '', $payload);
            if ($idem['completed']) {
                throw new RentalCheckinReplay($idem['result_id']);
            }
            rentalCheckinHook('after_idempotency');

            /* Lock order: reservation, contract, vehicle, return inspection, return photos,
             * checkout reference, current version/acknowledgements, then rental conflicts. */
            $reservation = dbFetchOne(
                'SELECT * FROM reservations WHERE id=:id AND agency_id=:agency AND archived_at IS NULL FOR UPDATE',
                ['id' => $reservationId, 'agency' => $agency]
            );
            if (!$reservation || $reservation['status'] !== 'active' || empty($reservation['vehicle_id'])
                || (int) $reservation['vehicle_id'] !== $expectedVehicle
                || (int) $reservation['customer_id'] !== $expectedCustomer) {
                throw new DomainException(t('validation.checkin_reservation'));
            }
            $contract = contractScopedRecord($contractId, true);
            if (!$contract || (int) $contract['reservation_id'] !== $reservationId
                || (int) $contract['agency_id'] !== $agency || $contract['status'] !== 'active'
                || empty($contract['activated_at']) || empty($contract['current_version_id'])
                || empty($contract['signed_at']) || $contract['completed_at'] !== null || $contract['cancelled_at'] !== null) {
                throw new DomainException(t('validation.checkin_contract'));
            }

            $vehicle = dbFetchOne(
                'SELECT * FROM vehicles WHERE id=:id AND agency_id=:agency AND archived_at IS NULL FOR UPDATE',
                ['id' => $expectedVehicle, 'agency' => $agency]
            );
            if (!$vehicle || $vehicle['status'] !== 'rented') {
                throw new DomainException(t('validation.checkin_vehicle'));
            }

            $inspection = dbFetchOne(
                'SELECT * FROM vehicle_inspections WHERE id=:id AND agency_id=:agency FOR UPDATE',
                ['id' => $inspectionId, 'agency' => $agency]
            );
            if (!$inspection || $inspection['inspection_type'] !== 'return' || $inspection['status'] !== 'draft'
                || $inspection['archived_at'] !== null
                || (int) $inspection['reservation_id'] !== $reservationId
                || (int) $inspection['contract_id'] !== $contractId
                || (int) $inspection['vehicle_id'] !== (int) $vehicle['id']
                || (int) $inspection['customer_id'] !== $expectedCustomer) {
                throw new DomainException(t('validation.checkin_inspection'));
            }
            rentalCheckinStoredTime($inspection['inspected_at'] ?? null, 'validation.checkin_inspection');

            if ((int) $inspection['mileage'] !== $mileage
                || number_format((float) $inspection['fuel_level'], 2, '.', '') !== $fuel) {
                throw new DomainException(t('validation.checkin_values'));
            }

            $photos = inspectionPhotoVerifyActiveBundle($inspectionId, $agency, 'validation.checkin_photo_bundle');

            $checkout = dbFetchOne(
                "SELECT id,mileage,completed_at FROM vehicle_inspections
                 WHERE agency_id=:agency AND reservation_id=:reservation AND contract_id=:contract
                   AND vehicle_id=:vehicle AND customer_id=:customer AND inspection_type='checkout'
                   AND status='completed' AND archived_at IS NULL FOR UPDATE",
                ['agency' => $agency, 'reservation' => $reservationId, 'contract' => $contractId, 'vehicle' => $expectedVehicle, 'customer' => $expectedCustomer]
            );
            $version = dbFetchOne(
                'SELECT id FROM contract_versions WHERE id=:id AND contract_id=:contract AND agency_id=:agency FOR UPDATE',
                ['id' => $contract['current_version_id'], 'contract' => $contractId, 'agency' => $agency]
            );
            $acks = dbFetchAll(
                'SELECT acknowledgement_type,contract_version_id FROM contract_acknowledgements WHERE contract_id=:contract AND contract_version_id=:version AND agency_id=:agency ORDER BY acknowledgement_type,id FOR UPDATE',
                ['contract' => $contractId, 'version' => $contract['current_version_id'], 'agency' => $agency]
            );
            if (!$checkout || empty($checkout['completed_at']) || !$version || count($acks) !== 2
                || !contractRequiredAcknowledgementsComplete($acks)
                || array_filter($acks, static fn($ack) => (int) $ack['contract_version_id'] !== (int) $version['id'])) {
                throw new DomainException(t('validation.checkin_contract'));
            }

            if (!dbFetchOne('SELECT id FROM customers WHERE id=:id AND agency_id=:agency', ['id' => $expectedCustomer, 'agency' => $agency])) {
                throw new DomainException(t('validation.checkin_reservation'));
            }
            $activeRentals = dbFetchAll(
                "SELECT id FROM reservations WHERE vehicle_id=:vehicle AND status='active' ORDER BY id FOR UPDATE",
                ['vehicle' => $expectedVehicle]
            );
            if (array_map('intval', array_column($activeRentals, 'id')) !== [$reservationId]) {
                throw new DomainException(t('validation.checkin_vehicle'));
            }

            $activatedAt = rentalCheckinStoredTime($contract['activated_at'], 'validation.checkin_contract');
            $checkedOutAt = rentalCheckinStoredTime($checkout['completed_at'], 'validation.checkin_contract');
            if ($returnedAt < $activatedAt || $returnedAt < $checkedOutAt) {
                throw new DomainException(t('validation.checkin_returned_at'));
            }
            if ($mileage < (int) $vehicle['current_mileage'] || $mileage < (int) $checkout['mileage']) {
                throw new DomainException(t('validation.checkin_values'));
            }
            $vehicleState = rentalCheckinVehicleState($condition);
            $cleanliness = $condition === 'dirty' ? 'dirty' : ($condition === 'good' ? 'clean' : 'acceptable');
            $damageNotes = $condition === 'damaged'
                ? ($comment !== '' ? $comment : 'Damage condition recorded at rental check-in.')
                : null;
            $at = $returnedAt->format('Y-m-d H:i:s.u');
            $updates = [
                ['reservation_update', "UPDATE reservations SET status='completed',updated_by=:actor WHERE id=:id AND agency_id=:agency AND status='active'", ['actor' => $actor['id'], 'id' => $reservationId, 'agency' => $agency]],
                ['contract_update', "UPDATE rental_contracts SET status='completed',completed_at=:at,updated_by=:actor WHERE id=:id AND agency_id=:agency AND status='active' AND activated_at IS NOT NULL AND completed_at IS NULL", ['at' => $at, 'actor' => $actor['id'], 'id' => $contractId, 'agency' => $agency]],
                ['vehicle_update', "UPDATE vehicles SET status=:status,current_mileage=:mileage,updated_by=:actor WHERE id=:id AND agency_id=:agency AND status='rented' AND archived_at IS NULL", ['status' => $vehicleState, 'mileage' => $mileage, 'actor' => $actor['id'], 'id' => $expectedVehicle, 'agency' => $agency]],
                ['inspection_update', "UPDATE vehicle_inspections SET status='completed',completed_at=:at,post_return_vehicle_state=:vehicle_state,damage_notes=:damage_notes,cleanliness=:cleanliness,notes=CASE WHEN :comment='' THEN notes ELSE :comment2 END WHERE id=:id AND agency_id=:agency AND status='draft' AND archived_at IS NULL", ['at' => $at, 'vehicle_state' => $vehicleState, 'damage_notes' => $damageNotes, 'cleanliness' => $cleanliness, 'comment' => $comment, 'comment2' => $comment, 'id' => $inspectionId, 'agency' => $agency]],
            ];
            foreach ($updates as [$stage, $sql, $params]) {
                $changed = dbExecute($sql, $params);
                if ($changed->rowCount() !== 1) {
                    throw new DomainException(t('validation.contract_stale'));
                }
                rentalCheckinHook('after_' . $stage);
            }

            $history = [
                'source' => 'rental_checkin',
                'contract_id' => $contractId,
                'inspection_id' => $inspectionId,
                'mileage' => $mileage,
                'fuel_level' => $fuel,
                'returned_at' => $returnedAt->format('Y-m-d H:i:s'),
                'vehicle_condition' => $condition,
                'actor_id' => (int) $actor['id'],
            ];
            rentalCheckinHook('before_reservation_history');
            dbExecute(
                "INSERT INTO reservation_status_history(reservation_id,from_status,to_status,reason,metadata_json,changed_by)
                 VALUES(:reservation,'active','completed',NULL,:meta,:actor)",
                ['reservation' => $reservationId, 'meta' => json_encode($history, JSON_UNESCAPED_SLASHES), 'actor' => $actor['id']]
            );
            rentalCheckinHook('after_reservation_history');
            dbExecute(
                "INSERT INTO contract_status_history(agency_id,contract_id,reservation_id,from_status,to_status,changed_by,occurred_at,metadata_json)
                 VALUES(:agency,:contract,:reservation,'active','completed',:actor,:at,:meta)",
                ['agency' => $agency, 'contract' => $contractId, 'reservation' => $reservationId, 'actor' => $actor['id'], 'at' => $at, 'meta' => json_encode($history, JSON_UNESCAPED_SLASHES)]
            );
            rentalCheckinHook('after_contract_history');
            auditLog(
                'rental.checkin',
                'reservation',
                $reservationId,
                ['reservation_status' => 'active', 'contract_status' => 'active', 'vehicle_status' => 'rented', 'inspection_status' => 'draft', 'vehicle_mileage' => (int) $vehicle['current_mileage'], 'return_condition' => null, 'returned_at' => null],
                ['reservation_status' => 'completed', 'contract_status' => 'completed', 'vehicle_status' => $vehicleState, 'inspection_status' => 'completed', 'vehicle_mileage' => $mileage, 'return_condition' => $condition, 'returned_at' => $returnedAt->format('Y-m-d H:i:s'), 'contract_id' => $contractId, 'inspection_id' => $inspectionId],
                $agency
            );
            rentalCheckinHook('after_audit');
            rentalCheckinHook('before_idempotency_completion');
            rentalCheckinCompleteIdempotency((int) $idem['id'], $reservationId);
            rentalCheckinHook('before_commit', ['photo_count' => count($photos)]);
            return $reservationId;
        });
    } catch (RentalCheckinReplay $replay) {
        return $replay->result();
    }
}
