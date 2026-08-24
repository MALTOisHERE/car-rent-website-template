<?php

function vehicleDetailTabs()
{
    return ['overview','profile','media','reservations','maintenance','inspections','documents','incidents','finance','history'];
}

function vehicleDetailTab($value)
{
    return validateChoice((string) $value, vehicleDetailTabs(), 'overview');
}

function vehicleFinancingTypes()
{
    return ['owned','loan','lease'];
}

function vehicleFinancingTypeForDisplay($value)
{
    return in_array((string) $value, vehicleFinancingTypes(), true) ? (string) $value : 'owned';
}

function vehicleRecord($vehicleId, $forUpdate = false, $includeArchived = false)
{
    $sql = 'SELECT v.*, c.name AS category_name, a.name AS agency_name
            FROM vehicles v
            JOIN vehicle_categories c ON c.id = v.category_id
            JOIN agencies a ON a.id = v.agency_id
            WHERE v.id = :id';
    if (!$includeArchived) $sql .= ' AND v.archived_at IS NULL';
    if ($forUpdate) $sql .= ' FOR UPDATE';
    $vehicle = dbFetchOne($sql, ['id'=>(int) $vehicleId]);
    if (!$vehicle) throw new InvalidArgumentException(t('validation.vehicle_not_found'));
    requireAgencyAccess((int) $vehicle['agency_id']);
    return $vehicle;
}

function vehicleProfileInteger($value, $minimum, $maximum, $field, $nullable = true)
{
    $value = trim((string) $value);
    if ($value === '' && $nullable) return null;
    if (!ctype_digit($value) || (int) $value < $minimum || (int) $value > $maximum) {
        throw new InvalidArgumentException(t('validation.vehicle_numeric_range', ['field'=>$field]));
    }
    return (int) $value;
}

function vehicleProfileMoney($value, $field, $nullable = true, $strictlyPositive = false)
{
    $value = trim((string) $value);
    if ($value === '' && $nullable) return null;
    $money = positiveMoney($value);
    if ($money === null || ($strictlyPositive && moneyToCents($money) <= 0)) {
        throw new InvalidArgumentException(t('validation.vehicle_money', ['field'=>$field]));
    }
    return $money;
}

function validatedVehicleProfile(array $input, array $current)
{
    $registration = strtoupper(trim((string) ($input['registration_number'] ?? '')));
    $vin = strtoupper(trim((string) ($input['vin'] ?? '')));
    $brand = trim((string) ($input['brand'] ?? ''));
    $model = trim((string) ($input['model'] ?? ''));
    if ($registration === '' || $brand === '' || $model === '') {
        throw new InvalidArgumentException(t('validation.vehicle_required_fields'));
    }
    if (strlen($registration) > 40 || strlen($vin) > 80 || strlen($brand) > 100 || strlen($model) > 100) {
        throw new InvalidArgumentException(t('validation.vehicle_text_length'));
    }
    $categoryId = vehicleProfileInteger($input['category_id'] ?? '', 1, PHP_INT_MAX, t('field.category'), false);
    $year = vehicleProfileInteger($input['model_year'] ?? '', 1900, (int) date('Y') + 1, t('field.year'));
    $seats = vehicleProfileInteger($input['seats'] ?? '', 1, 99, t('field.seats'));
    $doors = vehicleProfileInteger($input['doors'] ?? '', 1, 10, t('field.doors'));
    $luggage = vehicleProfileInteger($input['luggage_capacity'] ?? '', 0, 99, t('field.luggage'));
    $mileage = vehicleProfileInteger($input['current_mileage'] ?? '', 0, 20000000, t('field.mileage'), false);
    $allowance = vehicleProfileInteger($input['mileage_allowance'] ?? '', 0, 20000000, t('field.mileage_allowance'));
    $purchaseDate = trim((string) ($input['purchase_date'] ?? ''));
    if ($purchaseDate !== '') {
        $date = validDateValue($purchaseDate);
        if (!$date || $date > new DateTimeImmutable('today')) throw new InvalidArgumentException(t('validation.purchase_date'));
    } else {
        $purchaseDate = null;
    }
    $financing = trim((string) ($input['financing_type'] ?? ''));
    if (!in_array($financing, vehicleFinancingTypes(), true)) {
        throw new InvalidArgumentException(t('validation.financing_type'));
    }
    $monthly = vehicleProfileMoney($input['monthly_finance_amount'] ?? '', t('field.monthly_finance'), true);
    if (in_array($financing, ['loan','lease'], true) && ($monthly === null || moneyToCents($monthly) <= 0)) {
        throw new InvalidArgumentException(t('validation.monthly_finance'));
    }
    if ($financing === 'owned') $monthly = null;
    if ($mileage < (int) $current['current_mileage'] && trim((string) ($input['mileage_correction_reason'] ?? '')) === '') {
        throw new InvalidArgumentException(t('validation.mileage_correction_reason'));
    }
    return [
        'category_id'=>$categoryId,
        'registration_number'=>$registration,
        'vin'=>$vin !== '' ? $vin : null,
        'brand'=>$brand,
        'model'=>$model,
        'version'=>trim((string) ($input['version'] ?? '')) ?: null,
        'model_year'=>$year,
        'colour'=>trim((string) ($input['colour'] ?? '')) ?: null,
        'fuel'=>validateChoice((string) ($input['fuel'] ?? ''), ['petrol','diesel','hybrid','electric','other'], null),
        'transmission'=>validateChoice((string) ($input['transmission'] ?? ''), ['manual','automatic'], 'manual'),
        'seats'=>$seats,
        'doors'=>$doors,
        'luggage_capacity'=>$luggage,
        'current_mileage'=>$mileage,
        'purchase_date'=>$purchaseDate,
        'purchase_price'=>vehicleProfileMoney($input['purchase_price'] ?? '', t('field.purchase_price'), true),
        'financing_type'=>$financing,
        'monthly_finance_amount'=>$monthly,
        'base_daily_price'=>vehicleProfileMoney($input['base_daily_price'] ?? '', t('field.daily_price'), false, true),
        'recommended_deposit'=>vehicleProfileMoney($input['recommended_deposit'] ?? '0', t('field.deposit'), false),
        'mileage_allowance'=>$allowance,
        '_mileage_correction_reason'=>trim((string) ($input['mileage_correction_reason'] ?? '')),
    ];
}

