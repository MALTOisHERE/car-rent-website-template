<?php

function reservationStatuses()
{
    return ['draft', 'quote', 'pending', 'confirmed', 'deposit_paid', 'ready', 'active', 'completed', 'cancelled', 'no_show', 'expired'];
}

function vehicleStatuses()
{
    return ['available', 'reserved', 'rented', 'cleaning', 'maintenance', 'damaged', 'blocked', 'sold', 'retired'];
}

function contractStatuses()
{
    return ['draft', 'generated', 'signed', 'active', 'completed', 'cancelled', 'amended'];
}

function currentAgencyIds()
{
    if (currentUserRole() === ROLE_OWNER) {
        return array_map('intval', array_column(dbFetchAll('SELECT id FROM agencies WHERE archived_at IS NULL'), 'id'));
    }
    return array_values(array_map('intval', $_SESSION['agency_ids'] ?? []));
}

function requireAgencyAccess($agencyId)
{
    if (currentUserRole() !== ROLE_OWNER && !in_array((int) $agencyId, currentAgencyIds(), true)) {
        http_response_code(403);
        exit('You are not authorized to access this agency.');
    }
}

function generateBusinessReference($prefix)
{
    return strtoupper($prefix) . '-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
}

function calculateRentalPrice(array $input)
{
    $pickup = $input['pickup_at'];
    $return = $input['return_at'];
    $seconds = $return->getTimestamp() - $pickup->getTimestamp();
    if ($seconds <= 0) {
        throw new InvalidArgumentException('Return time must be after pickup time.');
    }
    $days = max(1, (int) ceil($seconds / 86400));
    $dailyPrice = moneyToCents($input['daily_price']);
    $optionsTotal = moneyToCents($input['options_total'] ?? '0') ?? 0;
    $feesTotal = moneyToCents($input['fees_total'] ?? '0') ?? 0;
    if($dailyPrice===null||$dailyPrice<0||$optionsTotal<0||$feesTotal<0)throw new InvalidArgumentException('Pricing values must be valid non-negative monetary amounts.');
    $base = $dailyPrice * $days;
    $ruleAdjustment = 0;
    $appliedRules = [];
    if (!empty($input['agency_id']) && function_exists('dbFetchAll') && tableExists('pricing_rules')) {
        $rules = dbFetchAll(
            "SELECT * FROM pricing_rules WHERE agency_id=:agency AND status='active' AND archived_at IS NULL
             AND (category_id IS NULL OR category_id=:category)
             AND (starts_at IS NULL OR starts_at<=:pickup) AND (ends_at IS NULL OR ends_at>=:return_at)
             ORDER BY priority,id",
            ['agency'=>$input['agency_id'],'category'=>$input['category_id']??0,'pickup'=>$pickup->format('Y-m-d H:i:s'),'return_at'=>$return->format('Y-m-d H:i:s')]
        );
        foreach ($rules as $rule) {
            $conditions=json_decode($rule['conditions_json']??'{}',true)?:[];
            $minimum=(int)($conditions['minimum_days']??0);$maximum=(int)($conditions['maximum_days']??0);
            if(($minimum&&$days<$minimum)||($maximum&&$days>$maximum))continue;
            $ruleBasisPoints=percentageToBasisPoints($rule['adjustment_value'])??0;
            $adjustment=$rule['adjustment_type']==='percentage'?percentageOfCents($base,$ruleBasisPoints):(moneyToCents($rule['adjustment_value'])??0);
            $discountRuleTypes=['duration','business','partner','loyalty'];
            $effect=$conditions['effect']??(in_array($rule['rule_type'],$discountRuleTypes,true)?'discount':'surcharge');
            if($effect==='discount')$adjustment*=-1;
            $ruleAdjustment+=$adjustment;$appliedRules[]=['id'=>(int)$rule['id'],'name'=>$rule['name'],'amount'=>centsToMoney($adjustment)];
        }
    }
    $discountPercent = percentageToBasisPoints($input['discount_percent'] ?? '0') ?? 0;
    $discountAmount = percentageOfCents($base + $ruleAdjustment + $optionsTotal + $feesTotal,$discountPercent);
    $taxRate = percentageToBasisPoints($input['tax_rate'] ?? '0') ?? 0;
    $taxable = max(0,$base + $ruleAdjustment + $optionsTotal + $feesTotal - $discountAmount);
    $taxAmount = percentageOfCents($taxable,$taxRate);
    $total = $taxable + $taxAmount;

    return [
        'days' => $days,
        'daily_price' => centsToMoney($dailyPrice),
        'base_total' => centsToMoney($base),
        'options_total' => centsToMoney($optionsTotal),
        'fees_total' => centsToMoney($feesTotal),
        'pricing_rule_adjustment' => centsToMoney($ruleAdjustment),
        'applied_rules' => $appliedRules,
        'discount_percent' => centsToMoney($discountPercent),
        'discount_amount' => centsToMoney($discountAmount),
        'tax_rate' => centsToMoney($taxRate),
        'tax_amount' => centsToMoney($taxAmount),
        'total' => centsToMoney($total),
    ];
}

