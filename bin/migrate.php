<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/application.php';

$migrationDirectory = dirname(__DIR__) . '/database/migrations';
$files = glob($migrationDirectory . '/*.sql'); sort($files, SORT_STRING);
db()->exec("CREATE TABLE IF NOT EXISTS schema_migrations(version VARCHAR(100) PRIMARY KEY,applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$applied = array_flip(array_column(dbFetchAll('SELECT version FROM schema_migrations'), 'version'));
foreach ($files as $file) {
    $version = pathinfo($file, PATHINFO_FILENAME);
    if (isset($applied[$version])) { echo "SKIP $version\n"; continue; }
    if ($version === '002_import_legacy_data'
        && (!tableExists('user') || !tableExists('car') || !tableExists('reservation'))) {
        dbExecute('INSERT IGNORE INTO schema_migrations(version) VALUES(:version)', ['version'=>$version]);
        echo "SKIP $version (legacy tables not present)\n";
        continue;
    }
    $sql = file_get_contents($file);
    if ($sql === false) { throw new RuntimeException('Cannot read migration: ' . $file); }
    echo "APPLY $version\n";
    try {
        db()->exec($sql);
        dbExecute('INSERT IGNORE INTO schema_migrations(version) VALUES(:version)', ['version'=>$version]);
    } catch (Throwable $exception) {
        error_log('[migration] ' . $version . ': ' . $exception->getMessage());
        fwrite(STDERR, "FAILED $version. Review the protected server log. Restore the backup if required.\n");
        exit(1);
    }
}
echo "Migrations complete.\n";
