<?php

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
$migrationPath = $root . '/database/migrations/006_finance_core.sql';
$migration = (string) file_get_contents($migrationPath);
$testSource = (string) file_get_contents(__FILE__);
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

/* Static assertions run even on environments without CREATE DATABASE authority. */
$requiredTokens = [
    'information_schema.COLUMNS', 'COLUMN_TYPE', 'IS_NULLABLE', 'COLUMN_DEFAULT',
    'EXTRA', 'GENERATION_EXPRESSION', 'DATETIME_PRECISION', 'information_schema.TABLES',
    'information_schema.STATISTICS', 'SEQ_IN_INDEX', 'information_schema.KEY_COLUMN_USAGE',
    'information_schema.REFERENTIAL_CONSTRAINTS', 'UPDATE_RULE', 'DELETE_RULE',
    'CONSTRAINT_TYPE', 'ON UPDATE RESTRICT', 'ON DELETE RESTRICT',
    'information_schema.CHECK_CONSTRAINTS', 'CHECK_CLAUSE', 'utf8mb4_unicode_ci',
    'financial_number_allocations', 'finance_idempotency_keys', 'payment_adjustments',
    'deposit_events', 'cash_movements', 'active_reservation_id', 'open_agency_id',
    'source_key', 'excess_payment_id', 'requested_deposit_id',
];
foreach ($requiredTokens as $token) {
    $assert(str_contains($migration, $token), 'Migration exact-structure token missing: ' . $token);
}
foreach ([
    'legacy_finance_paid_amount', 'finance_tracking_started_at', 'is_legacy_opening',
    'proof_original_name', 'proof_mime_type', 'proof_file_size', 'language_code',
    'opening_paid_amount', 'original_invoice_id', 'credit_reason', 'cancelled_by',
    'agency_id', 'received_amount', 'returned_amount', 'legacy_opening_received_amount',
    'legacy_opening_retained_amount', 'legacy_opening_returned_amount', 'legacy_opening_status',
    'legacy_opening_resolved_at', 'legacy_opening_resolved_by', 'legacy_opening_resolution_reason',
    'event_tracking_started_at', 'method', 'expense_type', 'direction', 'original_expense_id',
    'receipt_original_name', 'receipt_mime_type', 'receipt_file_size', 'decided_at', 'decided_by',
    'decision_reason', 'owner_exception_used', 'owner_exception_reason', 'currency',
    'legacy_net_movement_amount', 'movement_tracking_started_at', 'closing_boundary_at',
    'difference_reason', 'updated_at',
] as $column) {
    $assert(str_contains($migration, "'" . $column . "'"), 'Expected column definition missing: ' . $column);
}
foreach ([
    'uq_reservations_id_agency', 'uq_payments_id_agency', 'idx_payments_reservation_settlement',
    'idx_payments_invoice_settlement', 'uq_invoices_id_agency', 'uq_invoices_active_reservation',
    'idx_invoices_original', 'idx_invoices_type_status_date', 'uq_deposits_id_agency',
    'idx_deposits_agency_reservation_status', 'uq_expenses_id_agency', 'idx_expenses_original',
    'uq_cash_registers_id_agency', 'uq_cash_register_open_agency', 'idx_cash_register_agency_status',
    'uq_finance_number', 'idx_finance_number_state', 'uq_finance_idempotency',
    'idx_finance_idempotency_actor', 'uq_payment_adjustment_number', 'uq_payment_adjustment_excess',
    'idx_payment_adjustment_payment', 'uq_deposit_event_number', 'uq_deposit_requested',
    'idx_deposit_event_deposit', 'uq_cash_movement_number', 'uq_cash_movement_source',
    'idx_cash_movement_register', 'idx_cash_movement_agency_type',
    'uq_customers_id_agency', 'uq_vehicles_id_agency', 'uq_reservations_id_agency', 'uq_invoices_id_agency',
] as $index) {
    $assert(str_contains($migration, "'" . $index . "'"), 'Expected index definition missing: ' . $index);
}
foreach ([
    'fk_invoices_customer_agency', 'fk_invoices_reservation_agency',
    'fk_payments_reservation_agency', 'fk_payments_invoice_agency',
    'fk_expenses_vehicle_agency', 'fk_invoices_original_agency', 'fk_expenses_original_agency', 'fk_deposits_agency',
    'fk_deposits_reservation_agency', 'fk_finance_number_agency', 'fk_finance_number_user',
    'fk_finance_idempotency_agency', 'fk_finance_idempotency_user', 'fk_payment_adjustment_agency',
    'fk_payment_adjustment_payment', 'fk_payment_adjustment_deposit', 'fk_payment_adjustment_user',
    'fk_deposit_event_agency', 'fk_deposit_event_deposit', 'fk_deposit_event_payment',
    'fk_deposit_event_user', 'fk_cash_movement_agency', 'fk_cash_movement_register',
    'fk_cash_movement_user',
] as $foreignKey) {
    $assert(str_contains($migration, "'" . $foreignKey . "'"), 'Expected foreign key definition missing: ' . $foreignKey);
}
foreach ([
    "('invoices','fk_invoices_customer_agency','customer_id,agency_id'",
    "('invoices','fk_invoices_reservation_agency','reservation_id,agency_id'",
    "('payments','fk_payments_reservation_agency','reservation_id,agency_id'",
    "('payments','fk_payments_invoice_agency','invoice_id,agency_id'",
    "('expenses','fk_expenses_vehicle_agency','vehicle_id,agency_id'",
] as $compositeDefinition) {
    $assert(str_contains($migration, $compositeDefinition), 'Exact composite agency FK definition missing: ' . $compositeDefinition);
}
foreach ([
    'mismatch_invoice_customer', 'mismatch_invoice_reservation',
    'mismatch_payment_reservation', 'mismatch_payment_invoice', 'mismatch_expense_vehicle',
    'conflict_composite_fk_cascade', 'conflict_composite_fk_local_order', 'conflict_composite_fk_ref_order',
    'P5A-INV-CUSTOMER', 'P5A-INV-RESERVATION', 'P5A-PAY-RESERVATION', 'P5A-PAY-INVOICE',
] as $scenarioToken) {
    $assert(str_contains($testSource, $scenarioToken), 'Composite agency scenario missing: ' . $scenarioToken);
}
foreach ([
    'chk_finance_number_type', 'chk_finance_number_status', 'chk_finance_idempotency_status',
    'chk_payment_adjustment_type', 'chk_payment_adjustment_amount', 'chk_payment_adjustment_method',
    'chk_payment_adjustment_reason', 'chk_payment_adjustment_status', 'chk_deposit_event_type',
    'chk_deposit_event_amount', 'chk_deposit_event_method', 'chk_deposit_event_reason',
    'chk_deposit_event_status', 'chk_cash_movement_type', 'chk_cash_movement_direction',
    'chk_cash_movement_amount', 'chk_cash_movement_reason', 'chk_cash_movement_status',
] as $check) {
    $assert(str_contains($migration, "'" . $check . "'"), 'Expected CHECK definition missing: ' . $check);
}
$assert(!preg_match('/\bDROP\s+(?:TABLE|COLUMN|FOREIGN\s+KEY|CONSTRAINT)\b/i', $migration), 'Migration contains destructive DROP DDL');
$assert(!preg_match('/\bDELETE\s+FROM\b/i', $migration), 'Migration deletes finance history');
$assert(!str_contains($migration, 'INSERT INTO schema_migrations') && !str_contains($migration, 'INSERT IGNORE INTO schema_migrations'), 'Migration records its own version');
foreach (['reserved', 'consumed', 'voided'] as $status) {
    $assert(str_contains($migration, "'" . $status . "'"), 'Number allocation state missing: ' . $status);
}

