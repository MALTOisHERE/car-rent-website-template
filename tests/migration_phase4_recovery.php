<?php

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
$migrationPath = $root . '/database/migrations/005_customer_reservation_workspace.sql';
$servicePath = $root . '/app/reservation_service.php';
$migration = (string) file_get_contents($migrationPath);
$service = (string) file_get_contents($servicePath);
$structuralFailures = [];
$structuralAssert = static function ($condition, $message) use (&$structuralFailures) {
    if (!$condition) {
        $structuralFailures[] = $message;
    }
};

$structuralAssert(str_contains($migration, 'ADD COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (id)'), 'missing-id repair is not atomic');
$structuralAssert(!preg_match("/ADD COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST'\\s*[,;]/", $migration), 'standalone AUTO_INCREMENT recovery remains');
$structuralAssert(str_contains($migration, '@p4_id_count') && str_contains($migration, '@p4_primary_columns') && str_contains($migration, 'history primary key exists without id'), 'identifier recovery does not fail closed');
$structuralAssert(str_contains($migration, "@p4_id_type<>'bigint(20) unsigned'") && str_contains($migration, 'history id definition'), 'incompatible identifier definition does not fail closed');
$structuralAssert(str_contains($migration, "@p4_primary_columns IS NOT NULL AND @p4_primary_columns<>'id'") && str_contains($migration, 'history primary key'), 'incompatible primary-key definition does not fail closed');
foreach (['KEY_COLUMN_USAGE', 'REFERENTIAL_CONSTRAINTS', 'TABLE_CONSTRAINTS', 'REFERENCED_TABLE_SCHEMA', 'UPDATE_RULE', 'DELETE_RULE'] as $token) {
    $structuralAssert(str_contains($migration, $token), 'foreign-key metadata validation is incomplete');
}
$structuralAssert(str_contains($migration, 'customer_id,agency_id') && str_contains($migration, 'id,agency_id') && str_contains($migration, '|RESTRICT|RESTRICT'), 'foreign-key ordered definition is not exact');
$structuralAssert(str_contains($migration, 'CHECK_CONSTRAINTS') && str_contains($migration, 'CHECK_CLAUSE'), 'check-clause metadata validation is missing');
foreach ([
    "previous_statusisnullorprevious_statusin(''new'',''regular'',''VIP'',''watchlist'',''blocked'',''archived'')",
    "new_statusin(''new'',''regular'',''VIP'',''watchlist'',''blocked'',''archived'')",
    "action_typein(''migration_baseline'',''created'',''status_changed'',''blocked'',''unblocked'',''archived'')",
    "action_typenotin(''blocked'',''unblocked'',''archived'')orchar_length(trim(coalesce(reason,'''')))between1and255",
] as $expression) {
    $structuralAssert(str_contains($migration, $expression), 'exact check expression is missing');
}
$structuralAssert(!preg_match('/\\bDROP\\s+(?:TABLE|CONSTRAINT|FOREIGN\\s+KEY)\\b/i', $migration), 'migration contains destructive constraint or table recovery');
$structuralAssert(!preg_match('/\\bDELETE\\s+FROM\\s+customer_status_history\\b/i', $migration), 'migration deletes history rows');
$structuralAssert(str_contains($service, 'ORDER BY id ASC FOR UPDATE'), 'replacement locks are not deterministically ordered');
$structuralAssert(str_contains($service, 'lockReservationVehicleRows($reservation[\'vehicle_id\'],$vehicleId'), 'replacement does not lock current and requested vehicles together');
$structuralAssert(str_contains($service, 'rowCount()!==1'), 'vehicle state mutations lack affected-row assertions');

if ($structuralFailures) {
    foreach (array_unique($structuralFailures) as $failure) {
        fwrite(STDERR, 'FAIL: ' . $failure . PHP_EOL);
    }
    exit(1);
}

echo "Phase 4 remediation structural assertions passed.\n";

$required = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_CHARSET'];
foreach ($required as $name) {
    if ((string) getenv($name) === '') {
        fwrite(STDERR, "FAIL: Required database environment is unavailable.\n");
        exit(1);
    }
}