function vehicleHasConflict($vehicleId, DateTimeImmutable $pickup, DateTimeImmutable $return, $excludeReservationId = null)
{
    $parameters = [
        'vehicle_id' => $vehicleId,
        'return_at' => $return->format('Y-m-d H:i:s'),
        'pickup_at' => $pickup->format('Y-m-d H:i:s'),
    ];
    $excludeSql = '';
    if ($excludeReservationId !== null) {
        $excludeSql = ' AND id <> :exclude_id';
        $parameters['exclude_id'] = $excludeReservationId;
    }
    $lockingRead = db()->inTransaction() ? ' FOR UPDATE' : '';
    $row = dbFetchOne(
        "SELECT id FROM reservations WHERE vehicle_id = :vehicle_id
         AND status IN ('pending','confirmed','deposit_paid','ready','active')
         AND pickup_at < :return_at AND return_at > :pickup_at" . $excludeSql . ' LIMIT 1' . $lockingRead,
        $parameters
    );
    if ($row) {
        return true;
    }

    return (bool) dbFetchOne(
        "SELECT id FROM maintenance_records WHERE vehicle_id = :vehicle_id
         AND status IN ('scheduled','in_progress')
         AND COALESCE(entry_at, CONCAT(scheduled_date, ' 00:00:00')) < :return_at
         AND COALESCE(actual_exit_at, estimated_exit_at, '9999-12-31 23:59:59') > :pickup_at LIMIT 1" . $lockingRead,
        [
            'vehicle_id' => $vehicleId,
            'return_at' => $return->format('Y-m-d H:i:s'),
            'pickup_at' => $pickup->format('Y-m-d H:i:s'),
        ]
    );
}

