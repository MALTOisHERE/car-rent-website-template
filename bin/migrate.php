<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../app/application.php';

$migrationDirectory = dirname(__DIR__) . '/database/migrations';
$files = glob($migrationDirectory . '/*.sql');
if ($files === false) {
    fwrite(STDERR, "FAILED Unable to list migration files.\n");
    exit(1);
}
sort($files, SORT_STRING);

try {
    db()->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            version VARCHAR(100) PRIMARY KEY,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $applied = array_flip(array_column(dbFetchAll('SELECT version FROM schema_migrations'), 'version'));
} catch (Throwable $exception) {
    error_log('[migration] initialization: ' . $exception->getMessage());
    fwrite(STDERR, "FAILED migration initialization. Review the protected server log.\n");
    exit(1);
}
foreach ($files as $file) {
    $version = pathinfo($file, PATHINFO_FILENAME);

    if (isset($applied[$version])) {
        echo "SKIP $version (already applied)\n";
        continue;
    }

    if ($version === '002_import_legacy_data') {
        $legacyTablesExist = tableExists('user')
            && tableExists('car')
            && tableExists('reservation');
        if (!$legacyTablesExist) {
            dbExecute(
                'INSERT INTO schema_migrations (version) VALUES (:version)',
                ['version' => $version]
            );
            $applied[$version] = true;
            echo "SKIP $version (legacy tables not present)\n";
            continue;
        }
    }

    $sql = file_get_contents($file);
    if ($sql === false) {
        fwrite(STDERR, "FAILED $version (migration file is not readable)\n");
        exit(1);
    }

    echo "APPLY $version\n";
    try {
        db()->exec($sql);
        dbExecute(
            'INSERT IGNORE INTO schema_migrations (version) VALUES (:version)',
            ['version' => $version]
        );
        $applied[$version] = true;
    } catch (Throwable $exception) {
        error_log('[migration] ' . $version . ': ' . $exception->getMessage());
        fwrite(STDERR, "FAILED $version. Review the protected server log.\n");
        exit(1);
    }
}

echo "Migrations complete.\n";