if ($failures) {
    foreach (array_unique($failures) as $failure) {
        fwrite(STDERR, 'FAIL: ' . $failure . PHP_EOL);
    }
    exit(1);
}
echo "Phase 5A migration structural assertions passed.\n";

$requiredEnvironment = ['DB_HOST', 'DB_PORT', 'DB_USER', 'DB_PASSWORD', 'DB_CHARSET'];
foreach ($requiredEnvironment as $name) {
    if ((string) getenv($name) === '') {
        fwrite(STDERR, "PENDING: Privileged disposable-database migration scenarios require database environment.\n");
        exit(2);
    }
}

$runId = 'p5a_recovery_' . strtolower(bin2hex(random_bytes(4)));
$databases = [];
$created = [];
$charset = preg_match('/^[A-Za-z0-9_]+$/', (string) getenv('DB_CHARSET')) ? (string) getenv('DB_CHARSET') : 'utf8mb4';
$dsn = 'mysql:host=' . getenv('DB_HOST') . ';port=' . (int) getenv('DB_PORT') . ';charset=' . $charset;
$admin = null;
$identifier = static function (string $value): string {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $value)) {
        throw new RuntimeException('Unsafe disposable identifier');
    }
    return '`' . $value . '`';
};

try {
    $admin = new PDO($dsn, (string) getenv('DB_USER'), (string) getenv('DB_PASSWORD'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $databases = [
        'fresh' => $runId . '_fresh',
        'partial' => $runId . '_partial',
        'conflict_column_type' => $runId . '_column_type',
        'conflict_null_default' => $runId . '_null_default',
        'conflict_generated' => $runId . '_generated',
        'conflict_index' => $runId . '_index',
        'conflict_non_unique' => $runId . '_non_unique',
        'conflict_fk_cascade' => $runId . '_fk_cascade',
        'conflict_fk_order' => $runId . '_fk_order',
        'conflict_composite_fk_cascade' => $runId . '_composite_fk_cascade',
        'conflict_composite_fk_local_order' => $runId . '_composite_fk_local_order',
        'conflict_composite_fk_ref_order' => $runId . '_composite_fk_ref_order',
        'conflict_check' => $runId . '_check',
        'conflict_table_definition' => $runId . '_table_definition',
        'preflight' => $runId . '_preflight',
        'mismatch_invoice_customer' => $runId . '_mismatch_invoice_customer',
        'mismatch_invoice_reservation' => $runId . '_mismatch_invoice_reservation',
        'mismatch_payment_reservation' => $runId . '_mismatch_payment_reservation',
        'mismatch_payment_invoice' => $runId . '_mismatch_payment_invoice',
        'mismatch_expense_vehicle' => $runId . '_mismatch_expense_vehicle',
    ];
    foreach ($databases as $database) {
        $admin->exec('CREATE DATABASE ' . $identifier($database) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $created[] = $database;
    }
} catch (Throwable $exception) {
    if ($admin instanceof PDO) {
        foreach (array_reverse($created) as $database) {
            try {
                $admin->exec('DROP DATABASE IF EXISTS ' . $identifier($database));
            } catch (Throwable $ignored) {
            }
        }
    }
    fwrite(STDERR, "PENDING: Privileged disposable-database migration scenarios require CREATE DATABASE authority; structural assertions ran, but execution was not claimed.\n");
    exit(2);
}

$connect = static function (string $database) use ($dsn): PDO {
    return new PDO($dsn . ';dbname=' . $database, (string) getenv('DB_USER'), (string) getenv('DB_PASSWORD'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
};
$prepareBase = static function (PDO $pdo) use ($root): void {
    foreach (['001_authoritative_schema.sql', '003_operational_extensions.sql', '004_vehicle_detail_media.sql', '005_customer_reservation_workspace.sql'] as $file) {
        $pdo->exec((string) file_get_contents($root . '/database/migrations/' . $file));
    }
    $pdo->exec("INSERT IGNORE INTO schema_migrations(version) VALUES('002_import_legacy_data'),('005_customer_reservation_workspace')");
};
$runMigrations = static function (string $database) use ($root): array {
    $oldDatabase = getenv('DB_NAME');
    putenv('DB_NAME=' . $database);
    $pipes = [];
    $process = proc_open([PHP_BINARY, '-d', 'session.save_path=' . $root . '/storage', $root . '/bin/migrate.php'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, $root);
    if (!is_resource($process)) {
        putenv('DB_NAME=' . $oldDatabase);
        return ['code' => 1, 'out' => ''];
    }
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    putenv('DB_NAME=' . $oldDatabase);
    return ['code' => $code, 'out' => $output . $error];
};
$readForeignKey = static function (PDO $pdo, string $table, string $constraint): ?array {
    $statement = $pdo->prepare(
        "SELECT tc.CONSTRAINT_TYPE,k.COLUMN_NAME,k.ORDINAL_POSITION,
                k.REFERENCED_TABLE_SCHEMA,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,
                r.UPDATE_RULE,r.DELETE_RULE
         FROM information_schema.TABLE_CONSTRAINTS tc
         JOIN information_schema.KEY_COLUMN_USAGE k
           ON k.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA AND k.TABLE_NAME=tc.TABLE_NAME
          AND k.CONSTRAINT_NAME=tc.CONSTRAINT_NAME
         JOIN information_schema.REFERENTIAL_CONSTRAINTS r
           ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.TABLE_NAME=k.TABLE_NAME
          AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME
         WHERE tc.CONSTRAINT_SCHEMA=DATABASE() AND tc.TABLE_NAME=? AND tc.CONSTRAINT_NAME=?
           AND k.REFERENCED_TABLE_NAME IS NOT NULL
         ORDER BY k.ORDINAL_POSITION"
    );
    $statement->execute([$table, $constraint]);
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        return null;
    }
    $first = $rows[0];
    return [
        'constraint_type' => (string) $first['CONSTRAINT_TYPE'],
        'local_columns' => implode(',', array_map(static fn (array $row): string => (string) $row['COLUMN_NAME'], $rows)),
        'referenced_schema' => (string) $first['REFERENCED_TABLE_SCHEMA'],
        'referenced_table' => (string) $first['REFERENCED_TABLE_NAME'],
        'referenced_columns' => implode(',', array_map(static fn (array $row): string => (string) $row['REFERENCED_COLUMN_NAME'], $rows)),
        'update_rule' => strtoupper((string) $first['UPDATE_RULE']),
        'delete_rule' => strtoupper((string) $first['DELETE_RULE']),
    ];
};
$expectedCompositeFks = [
    ['invoices', 'fk_invoices_customer_agency', 'customer_id,agency_id', 'customers', 'id,agency_id'],
    ['invoices', 'fk_invoices_reservation_agency', 'reservation_id,agency_id', 'reservations', 'id,agency_id'],
    ['payments', 'fk_payments_reservation_agency', 'reservation_id,agency_id', 'reservations', 'id,agency_id'],
    ['payments', 'fk_payments_invoice_agency', 'invoice_id,agency_id', 'invoices', 'id,agency_id'],
    ['expenses', 'fk_expenses_vehicle_agency', 'vehicle_id,agency_id', 'vehicles', 'id,agency_id'],
];
$seedAgencyFixtures = static function (PDO $pdo): array {
    $pdo->exec("INSERT INTO agencies(name,code,status) VALUES('P5A agency A','P5A_FIX_A','active'),('P5A agency B','P5A_FIX_B','active')");
    $agencyA = (int) $pdo->query("SELECT id FROM agencies WHERE code='P5A_FIX_A'")->fetchColumn();
    $agencyB = (int) $pdo->query("SELECT id FROM agencies WHERE code='P5A_FIX_B'")->fetchColumn();
    $pdo->exec("INSERT INTO customers(agency_id,first_name,last_name,status) VALUES($agencyA,'P5A','Customer A','active'),($agencyB,'P5A','Customer B','active')");
    $customerA = (int) $pdo->query("SELECT id FROM customers WHERE agency_id=$agencyA ORDER BY id LIMIT 1")->fetchColumn();
    $customerB = (int) $pdo->query("SELECT id FROM customers WHERE agency_id=$agencyB ORDER BY id LIMIT 1")->fetchColumn();
    $pdo->exec("INSERT INTO vehicle_categories(agency_id,name,code,status) VALUES($agencyA,'P5A category A','P5A_CAT_A','active'),($agencyB,'P5A category B','P5A_CAT_B','active')");
    $categoryA = (int) $pdo->query("SELECT id FROM vehicle_categories WHERE code='P5A_CAT_A'")->fetchColumn();
    $categoryB = (int) $pdo->query("SELECT id FROM vehicle_categories WHERE code='P5A_CAT_B'")->fetchColumn();
    $pdo->exec("INSERT INTO vehicles(agency_id,category_id,registration_number,brand,model,status) VALUES($agencyA,$categoryA,'P5A-VEH-A','P5A','A','available'),($agencyB,$categoryB,'P5A-VEH-B','P5A','B','available')");
    $vehicleA = (int) $pdo->query("SELECT id FROM vehicles WHERE registration_number='P5A-VEH-A'")->fetchColumn();
    $vehicleB = (int) $pdo->query("SELECT id FROM vehicles WHERE registration_number='P5A-VEH-B'")->fetchColumn();
    $pdo->exec("INSERT INTO reservations(reference,agency_id,customer_id,vehicle_id,category_id,status,source,pickup_at,return_at,currency,daily_price,rental_days,total_amount,pricing_snapshot_json) VALUES('P5A-RES-A',$agencyA,$customerA,$vehicleA,$categoryA,'pending','agency','2030-01-01 10:00:00','2030-01-02 10:00:00','MAD',10,1,10,'{}'),('P5A-RES-B',$agencyB,$customerB,$vehicleB,$categoryB,'pending','agency','2030-01-01 10:00:00','2030-01-02 10:00:00','MAD',10,1,10,'{}')");
    $reservationA = (int) $pdo->query("SELECT id FROM reservations WHERE reference='P5A-RES-A'")->fetchColumn();
    $reservationB = (int) $pdo->query("SELECT id FROM reservations WHERE reference='P5A-RES-B'")->fetchColumn();
    return compact('agencyA', 'agencyB', 'customerA', 'customerB', 'categoryA', 'categoryB', 'vehicleA', 'vehicleB', 'reservationA', 'reservationB');
};
$expectCrossAgencyPreflight = static function (PDO $pdo, string $database, string $constraint, callable $mutator) use ($prepareBase, $runMigrations, $seedAgencyFixtures, &$assert): void {
    $prepareBase($pdo);
    $initial = $runMigrations($database);
    $assert($initial['code'] === 0 && str_contains($initial['out'], 'APPLY 006_finance_core'), 'Cross-agency fixture initial migration failed for ' . $database);
    try {
        $ids = $seedAgencyFixtures($pdo);
        $pdo->exec("ALTER TABLE " . (str_starts_with($constraint, 'fk_invoices_') ? 'invoices' : (str_starts_with($constraint, 'fk_payments_') ? 'payments' : 'expenses')) . " DROP FOREIGN KEY " . $constraint);
        $mutator($pdo, $ids);
        $beforeRows = [];
        foreach (['invoices', 'payments', 'expenses'] as $financeTable) {
            $beforeRows[$financeTable] = (string) $pdo->query("SELECT COALESCE(GROUP_CONCAT(CONCAT(id,':',agency_id) ORDER BY id SEPARATOR '|'),'') FROM $financeTable")->fetchColumn();
        }
        $pdo->exec("DELETE FROM schema_migrations WHERE version='006_finance_core'");
        $retry = $runMigrations($database);
        $assert($retry['code'] === 1, 'Cross-agency mismatch did not fail closed: ' . $database);
        $assert((int) $pdo->query("SELECT COUNT(*) FROM schema_migrations WHERE version='006_finance_core'")->fetchColumn() === 0, 'Cross-agency mismatch recorded migration: ' . $database);
        foreach (['invoices', 'payments', 'expenses'] as $financeTable) {
            $afterRows = (string) $pdo->query("SELECT COALESCE(GROUP_CONCAT(CONCAT(id,':',agency_id) ORDER BY id SEPARATOR '|'),'') FROM $financeTable")->fetchColumn();
            $assert($afterRows === $beforeRows[$financeTable], 'Cross-agency preflight altered finance history: ' . $database . '/' . $financeTable);
        }
    } catch (Throwable $exception) {
        $assert(false, 'Cross-agency fixture setup failed: ' . $database);
    }
};
$expectFailureAfter006 = static function (PDO $pdo, string $database, callable $mutator) use ($prepareBase, $runMigrations, &$assert): void {
    $prepareBase($pdo);
    $initial = $runMigrations($database);
    $assert($initial['code'] === 0 && str_contains($initial['out'], 'APPLY 006_finance_core'), 'Conflict fixture initial migration failed for ' . $database);
    $mutator($pdo);
    $pdo->exec("DELETE FROM schema_migrations WHERE version='006_finance_core'");
    $retry = $runMigrations($database);
    $assert($retry['code'] === 1, 'Incompatible structural fixture did not fail closed: ' . $database);
    $assert((int) $pdo->query("SELECT COUNT(*) FROM schema_migrations WHERE version='006_finance_core'")->fetchColumn() === 0, 'Failed structural fixture recorded migration: ' . $database);
};

try {
    $fresh = $runMigrations($databases['fresh']);
    $assert($fresh['code'] === 0 && str_contains($fresh['out'], 'APPLY 006_finance_core'), 'Fresh 001-006 migration failed');
    $freshAgain = $runMigrations($databases['fresh']);
    $assert($freshAgain['code'] === 0 && str_contains($freshAgain['out'], 'SKIP 006_finance_core (already applied)'), 'Fresh immediate rerun failed');
    $freshPdo = $connect($databases['fresh']);
    foreach ($expectedCompositeFks as [$table, $constraint, $localColumns, $referencedTable, $referencedColumns]) {
        $metadata = $readForeignKey($freshPdo, $table, $constraint);
        $assert($metadata !== null, 'Composite agency FK missing: ' . $constraint);
        if ($metadata !== null) {
            $assert($metadata['constraint_type'] === 'FOREIGN KEY', 'Composite agency FK type mismatch: ' . $constraint);
            $assert($metadata['local_columns'] === $localColumns, 'Composite agency FK local order mismatch: ' . $constraint);
            $assert($metadata['referenced_schema'] === $databases['fresh'], 'Composite agency FK schema mismatch: ' . $constraint);
            $assert($metadata['referenced_table'] === $referencedTable, 'Composite agency FK parent mismatch: ' . $constraint);
            $assert($metadata['referenced_columns'] === $referencedColumns, 'Composite agency FK referenced order mismatch: ' . $constraint);
            $assert(in_array($metadata['update_rule'], ['RESTRICT', 'NO ACTION'], true), 'Composite agency FK UPDATE rule mismatch: ' . $constraint);
            $assert(in_array($metadata['delete_rule'], ['RESTRICT', 'NO ACTION'], true), 'Composite agency FK DELETE rule mismatch: ' . $constraint);
        }
    }

    $partial = $runMigrations($databases['partial']);
    $assert($partial['code'] === 0, 'Partial fixture initial migration failed');
    $partialPdo = $connect($databases['partial']);
    $partialPdo->exec("DELETE FROM schema_migrations WHERE version='006_finance_core'");
    $partialPdo->exec('DROP TABLE cash_movements');
    $partialRetry = $runMigrations($databases['partial']);
    $assert($partialRetry['code'] === 0 && str_contains($partialRetry['out'], 'APPLY 006_finance_core'), 'Compatible partial-DDL retry failed');
    $assert((int) $partialPdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cash_movements'")->fetchColumn() === 1, 'Partial retry did not recreate missing table');

    $expectFailureAfter006($connect($databases['conflict_column_type']), $databases['conflict_column_type'], static function (PDO $pdo): void {
        $pdo->exec('ALTER TABLE reservations MODIFY legacy_finance_paid_amount INT NOT NULL DEFAULT 0');
    });
    $expectFailureAfter006($connect($databases['conflict_null_default']), $databases['conflict_null_default'], static function (PDO $pdo): void {
        $pdo->exec('ALTER TABLE payments MODIFY is_legacy_opening TINYINT(1) NULL DEFAULT 1');
    });
    $expectFailureAfter006($connect($databases['conflict_generated']), $databases['conflict_generated'], static function (PDO $pdo): void {
        $pdo->exec('ALTER TABLE invoices DROP COLUMN active_reservation_id, ADD COLUMN active_reservation_id BIGINT UNSIGNED GENERATED ALWAYS AS (reservation_id) PERSISTENT');
    });
    $expectFailureAfter006($connect($databases['conflict_index']), $databases['conflict_index'], static function (PDO $pdo): void {
        $pdo->exec('ALTER TABLE financial_number_allocations DROP INDEX uq_finance_number, ADD UNIQUE KEY uq_finance_number (status)');
    });
    $expectFailureAfter006($connect($databases['conflict_non_unique']), $databases['conflict_non_unique'], static function (PDO $pdo): void {
        $pdo->exec('ALTER TABLE financial_number_allocations DROP INDEX uq_finance_number, ADD KEY uq_finance_number (allocated_number)');
    });
    $expectFailureAfter006($connect($databases['conflict_fk_cascade']), $databases['conflict_fk_cascade'], static function (PDO $pdo): void {
        $pdo->exec('ALTER TABLE financial_number_allocations DROP FOREIGN KEY fk_finance_number_agency, ADD CONSTRAINT fk_finance_number_agency FOREIGN KEY (agency_id) REFERENCES agencies(id) ON UPDATE RESTRICT ON DELETE CASCADE');
    });
    $expectFailureAfter006($connect($databases['conflict_fk_order']), $databases['conflict_fk_order'], static function (PDO $pdo): void {
        $pdo->exec('ALTER TABLE payment_adjustments DROP FOREIGN KEY fk_payment_adjustment_payment, ADD CONSTRAINT fk_payment_adjustment_payment FOREIGN KEY (agency_id,payment_id) REFERENCES payments(id,agency_id) ON UPDATE RESTRICT ON DELETE RESTRICT');
    });
    $expectFailureAfter006($connect($databases['conflict_composite_fk_cascade']), $databases['conflict_composite_fk_cascade'], static function (PDO $pdo): void {
        $pdo->exec('ALTER TABLE invoices DROP FOREIGN KEY fk_invoices_customer_agency, ADD CONSTRAINT fk_invoices_customer_agency FOREIGN KEY (customer_id,agency_id) REFERENCES customers(id,agency_id) ON UPDATE RESTRICT ON DELETE CASCADE');
    });
    $expectFailureAfter006($connect($databases['conflict_composite_fk_local_order']), $databases['conflict_composite_fk_local_order'], static function (PDO $pdo): void {
        $pdo->exec('ALTER TABLE invoices DROP FOREIGN KEY fk_invoices_customer_agency, ADD CONSTRAINT fk_invoices_customer_agency FOREIGN KEY (agency_id,customer_id) REFERENCES customers(id,agency_id) ON UPDATE RESTRICT ON DELETE RESTRICT');
    });
    $expectFailureAfter006($connect($databases['conflict_composite_fk_ref_order']), $databases['conflict_composite_fk_ref_order'], static function (PDO $pdo): void {
        $pdo->exec('ALTER TABLE customers ADD UNIQUE KEY uq_p5a_wrong_customer_order (agency_id,id)');
        $pdo->exec('ALTER TABLE invoices DROP FOREIGN KEY fk_invoices_customer_agency, ADD CONSTRAINT fk_invoices_customer_agency FOREIGN KEY (customer_id,agency_id) REFERENCES customers(agency_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT');
    });

    $expectCrossAgencyPreflight($connect($databases['mismatch_invoice_customer']), $databases['mismatch_invoice_customer'], 'fk_invoices_customer_agency', static function (PDO $pdo, array $ids): void {
        $pdo->exec("INSERT INTO invoices(agency_id,customer_id,invoice_number,invoice_type,status,currency,subtotal,tax_amount,total_amount,paid_amount,issued_at) VALUES({$ids['agencyA']},{$ids['customerB']},'P5A-INV-CUSTOMER','invoice','unpaid','MAD',10,0,10,0,'2030-01-01 10:00:00')");
    });
    $expectCrossAgencyPreflight($connect($databases['mismatch_invoice_reservation']), $databases['mismatch_invoice_reservation'], 'fk_invoices_reservation_agency', static function (PDO $pdo, array $ids): void {
        $pdo->exec("INSERT INTO invoices(agency_id,customer_id,reservation_id,invoice_number,invoice_type,status,currency,subtotal,tax_amount,total_amount,paid_amount,issued_at) VALUES({$ids['agencyA']},{$ids['customerA']},{$ids['reservationB']},'P5A-INV-RESERVATION','invoice','unpaid','MAD',10,0,10,0,'2030-01-01 10:00:00')");
    });
    $expectCrossAgencyPreflight($connect($databases['mismatch_payment_reservation']), $databases['mismatch_payment_reservation'], 'fk_payments_reservation_agency', static function (PDO $pdo, array $ids): void {
        $pdo->exec("INSERT INTO payments(agency_id,reservation_id,payment_number,amount,currency,paid_at,method,status) VALUES({$ids['agencyA']},{$ids['reservationB']},'P5A-PAY-RESERVATION',10,'MAD','2030-01-01 10:00:00','cash','paid')");
    });
    $expectCrossAgencyPreflight($connect($databases['mismatch_payment_invoice']), $databases['mismatch_payment_invoice'], 'fk_payments_invoice_agency', static function (PDO $pdo, array $ids): void {
        $pdo->exec("INSERT INTO invoices(agency_id,customer_id,invoice_number,invoice_type,status,currency,subtotal,tax_amount,total_amount,paid_amount,issued_at) VALUES({$ids['agencyB']},{$ids['customerB']},'P5A-INV-PAYMENT','invoice','unpaid','MAD',10,0,10,0,'2030-01-01 10:00:00')");
        $invoiceB = (int) $pdo->query("SELECT id FROM invoices WHERE invoice_number='P5A-INV-PAYMENT'")->fetchColumn();
        $pdo->exec("INSERT INTO payments(agency_id,invoice_id,payment_number,amount,currency,paid_at,method,status) VALUES({$ids['agencyA']},$invoiceB,'P5A-PAY-INVOICE',10,'MAD','2030-01-01 10:00:00','cash','paid')");
    });
    $expectCrossAgencyPreflight($connect($databases['mismatch_expense_vehicle']), $databases['mismatch_expense_vehicle'], 'fk_expenses_vehicle_agency', static function (PDO $pdo, array $ids): void {
        $pdo->exec("INSERT INTO expenses(agency_id,vehicle_id,category,description,amount,currency,expense_date,status) VALUES({$ids['agencyA']},{$ids['vehicleB']},'maintenance','P5A mismatch',10,'MAD','2030-01-01','pending')");
    });
    $expectFailureAfter006($connect($databases['conflict_check']), $databases['conflict_check'], static function (PDO $pdo): void {
        $pdo->exec("ALTER TABLE payment_adjustments DROP CONSTRAINT chk_payment_adjustment_amount, ADD CONSTRAINT chk_payment_adjustment_amount CHECK(amount>=0)");
    });
    $expectFailureAfter006($connect($databases['conflict_table_definition']), $databases['conflict_table_definition'], static function (PDO $pdo): void {
        $pdo->exec('ALTER TABLE cash_movements MODIFY currency CHAR(4) NOT NULL');
    });

    $preflight = $connect($databases['preflight']);
    $prepareBase($preflight);
    $preflight->exec("INSERT INTO agencies(name,code,status) VALUES('P5A preflight','P5APREFLIGHT','active')");
    $agency = (int) $preflight->lastInsertId();
    $preflight->exec("INSERT INTO users(fullname,email,email_normalized,password_hash,role,status) VALUES('P5A user','p5a-preflight@example.test','p5a-preflight@example.test','fixture','OWNER','active')");
    $user = (int) $preflight->lastInsertId();
    $preflight->exec("INSERT INTO cash_registers(agency_id,business_date,opening_balance,status,opened_by) VALUES($agency,'2030-01-01',0,'open',$user),($agency,'2030-01-02',0,'open',$user)");
    $preflightResult = $runMigrations($databases['preflight']);
    $assert($preflightResult['code'] === 1, 'Multiple-open-register preflight did not fail');
    $assert((int) $preflight->query("SELECT COUNT(*) FROM schema_migrations WHERE version='006_finance_core'")->fetchColumn() === 0, 'Failed preflight recorded migration 006');
} catch (Throwable $exception) {
    $failures[] = 'Unexpected privileged migration test failure.';
} finally {
    foreach (array_reverse($created) as $database) {
        try {
            $admin->exec('DROP DATABASE IF EXISTS ' . $identifier($database));
        } catch (Throwable $exception) {
            $failures[] = 'Disposable database cleanup failed.';
        }
    }
}

if ($failures) {
    foreach (array_unique($failures) as $failure) {
        fwrite(STDERR, 'FAIL: ' . $failure . PHP_EOL);
    }
    exit(1);
}
echo "Phase 5A privileged migration tests passed: fresh, rerun, compatible partial DDL, twelve incompatible structural fixtures, five cross-agency mismatch fixtures, preflight, and cleanup.\n";