function createReservation(array $input)
{
    if (function_exists('createReservationWorkspace')) return createReservationWorkspace($input);
    return withTransaction(function () use ($input) {
        $vehicle = dbFetchOne('SELECT * FROM vehicles WHERE id = :id AND archived_at IS NULL FOR UPDATE', ['id' => $input['vehicle_id']]);
        if (!$vehicle || in_array($vehicle['status'], ['maintenance', 'damaged', 'blocked', 'sold', 'retired'], true)) {
            throw new DomainException('The selected vehicle is not available.');
        }
        requireAgencyAccess($input['agency_id']);
        $pickup = validDateTimeValue($input['pickup_at']);
        $return = validDateTimeValue($input['return_at']);
        if (!$pickup || !$return || $return <= $pickup) {
            throw new InvalidArgumentException('A valid pickup and return period is required.');
        }
        if (vehicleHasConflict($vehicle['id'], $pickup, $return)) {
            auditLog('reservation.conflict', 'vehicle', $vehicle['id'], null, ['pickup_at' => $input['pickup_at'], 'return_at' => $input['return_at']], $input['agency_id']);
            throw new DomainException('The selected vehicle is no longer available for this period.');
        }

        $pricing = calculateRentalPrice([
            'pickup_at' => $pickup, 'return_at' => $return, 'daily_price' => $vehicle['base_daily_price'], 'agency_id'=>$input['agency_id'], 'category_id'=>$vehicle['category_id'],
            'options_total' => $input['options_total'] ?? 0, 'fees_total' => $input['fees_total'] ?? 0,
            'discount_percent' => $input['discount_percent'] ?? 0, 'tax_rate' => $input['tax_rate'] ?? 0,
        ]);
        $status = validateChoice($input['status'] ?? 'pending', ['draft', 'quote', 'pending', 'confirmed'], 'pending');
        $expiresAt = $status === 'pending' ? date('Y-m-d H:i:s', time() + appConfig('pending_reservation_minutes') * 60) : null;
        $reference = generateBusinessReference($status === 'quote' ? 'QTE' : 'RES');
        dbExecute(
            'INSERT INTO reservations (reference, agency_id, return_agency_id, customer_id, vehicle_id, category_id,
             status, source, pickup_at, return_at, pickup_location, return_location, delivery_location,
             pending_expires_at, currency, daily_price, rental_days, options_total, fees_total,
             discount_amount, discount_percent, discount_reason, tax_amount, total_amount, remaining_amount,
             deposit_amount, pricing_snapshot_json, internal_notes, created_by, updated_by)
             VALUES (:reference, :agency_id, :return_agency_id, :customer_id, :vehicle_id, :category_id,
             :status, :source, :pickup_at, :return_at, :pickup_location, :return_location, :delivery_location,
             :pending_expires_at, :currency, :daily_price, :rental_days, :options_total, :fees_total,
             :discount_amount, :discount_percent, :discount_reason, :tax_amount, :total_amount, :remaining_amount,
             :deposit_amount, :pricing_snapshot_json, :internal_notes, :created_by, :updated_by)',
            [
                'reference' => $reference, 'agency_id' => $input['agency_id'],
                'return_agency_id' => $input['return_agency_id'] ?? $input['agency_id'], 'customer_id' => $input['customer_id'],
                'vehicle_id' => $vehicle['id'], 'category_id' => $vehicle['category_id'], 'status' => $status,
                'source' => validateChoice($input['source'] ?? 'agency', ['phone','WhatsApp','website','Instagram','Facebook','agency','hotel','partner','marketplace','other'], 'agency'),
                'pickup_at' => $pickup->format('Y-m-d H:i:s'), 'return_at' => $return->format('Y-m-d H:i:s'),
                'pickup_location' => trim((string) ($input['pickup_location'] ?? '')), 'return_location' => trim((string) ($input['return_location'] ?? '')),
                'delivery_location' => trim((string) ($input['delivery_location'] ?? '')), 'pending_expires_at' => $expiresAt,
                'currency' => appConfig('currency'), 'daily_price' => $pricing['daily_price'], 'rental_days' => $pricing['days'],
                'options_total' => $pricing['options_total'], 'fees_total' => $pricing['fees_total'],
                'discount_amount' => $pricing['discount_amount'], 'discount_percent' => $pricing['discount_percent'],
                'discount_reason' => trim((string) ($input['discount_reason'] ?? '')), 'tax_amount' => $pricing['tax_amount'],
                'total_amount' => $pricing['total'], 'remaining_amount' => $pricing['total'],
                'deposit_amount' => positiveMoney($input['deposit_amount'] ?? $vehicle['recommended_deposit']) ?? '0.00',
                'pricing_snapshot_json' => json_encode($pricing, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'internal_notes' => trim((string) ($input['internal_notes'] ?? '')), 'created_by' => currentUserId(), 'updated_by' => currentUserId(),
            ]
        );
        $reservationId = (int) db()->lastInsertId();
        if($status==='confirmed')dbExecute("UPDATE vehicles SET status='reserved',updated_by=:user WHERE id=:id AND status='available'",['user'=>currentUserId(),'id'=>$vehicle['id']]);
        dbExecute('INSERT INTO reservation_status_history (reservation_id, from_status, to_status, changed_by) VALUES (:id, NULL, :status, :user_id)', ['id' => $reservationId, 'status' => $status, 'user_id' => currentUserId()]);
        auditLog('reservation.created', 'reservation', $reservationId, null, ['reference' => $reference, 'status' => $status, 'total' => $pricing['total']], $input['agency_id']);
        return $reservationId;
    });
}