function updateVehicleProfile($vehicleId, array $input)
{
    return withTransaction(function () use ($vehicleId, $input) {
        $vehicle = vehicleRecord($vehicleId, true);
        if (!hash_equals((string) $vehicle['updated_at'], (string) ($input['updated_at'] ?? ''))) {
            throw new DomainException(t('validation.stale_vehicle'));
        }
        $data = validatedVehicleProfile($input, $vehicle);
        $category = dbFetchOne('SELECT id FROM vehicle_categories WHERE id=:id AND (agency_id=:agency OR agency_id IS NULL) AND archived_at IS NULL', ['id'=>$data['category_id'],'agency'=>$vehicle['agency_id']]);
        if (!$category) throw new InvalidArgumentException(t('validation.valid_vehicle_category'));
        $duplicate = dbFetchOne('SELECT id FROM vehicles WHERE id<>:id AND (registration_number=:registration OR (:vin_present IS NOT NULL AND vin=:vin)) LIMIT 1', ['id'=>$vehicle['id'],'registration'=>$data['registration_number'],'vin_present'=>$data['vin'],'vin'=>$data['vin']]);
        if ($duplicate) throw new DomainException(t('validation.duplicate_vehicle_identifier'));
        $reason = $data['_mileage_correction_reason'];
        unset($data['_mileage_correction_reason']);
        $changes = [];
        foreach ($data as $field=>$value) {
            $old = $vehicle[$field] === null ? null : (string) $vehicle[$field];
            $new = $value === null ? null : (string) $value;
            if ($old !== $new) $changes[$field] = ['from'=>$old,'to'=>$new];
        }
        if (!$changes) return [];
        dbExecute('UPDATE vehicles SET category_id=:category_id,registration_number=:registration_number,vin=:vin,brand=:brand,model=:model,version=:version,model_year=:model_year,colour=:colour,fuel=:fuel,transmission=:transmission,seats=:seats,doors=:doors,luggage_capacity=:luggage_capacity,current_mileage=:current_mileage,purchase_date=:purchase_date,purchase_price=:purchase_price,financing_type=:financing_type,monthly_finance_amount=:monthly_finance_amount,base_daily_price=:base_daily_price,recommended_deposit=:recommended_deposit,mileage_allowance=:mileage_allowance,updated_by=:updated_by,updated_at=NOW(6) WHERE id=:id', $data + ['updated_by'=>currentUserId(),'id'=>$vehicle['id']]);
        $auditAfter = ['changes'=>$changes];
        if ((int) $data['current_mileage'] < (int) $vehicle['current_mileage']) $auditAfter['mileage_correction_reason'] = $reason;
        auditLog('vehicle.profile_updated','vehicle',$vehicle['id'],null,$auditAfter,$vehicle['agency_id']);
        return $changes;
    });
}