$run = strtolower(bin2hex(random_bytes(5)));
$prefix = 'p4_recovery_' . $run;
$scenarios = [
    'fresh', 'missing_id', 'id_no_auto_pk', 'id_no_pk', 'id_auto_no_pk',
    'bad_id', 'bad_primary', 'bad_fk', 'bad_check',
];
$databases = [];
foreach ($scenarios as $scenario) {
    $databases[$scenario] = $prefix . '_' . $scenario;
}
$charset = preg_match('/^[A-Za-z0-9_]+$/', getenv('DB_CHARSET')) ? getenv('DB_CHARSET') : 'utf8mb4';
$dsn = sprintf('mysql:host=%s;port=%d;charset=%s', getenv('DB_HOST'), (int) getenv('DB_PORT'), $charset);
$admin = null;
$created = [];

try {
    $admin = new PDO($dsn, getenv('DB_USER'), getenv('DB_PASSWORD'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    foreach ($databases as $database) {
        $admin->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $created[] = $database;
    }
} catch (Throwable $exception) {
    if ($admin instanceof PDO) {
        foreach (array_reverse($created) as $database) {
            try {
                $admin->exec("DROP DATABASE IF EXISTS `$database`");
            } catch (Throwable $ignored) {
            }
        }
    }
    fwrite(STDERR, "PENDING: Privileged disposable-database migration scenarios require CREATE DATABASE authority; partial-DDL recovery was not claimed.\n");
    exit(2);
}

$failures = [];
$assert = static function ($condition, $message) use (&$failures) {
    if (!$condition) {
        $failures[] = $message;
    }
};
$connect = static function ($database) use ($dsn) {
    return new PDO($dsn . ';dbname=' . $database, getenv('DB_USER'), getenv('DB_PASSWORD'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
};
$runMigrations = static function ($database) use ($root) {
    $previous = getenv('DB_NAME');
    putenv('DB_NAME=' . $database);
    $pipes = [];
    $process = proc_open(
        [PHP_BINARY, $root . '/bin/migrate.php'],
        [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
        $pipes,
        $root
    );
    if (!is_resource($process)) {
        putenv('DB_NAME=' . $previous);
        return ['code' => 1, 'output' => ''];
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    putenv('DB_NAME=' . $previous);
    return ['code' => $code, 'output' => (string) $stdout];
};
$prepareBase = static function (PDO $pdo) use ($root) {
    foreach (['001_authoritative_schema.sql', '003_operational_extensions.sql', '004_vehicle_detail_media.sql'] as $file) {
        $sql = file_get_contents($root . '/database/migrations/' . $file);
        if ($sql === false) {
            throw new RuntimeException('migration fixture unavailable');
        }
        $pdo->exec($sql);
    }
    $pdo->exec("CREATE TABLE schema_migrations(version VARCHAR(100) PRIMARY KEY,applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("INSERT INTO schema_migrations(version) VALUES('001_authoritative_schema'),('002_import_legacy_data'),('003_operational_extensions'),('004_vehicle_detail_media')");
    $pdo->exec("INSERT INTO agencies(name,code,status) VALUES('P4 migration fixture',CONCAT('P4',SUBSTRING(REPLACE(UUID(),'-',''),1,10)),'active')");
    $agencyId = (int) $pdo->lastInsertId();
    $statement = $pdo->prepare("INSERT INTO customers(agency_id,first_name,last_name,status) VALUES(:agency,'Migration','Fixture','regular')");
    $statement->execute(['agency' => $agencyId]);
    return [$agencyId, (int) $pdo->lastInsertId()];
};
$historyTable = static function ($identifier) {
    $identifierSql = trim((string) $identifier) === '' ? '' : $identifier . ',';
    return "CREATE TABLE customer_status_history($identifierSql
        customer_id BIGINT UNSIGNED NOT NULL,
        agency_id BIGINT UNSIGNED NOT NULL,
        previous_status VARCHAR(30) NULL,
        new_status VARCHAR(30) NOT NULL,
        action_type VARCHAR(30) NOT NULL,
        reason VARCHAR(255) NULL,
        changed_by BIGINT UNSIGNED NULL,
        changed_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        baseline_customer_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN action_type='migration_baseline' THEN customer_id ELSE NULL END) PERSISTENT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
};
$insertHistory = static function (PDO $pdo, $agencyId, $customerId, $id = null) {
    $columns = $id === null ? '' : 'id,';
    $values = $id === null ? '' : ':id,';
    $statement = $pdo->prepare("INSERT INTO customer_status_history({$columns}customer_id,agency_id,previous_status,new_status,action_type,reason) VALUES({$values}:customer,:agency,NULL,'regular','created','survivor')");
    $parameters = ['customer' => $customerId, 'agency' => $agencyId];
    if ($id !== null) {
        $parameters['id'] = $id;
    }
    $statement->execute($parameters);
};
$normalizedCheck = static function ($value) {
    return preg_replace('/[`\\s]+/', '', (string) $value);
};
$definitionAudit = static function (PDO $pdo) use ($assert, $normalizedCheck) {
    $id = $pdo->query("SELECT COLUMN_TYPE,IS_NULLABLE,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_status_history' AND COLUMN_NAME='id'")->fetch(PDO::FETCH_ASSOC);
    $assert($id && $id['COLUMN_TYPE'] === 'bigint(20) unsigned' && $id['IS_NULLABLE'] === 'NO' && str_contains($id['EXTRA'], 'auto_increment'), 'history id definition audit failed');
    $primary = $pdo->query("SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_status_history' AND INDEX_NAME='PRIMARY'")->fetchColumn();
    $assert($primary === 'id', 'history primary-key audit failed');
    $column = $pdo->query("SELECT COLUMN_TYPE,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reservations' AND COLUMN_NAME='tax_rate'")->fetch(PDO::FETCH_ASSOC);
    $assert($column && $column['COLUMN_TYPE'] === 'decimal(5,2)' && $column['IS_NULLABLE'] === 'YES', 'tax_rate definition audit failed');
    $generated = $pdo->query("SELECT GENERATION_EXPRESSION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_status_history' AND COLUMN_NAME='baseline_customer_id'")->fetchColumn();
    $assert(is_string($generated) && stripos($generated, 'migration_baseline') !== false, 'baseline generated-column audit failed');
    $index = $pdo->query("SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX),MIN(NON_UNIQUE) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_status_history' AND INDEX_NAME='uq_customer_status_history_baseline'")->fetch(PDO::FETCH_NUM);
    $assert($index && $index[0] === 'baseline_customer_id' && (int) $index[1] === 0, 'baseline unique-index audit failed');

    $expectedForeignKeys = [
        'fk_customer_status_history_customer_agency' => ['customer_id,agency_id', 'customers', 'id,agency_id', 'RESTRICT', 'RESTRICT'],
        'fk_customer_status_history_agency' => ['agency_id', 'agencies', 'id', 'RESTRICT', 'RESTRICT'],
        'fk_customer_status_history_user' => ['changed_by', 'users', 'id', 'RESTRICT', 'RESTRICT'],
    ];
    foreach ($expectedForeignKeys as $name => $expected) {
        $quoted = $pdo->quote($name);
        $row = $pdo->query("SELECT tc.CONSTRAINT_TYPE,GROUP_CONCAT(k.COLUMN_NAME ORDER BY k.ORDINAL_POSITION),MAX(k.REFERENCED_TABLE_SCHEMA),MAX(k.REFERENCED_TABLE_NAME),GROUP_CONCAT(k.REFERENCED_COLUMN_NAME ORDER BY k.ORDINAL_POSITION),MAX(rc.UPDATE_RULE),MAX(rc.DELETE_RULE) FROM information_schema.TABLE_CONSTRAINTS tc JOIN information_schema.KEY_COLUMN_USAGE k ON k.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA AND k.TABLE_NAME=tc.TABLE_NAME AND k.CONSTRAINT_NAME=tc.CONSTRAINT_NAME JOIN information_schema.REFERENTIAL_CONSTRAINTS rc ON rc.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA AND rc.TABLE_NAME=tc.TABLE_NAME AND rc.CONSTRAINT_NAME=tc.CONSTRAINT_NAME WHERE tc.CONSTRAINT_SCHEMA=DATABASE() AND tc.TABLE_NAME='customer_status_history' AND tc.CONSTRAINT_NAME=$quoted GROUP BY tc.CONSTRAINT_TYPE")->fetch(PDO::FETCH_NUM);
        $rules = $row ? array_map(static function ($value) { return $value === 'NO ACTION' ? 'RESTRICT' : $value; }, [$row[5], $row[6]]) : [];
        $assert($row && $row[0] === 'FOREIGN KEY' && $row[1] === $expected[0] && $row[2] === $pdo->query('SELECT DATABASE()')->fetchColumn() && $row[3] === $expected[1] && $row[4] === $expected[2] && $rules === [$expected[3], $expected[4]], 'exact foreign-key audit failed');
    }

    $expectedChecks = [
        'chk_customer_history_previous_status' => "previous_statusisnullorprevious_statusin('new','regular','VIP','watchlist','blocked','archived')",
        'chk_customer_history_new_status' => "new_statusin('new','regular','VIP','watchlist','blocked','archived')",
        'chk_customer_history_action' => "action_typein('migration_baseline','created','status_changed','blocked','unblocked','archived')",
        'chk_customer_history_reason' => "action_typenotin('blocked','unblocked','archived')orchar_length(trim(coalesce(reason,'')))between1and255",
    ];
    foreach ($expectedChecks as $name => $expected) {
        $quoted = $pdo->quote($name);
        $row = $pdo->query("SELECT tc.CONSTRAINT_TYPE,cc.CHECK_CLAUSE FROM information_schema.TABLE_CONSTRAINTS tc JOIN information_schema.CHECK_CONSTRAINTS cc ON cc.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA AND cc.TABLE_NAME=tc.TABLE_NAME AND cc.CONSTRAINT_NAME=tc.CONSTRAINT_NAME WHERE tc.CONSTRAINT_SCHEMA=DATABASE() AND tc.TABLE_NAME='customer_status_history' AND tc.CONSTRAINT_NAME=$quoted")->fetch(PDO::FETCH_NUM);
        $assert($row && $row[0] === 'CHECK' && $normalizedCheck($row[1]) === $expected, 'exact check-expression audit failed');
    }
};

try {
    $fresh = $runMigrations($databases['fresh']);
    $assert($fresh['code'] === 0 && str_contains($fresh['output'], 'APPLY 005_customer_reservation_workspace') && str_contains($fresh['output'], 'Migrations complete.'), 'fresh 001-005 migration failed');
    $freshRerun = $runMigrations($databases['fresh']);
    $assert($freshRerun['code'] === 0 && str_contains($freshRerun['output'], 'SKIP 005_customer_reservation_workspace (already applied)'), 'fresh immediate rerun failed');
    $definitionAudit($connect($databases['fresh']));

    $successfulPartials = [
        'missing_id' => ['', null],
        'id_no_auto_pk' => ['id BIGINT UNSIGNED NOT NULL PRIMARY KEY', 41],
        'id_no_pk' => ['id BIGINT UNSIGNED NOT NULL', 51],
        'id_auto_no_pk' => ['id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, UNIQUE KEY uq_partial_id(id)', 61],
    ];
    foreach ($successfulPartials as $scenario => [$identifier, $survivorId]) {
        $pdo = $connect($databases[$scenario]);
        [$agencyId, $customerId] = $prepareBase($pdo);
        $pdo->exec($historyTable($identifier));
        $insertHistory($pdo, $agencyId, $customerId, $survivorId);
        $result = $runMigrations($databases[$scenario]);
        $assert($result['code'] === 0 && str_contains($result['output'], 'APPLY 005_customer_reservation_workspace'), "$scenario recovery failed");
        $definitionAudit($pdo);
        $survivor = $pdo->prepare("SELECT COUNT(*) FROM customer_status_history WHERE customer_id=:customer AND action_type='created' AND reason='survivor'");
        $survivor->execute(['customer' => $customerId]);
        $assert((int) $survivor->fetchColumn() === 1, "$scenario rewrote or deleted an existing history row");
        $pdo->exec("DELETE FROM schema_migrations WHERE version='005_customer_reservation_workspace'");
        $retry = $runMigrations($databases[$scenario]);
        $assert($retry['code'] === 0 && str_contains($retry['output'], 'APPLY 005_customer_reservation_workspace'), "$scenario post-DDL retry failed");
        $baseline = $pdo->prepare("SELECT COUNT(*) FROM customer_status_history WHERE customer_id=:customer AND action_type='migration_baseline'");
        $baseline->execute(['customer' => $customerId]);
        $assert((int) $baseline->fetchColumn() === 1, "$scenario duplicated baseline history");
        $rerun = $runMigrations($databases[$scenario]);
        $assert($rerun['code'] === 0 && str_contains($rerun['output'], 'SKIP 005_customer_reservation_workspace (already applied)'), "$scenario immediate rerun failed");
    }

    $pdo = $connect($databases['bad_id']);
    [$agencyId, $customerId] = $prepareBase($pdo);
    $pdo->exec($historyTable('id INT UNSIGNED NOT NULL PRIMARY KEY'));
    $insertHistory($pdo, $agencyId, $customerId, 71);
    $badIdentifier = $runMigrations($databases['bad_id']);
    $assert($badIdentifier['code'] === 1, 'incompatible identifier definition did not fail');
    $assert((int) $pdo->query("SELECT COUNT(*) FROM schema_migrations WHERE version='005_customer_reservation_workspace'")->fetchColumn() === 0, 'incompatible identifier definition was recorded as applied');
    $identifierType = $pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customer_status_history' AND COLUMN_NAME='id'")->fetchColumn();
    $assert($identifierType === 'int(10) unsigned', 'incompatible identifier definition was rewritten');
    $assert((int) $pdo->query("SELECT COUNT(*) FROM customer_status_history WHERE id=71 AND reason='survivor'")->fetchColumn() === 1, 'incompatible identifier failure changed history data');

    $pdo = $connect($databases['bad_primary']);
    [$agencyId, $customerId] = $prepareBase($pdo);
    $pdo->exec($historyTable('id BIGINT UNSIGNED NOT NULL, legacy_key BIGINT UNSIGNED NOT NULL PRIMARY KEY'));
    $insertHistory($pdo, $agencyId, $customerId, 81);
    $badPrimary = $runMigrations($databases['bad_primary']);
    $assert($badPrimary['code'] === 1, 'incompatible primary key did not fail');
    $assert((int) $pdo->query("SELECT COUNT(*) FROM schema_migrations WHERE version='005_customer_reservation_workspace'")->fetchColumn() === 0, 'incompatible primary key was recorded as applied');
    $assert((int) $pdo->query("SELECT COUNT(*) FROM customer_status_history WHERE id=81 AND reason='survivor'")->fetchColumn() === 1, 'incompatible primary-key failure changed history data');

    $pdo = $connect($databases['bad_fk']);
    [$agencyId, $customerId] = $prepareBase($pdo);
    $pdo->exec($historyTable('id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY'));
    $insertHistory($pdo, $agencyId, $customerId);
    $pdo->exec('ALTER TABLE customer_status_history ADD CONSTRAINT fk_customer_status_history_agency FOREIGN KEY (agency_id) REFERENCES agencies(id) ON UPDATE RESTRICT ON DELETE CASCADE');
    $badForeignKey = $runMigrations($databases['bad_fk']);
    $assert($badForeignKey['code'] === 1, 'incompatible same-named foreign key did not fail');
    $assert((int) $pdo->query("SELECT COUNT(*) FROM schema_migrations WHERE version='005_customer_reservation_workspace'")->fetchColumn() === 0, 'incompatible foreign key was recorded as applied');
    $assert((string) $pdo->query("SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_customer_status_history_agency'")->fetchColumn() === 'CASCADE', 'incompatible foreign key was replaced');
    $assert((int) $pdo->query("SELECT COUNT(*) FROM customer_status_history WHERE reason='survivor'")->fetchColumn() === 1, 'incompatible foreign-key failure changed history data');

    $pdo = $connect($databases['bad_check']);
    [$agencyId, $customerId] = $prepareBase($pdo);
    $pdo->exec($historyTable('id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY'));
    $insertHistory($pdo, $agencyId, $customerId);
    $pdo->exec('ALTER TABLE customer_status_history ADD CONSTRAINT chk_customer_history_new_status CHECK (new_status IS NOT NULL)');
    $badCheck = $runMigrations($databases['bad_check']);
    $assert($badCheck['code'] === 1, 'incompatible same-named check did not fail');
    $assert((int) $pdo->query("SELECT COUNT(*) FROM schema_migrations WHERE version='005_customer_reservation_workspace'")->fetchColumn() === 0, 'incompatible check was recorded as applied');
    $assert((int) $pdo->query("SELECT COUNT(*) FROM customer_status_history WHERE reason='survivor'")->fetchColumn() === 1, 'incompatible check failure changed history data');
} catch (Throwable $exception) {
    $failures[] = 'Unexpected migration recovery test failure.';
} finally {
    foreach (array_reverse($created) as $database) {
        try {
            $admin->exec("DROP DATABASE IF EXISTS `$database`");
        } catch (Throwable $exception) {
            $failures[] = 'Disposable migration database cleanup failed.';
        }
    }
}

foreach ($created as $database) {
    $quoted = $admin->quote($database);
    $count = (int) $admin->query("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=$quoted")->fetchColumn();
    $assert($count === 0, 'Disposable migration database remained after cleanup');
}

if ($failures) {
    foreach (array_unique($failures) as $failure) {
        fwrite(STDERR, 'FAIL: ' . $failure . PHP_EOL);
    }
    exit(1);
}

echo "Phase 4 migration recovery tests passed: structural assertions, fresh migration, identifier partial states, exact metadata audits, incompatible-definition failures, idempotent retries, row preservation, and cleanup.\n";