function reservationTransitions()
{
    return [
        'draft' => ['quote', 'pending', 'cancelled'], 'quote' => ['pending', 'expired', 'cancelled'],
        'pending' => ['confirmed', 'cancelled', 'expired'], 'confirmed' => ['deposit_paid', 'ready', 'cancelled', 'no_show'],
        'deposit_paid' => ['ready', 'cancelled', 'no_show'], 'ready' => ['active', 'cancelled', 'no_show'],
        'active' => ['completed'], 'completed' => [], 'cancelled' => [], 'no_show' => [], 'expired' => [],
    ];
}

function transitionReservation($reservationId, $newStatus, $reason = null)
{
    if (function_exists('transitionReservationWorkspace')) return transitionReservationWorkspace($reservationId,$newStatus,$reason);
    return withTransaction(function () use ($reservationId, $newStatus, $reason) {
        $reservation = dbFetchOne('SELECT * FROM reservations WHERE id = :id FOR UPDATE', ['id' => $reservationId]);
        if (!$reservation || !in_array($newStatus, reservationTransitions()[$reservation['status']] ?? [], true)) {
            throw new DomainException('This reservation status transition is not permitted.');
        }
        requireAgencyAccess($reservation['agency_id']);
        if (in_array($newStatus, ['confirmed','deposit_paid','ready','active'], true)) {
            $vehicle = dbFetchOne('SELECT id FROM vehicles WHERE id = :id FOR UPDATE', ['id' => $reservation['vehicle_id']]);
            $pickup = new DateTimeImmutable($reservation['pickup_at']);
            $return = new DateTimeImmutable($reservation['return_at']);
            if (!$vehicle || vehicleHasConflict($vehicle['id'], $pickup, $return, $reservation['id'])) {
                throw new DomainException('The vehicle has a conflicting booking and cannot be confirmed.');
            }
        }
        dbExecute('UPDATE reservations SET status = :status, cancellation_reason = :reason, updated_by = :user_id WHERE id = :id', ['status' => $newStatus, 'reason' => $newStatus === 'cancelled' ? $reason : null, 'user_id' => currentUserId(), 'id' => $reservationId]);
        if(in_array($newStatus,['confirmed','deposit_paid','ready'],true))dbExecute("UPDATE vehicles SET status='reserved',updated_by=:user WHERE id=:id AND status='available'",['user'=>currentUserId(),'id'=>$reservation['vehicle_id']]);
        elseif($newStatus==='active')dbExecute("UPDATE vehicles SET status='rented',updated_by=:user WHERE id=:id",['user'=>currentUserId(),'id'=>$reservation['vehicle_id']]);
        elseif($newStatus==='completed')dbExecute("UPDATE vehicles SET status='cleaning',updated_by=:user WHERE id=:id",['user'=>currentUserId(),'id'=>$reservation['vehicle_id']]);
        elseif(in_array($newStatus,['cancelled','no_show','expired'],true))dbExecute("UPDATE vehicles SET status='available',updated_by=:user WHERE id=:id AND status='reserved'",['user'=>currentUserId(),'id'=>$reservation['vehicle_id']]);
        dbExecute('INSERT INTO reservation_status_history (reservation_id, from_status, to_status, reason, changed_by) VALUES (:id, :from_status, :to_status, :reason, :user_id)', ['id' => $reservationId, 'from_status' => $reservation['status'], 'to_status' => $newStatus, 'reason' => $reason, 'user_id' => currentUserId()]);
        auditLog('reservation.status_changed', 'reservation', $reservationId, ['status' => $reservation['status']], ['status' => $newStatus, 'reason' => $reason], $reservation['agency_id']);
        return true;
    });
}

function expirePendingReservations()
{
    $rows = dbFetchAll("SELECT id, status FROM reservations WHERE status = 'pending' AND pending_expires_at < NOW() FOR UPDATE");
    foreach ($rows as $row) {
        dbExecute("UPDATE reservations SET status = 'expired', updated_at = NOW() WHERE id = :id AND status = 'pending'", ['id' => $row['id']]);
        dbExecute("INSERT INTO reservation_status_history (reservation_id, from_status, to_status, reason) VALUES (:id, 'pending', 'expired', 'Pending hold expired')", ['id' => $row['id']]);
    }
    return count($rows);
}