function changeVehicleStatus($vehicleId, $status, $reason = '')
{
    return withTransaction(function () use ($vehicleId, $status, $reason) {
        $vehicle = vehicleRecord($vehicleId, true);
        $status = validateChoice((string) $status, vehicleStatuses(), null);
        if (!$status) throw new InvalidArgumentException(t('validation.invalid_vehicle_status'));
        if ($status === $vehicle['status']) return false;
        dbExecute('UPDATE vehicles SET status=:status,updated_by=:user,updated_at=NOW(6) WHERE id=:id', ['status'=>$status,'user'=>currentUserId(),'id'=>$vehicle['id']]);
        dbExecute('INSERT INTO vehicle_status_history(vehicle_id,from_status,to_status,reason,changed_by) VALUES(:id,:old,:new,:reason,:user)', ['id'=>$vehicle['id'],'old'=>$vehicle['status'],'new'=>$status,'reason'=>trim((string) $reason) ?: null,'user'=>currentUserId()]);
        auditLog('vehicle.status_changed','vehicle',$vehicle['id'],['status'=>$vehicle['status']],['status'=>$status,'reason'=>trim((string) $reason)],$vehicle['agency_id']);
        return true;
    });
}

function vehicleMediaRows($vehicleId, $includeArchived = false)
{
    $vehicle = vehicleRecord($vehicleId);
    $sql = 'SELECT vm.* FROM vehicle_media vm WHERE vm.vehicle_id=:vehicle AND vm.agency_id=:agency';
    if (!$includeArchived) $sql .= ' AND vm.archived_at IS NULL';
    $sql .= ' ORDER BY vm.archived_at IS NOT NULL, vm.sort_order, vm.id';
    return dbFetchAll($sql, ['vehicle'=>$vehicle['id'],'agency'=>$vehicle['agency_id']]);
}

function normalizedUploadFiles(array $files)
{
    if (!isset($files['name'])) return [];
    if (!is_array($files['name'])) return [$files];
    $result = [];
    foreach ($files['name'] as $index=>$name) {
        $error = $files['error'][$index] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) continue;
        $result[] = ['name'=>$name,'type'=>$files['type'][$index] ?? '','tmp_name'=>$files['tmp_name'][$index] ?? '','error'=>$error,'size'=>$files['size'][$index] ?? 0];
    }
    return $result;
}

function insertVehicleMedia(array $vehicle, array $stored, $sortOrder, $primary = false)
{
    dbExecute('INSERT INTO vehicle_media(agency_id,vehicle_id,media_type,storage_path,original_name,mime_type,file_size,width,height,sort_order,is_primary,created_by,updated_by) VALUES(:agency,:vehicle,\'image\',:path,:original,:mime,:size,:width,:height,:sort_order,:primary,:creator,:updater)', [
        'agency'=>$vehicle['agency_id'],'vehicle'=>$vehicle['id'],'path'=>$stored['path'],'original'=>$stored['original_name'],'mime'=>$stored['mime_type'],'size'=>$stored['size'],'width'=>$stored['width'] ?? null,'height'=>$stored['height'] ?? null,'sort_order'=>$sortOrder,'primary'=>$primary ? 1 : 0,'creator'=>currentUserId(),'updater'=>currentUserId()
    ]);
    return (int) db()->lastInsertId();
}

function uploadVehicleMedia($vehicleId, array $files)
{
    $files = normalizedUploadFiles($files);
    if (!$files || count($files) > 10) throw new InvalidArgumentException(t('validation.media_upload_count'));
    $storedPaths = [];
    try {
        return withTransaction(function () use ($vehicleId, $files, &$storedPaths) {
            $vehicle = vehicleRecord($vehicleId, true);
            $state = dbFetchOne('SELECT COUNT(*) AS total, COALESCE(MAX(sort_order),0) AS maximum FROM vehicle_media WHERE vehicle_id=:vehicle AND agency_id=:agency AND archived_at IS NULL FOR UPDATE', ['vehicle'=>$vehicle['id'],'agency'=>$vehicle['agency_id']]);
            if ((int) $state['total'] + count($files) > 50) throw new DomainException(t('validation.media_gallery_limit'));
            $makePrimary = (int) $state['total'] === 0;
            $sort = (int) $state['maximum'];
            $ids = [];
            foreach ($files as $file) {
                $stored = storeValidatedImage($file, 'vehicle_media');
                $storedPaths[] = $stored['path'];
                $sort += 10;
                $ids[] = insertVehicleMedia($vehicle, $stored, $sort, $makePrimary);
                if ($makePrimary) {
                    dbExecute('UPDATE vehicles SET primary_image_path=:path,updated_by=:user,updated_at=NOW(6) WHERE id=:id', ['path'=>$stored['path'],'user'=>currentUserId(),'id'=>$vehicle['id']]);
                    $makePrimary = false;
                }
            }
            auditLog('vehicle.media_uploaded','vehicle',$vehicle['id'],null,['media_ids'=>$ids,'count'=>count($ids)],$vehicle['agency_id']);
            return $ids;
        });
    } catch (Throwable $exception) {
        foreach ($storedPaths as $path) removeNewStoredUpload($path);
        throw $exception;
    }
}

function vehicleMediaRecord($vehicleId, $mediaId, $forUpdate = false)
{
    $vehicle = vehicleRecord($vehicleId, $forUpdate);
    $sql = 'SELECT * FROM vehicle_media WHERE id=:id AND vehicle_id=:vehicle AND agency_id=:agency';
    if ($forUpdate) $sql .= ' FOR UPDATE';
    $media = dbFetchOne($sql, ['id'=>(int) $mediaId,'vehicle'=>$vehicle['id'],'agency'=>$vehicle['agency_id']]);
    if (!$media) throw new InvalidArgumentException(t('validation.media_not_found'));
    return [$vehicle,$media];
}

function updateVehicleMediaText($vehicleId, $mediaId, $caption, $altText)
{
    return withTransaction(function () use ($vehicleId, $mediaId, $caption, $altText) {
        [$vehicle,$media] = vehicleMediaRecord($vehicleId, $mediaId, true);
        $caption = trim((string) $caption); $altText = trim((string) $altText);
        if (strlen($caption) > 255 || strlen($altText) > 255) throw new InvalidArgumentException(t('validation.media_text_length'));
        dbExecute('UPDATE vehicle_media SET caption=:caption,alt_text=:alt,updated_by=:user WHERE id=:id', ['caption'=>$caption ?: null,'alt'=>$altText ?: null,'user'=>currentUserId(),'id'=>$media['id']]);
        auditLog('vehicle.media_updated','vehicle_media',$media['id'],['caption'=>$media['caption'],'alt_text'=>$media['alt_text']],['caption'=>$caption ?: null,'alt_text'=>$altText ?: null],$vehicle['agency_id']);
    });
}

function setPrimaryVehicleMedia($vehicleId, $mediaId)
{
    return withTransaction(function () use ($vehicleId, $mediaId) {
        [$vehicle,$media] = vehicleMediaRecord($vehicleId, $mediaId, true);
        if ($media['archived_at'] !== null) throw new DomainException(t('validation.archived_media_primary'));
        dbExecute('UPDATE vehicle_media SET is_primary=0,updated_by=:user WHERE vehicle_id=:vehicle AND agency_id=:agency AND archived_at IS NULL AND is_primary=1', ['user'=>currentUserId(),'vehicle'=>$vehicle['id'],'agency'=>$vehicle['agency_id']]);
        dbExecute('UPDATE vehicle_media SET is_primary=1,updated_by=:user WHERE id=:id', ['user'=>currentUserId(),'id'=>$media['id']]);
        dbExecute('UPDATE vehicles SET primary_image_path=:path,updated_by=:user,updated_at=NOW(6) WHERE id=:id', ['path'=>$media['storage_path'],'user'=>currentUserId(),'id'=>$vehicle['id']]);
        auditLog('vehicle.media_primary_changed','vehicle',$vehicle['id'],['media_id'=>null],['media_id'=>$media['id']],$vehicle['agency_id']);
    });
}