function updateReservationAllocation($reservationId, array $input)
{
    if (function_exists('updateReservationAllocationWorkspace')) return updateReservationAllocationWorkspace($reservationId,$input);
    return withTransaction(function()use($reservationId,$input){$reservation=dbFetchOne('SELECT * FROM reservations WHERE id=:id FOR UPDATE',['id'=>$reservationId]);if(!$reservation||in_array($reservation['status'],['completed','cancelled','no_show','expired'],true))throw new DomainException('This reservation can no longer be edited.');requireAgencyAccess($reservation['agency_id']);$vehicleId=(int)($input['vehicle_id']??$reservation['vehicle_id']);$vehicle=dbFetchOne('SELECT * FROM vehicles WHERE id=:id AND archived_at IS NULL FOR UPDATE',['id'=>$vehicleId]);if(!$vehicle||in_array($vehicle['status'],['maintenance','damaged','blocked','sold','retired'],true))throw new DomainException('The selected replacement vehicle is unavailable.');$pickup=validDateTimeValue($input['pickup_at']??'');$return=validDateTimeValue($input['return_at']??'');if(!$pickup||!$return||$return<=$pickup)throw new InvalidArgumentException('A valid pickup and return period is required.');if(vehicleHasConflict($vehicleId,$pickup,$return,$reservationId)){auditLog('reservation.edit_conflict','reservation',$reservationId,null,['vehicle_id'=>$vehicleId,'pickup_at'=>$input['pickup_at'],'return_at'=>$input['return_at']],$reservation['agency_id']);throw new DomainException('The requested allocation conflicts with another booking or maintenance period.');}$dailyPrice=$vehicleId===(int)$reservation['vehicle_id']?$reservation['daily_price']:$vehicle['base_daily_price'];$pricing=calculateRentalPrice(['pickup_at'=>$pickup,'return_at'=>$return,'daily_price'=>$dailyPrice,'agency_id'=>$reservation['agency_id'],'category_id'=>$vehicle['category_id'],'options_total'=>$reservation['options_total'],'fees_total'=>$reservation['fees_total'],'discount_percent'=>$reservation['discount_percent'],'tax_rate'=>0]);dbExecute('UPDATE reservations SET vehicle_id=:vehicle,category_id=:category,pickup_at=:pickup,return_at=:return_at,daily_price=:daily,rental_days=:days,discount_amount=:discount,tax_amount=:tax,total_amount=:total,remaining_amount=GREATEST(0,:total2-advance_amount),pricing_snapshot_json=:snapshot,updated_by=:user WHERE id=:id',['vehicle'=>$vehicleId,'category'=>$vehicle['category_id'],'pickup'=>$pickup->format('Y-m-d H:i:s'),'return_at'=>$return->format('Y-m-d H:i:s'),'daily'=>$pricing['daily_price'],'days'=>$pricing['days'],'discount'=>$pricing['discount_amount'],'tax'=>$pricing['tax_amount'],'total'=>$pricing['total'],'total2'=>$pricing['total'],'snapshot'=>json_encode($pricing,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'user'=>currentUserId(),'id'=>$reservationId]);if($vehicleId!==(int)$reservation['vehicle_id']&&$reservation['status']==='active'){dbExecute("UPDATE vehicles SET status='cleaning',updated_by=:user WHERE id=:id",['user'=>currentUserId(),'id'=>$reservation['vehicle_id']]);dbExecute("UPDATE vehicles SET status='rented',updated_by=:user WHERE id=:id",['user'=>currentUserId(),'id'=>$vehicleId]);}auditLog('reservation.allocation_updated','reservation',$reservationId,['vehicle_id'=>$reservation['vehicle_id'],'pickup_at'=>$reservation['pickup_at'],'return_at'=>$reservation['return_at']],['vehicle_id'=>$vehicleId,'pickup_at'=>$pickup->format('Y-m-d H:i:s'),'return_at'=>$return->format('Y-m-d H:i:s'),'total'=>$pricing['total']],$reservation['agency_id']);return true;});
}