function reorderVehicleMedia($vehicleId, array $orderedIds)
{
    return withTransaction(function () use ($vehicleId, $orderedIds) {
        $vehicle = vehicleRecord($vehicleId, true);
        $active = dbFetchAll('SELECT id FROM vehicle_media WHERE vehicle_id=:vehicle AND agency_id=:agency AND archived_at IS NULL ORDER BY sort_order,id FOR UPDATE', ['vehicle'=>$vehicle['id'],'agency'=>$vehicle['agency_id']]);
        $expected = array_map('intval', array_column($active, 'id'));
        $provided = array_map('intval', $orderedIds);
        $sortedExpected=$expected;$sortedProvided=$provided;sort($sortedExpected);sort($sortedProvided);
        if (count($provided)!==count(array_unique($provided)) || $sortedExpected!==$sortedProvided) throw new InvalidArgumentException(t('validation.media_order'));
        foreach ($provided as $index=>$id) dbExecute('UPDATE vehicle_media SET sort_order=:sort,updated_by=:user WHERE id=:id', ['sort'=>($index+1)*10,'user'=>currentUserId(),'id'=>$id]);
        auditLog('vehicle.media_reordered','vehicle',$vehicle['id'],['order'=>$expected],['order'=>$provided],$vehicle['agency_id']);
    });
}

function moveVehicleMedia($vehicleId, $mediaId, $direction)
{
    $rows = vehicleMediaRows($vehicleId);
    $ids = array_map('intval', array_column($rows, 'id'));
    $index = array_search((int) $mediaId, $ids, true);
    if ($index === false) throw new InvalidArgumentException(t('validation.media_not_found'));
    $target = $direction === 'up' ? $index - 1 : ($direction === 'down' ? $index + 1 : -1);
    if ($target < 0 || $target >= count($ids)) return false;
    [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];
    reorderVehicleMedia($vehicleId, $ids);
    return true;
}

function archiveVehicleMedia($vehicleId, $mediaId)
{
    return withTransaction(function () use ($vehicleId, $mediaId) {
        [$vehicle,$media] = vehicleMediaRecord($vehicleId, $mediaId, true);
        if ($media['archived_at'] !== null) return;
        dbExecute('UPDATE vehicle_media SET is_primary=0,archived_at=NOW(6),archived_by=:archiver,updated_by=:updater WHERE id=:id', ['archiver'=>currentUserId(),'updater'=>currentUserId(),'id'=>$media['id']]);
        $promoted = null;
        if ((int) $media['is_primary'] === 1) {
            $promoted = dbFetchOne('SELECT * FROM vehicle_media WHERE vehicle_id=:vehicle AND agency_id=:agency AND archived_at IS NULL ORDER BY sort_order,id LIMIT 1 FOR UPDATE', ['vehicle'=>$vehicle['id'],'agency'=>$vehicle['agency_id']]);
            if ($promoted) dbExecute('UPDATE vehicle_media SET is_primary=1,updated_by=:user WHERE id=:id', ['user'=>currentUserId(),'id'=>$promoted['id']]);
            dbExecute('UPDATE vehicles SET primary_image_path=:path,updated_by=:user,updated_at=NOW(6) WHERE id=:id', ['path'=>$promoted['storage_path'] ?? null,'user'=>currentUserId(),'id'=>$vehicle['id']]);
        }
        auditLog('vehicle.media_archived','vehicle_media',$media['id'],null,['promoted_media_id'=>$promoted['id'] ?? null],$vehicle['agency_id']);
    });
}

function restoreVehicleMedia($vehicleId, $mediaId)
{
    return withTransaction(function () use ($vehicleId, $mediaId) {
        [$vehicle,$media] = vehicleMediaRecord($vehicleId, $mediaId, true);
        if ($media['archived_at'] === null) return;
        $state=dbFetchOne('SELECT COUNT(*) total,COALESCE(MAX(sort_order),0) maximum FROM vehicle_media WHERE vehicle_id=:vehicle AND agency_id=:agency AND archived_at IS NULL FOR UPDATE',['vehicle'=>$vehicle['id'],'agency'=>$vehicle['agency_id']]);
        if ((int)$state['total']>=50) throw new DomainException(t('validation.media_gallery_limit'));
        dbExecute('UPDATE vehicle_media SET archived_at=NULL,archived_by=NULL,is_primary=0,sort_order=:sort,updated_by=:user WHERE id=:id', ['sort'=>(int)$state['maximum']+10,'user'=>currentUserId(),'id'=>$media['id']]);
        auditLog('vehicle.media_restored','vehicle_media',$media['id'],null,['sort_order'=>(int)$state['maximum']+10],$vehicle['agency_id']);
    });
}
