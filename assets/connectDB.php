<?php

require_once __DIR__ . '/../app/bootstrap.php';

/**
 * Central database bootstrap.
 *
 * Database credentials must be supplied by the process environment. This file
 * intentionally preserves the legacy $mysqlconnection variable used by the
 * existing procedural pages.
 */

// Prevent uncaught runtime details from reaching the browser on database-backed pages.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

if (!function_exists('reportDatabaseError')) {
    /** Log technical details server-side and return a browser-safe message. */
    function reportDatabaseError(Throwable $exception, $context = 'Database operation failed')
    {
        error_log(sprintf(
            '[database] %s (%s): %s',
            $context,
            get_class($exception),
            $exception->getMessage()
        ));

        return 'A database error occurred. Please try again later.';
    }
}

if (!function_exists('databaseConfigurationError')) {
    /** Stop startup without exposing configuration or connection details. */
    function databaseConfigurationError($technicalMessage)
    {
        error_log('[database] Configuration error: ' . $technicalMessage);

        if (!headers_sent()) {
            http_response_code(500);
        }

        exit('The service is temporarily unavailable. Please try again later.');
    }
}

$dbHost = getenv('DB_HOST');
$dbPort = getenv('DB_PORT');
$dbName = getenv('DB_NAME');
$dbUser = getenv('DB_USER');
$dbPassword = getenv('DB_PASSWORD');
$dbCharset = getenv('DB_CHARSET');

$dbPort = ($dbPort === false || $dbPort === '') ? '3306' : $dbPort;
$dbCharset = ($dbCharset === false || $dbCharset === '') ? 'utf8mb4' : $dbCharset;

$missingVariables = [];
foreach ([
    'DB_HOST' => $dbHost,
    'DB_NAME' => $dbName,
    'DB_USER' => $dbUser,
    'DB_PASSWORD' => $dbPassword,
] as $variableName => $value) {
    if ($value === false || ($variableName !== 'DB_PASSWORD' && $value === '')) {
        $missingVariables[] = $variableName;
    }
}

if ($missingVariables !== []) {
    databaseConfigurationError('Missing required environment variables: ' . implode(', ', $missingVariables));
}

if (!ctype_digit((string) $dbPort) || (int) $dbPort < 1 || (int) $dbPort > 65535) {
    databaseConfigurationError('DB_PORT must be an integer between 1 and 65535.');
}

if (!preg_match('/^[A-Za-z0-9_]+$/', (string) $dbCharset)) {
    databaseConfigurationError('DB_CHARSET contains unsupported characters.');
}

if (strtolower((string) $dbCharset) !== 'utf8mb4') {
    databaseConfigurationError('DB_CHARSET must be utf8mb4.');
}

$dbCharset = 'utf8mb4';

foreach (['DB_HOST' => $dbHost, 'DB_NAME' => $dbName] as $variableName => $value) {
    if (preg_match('/[;\x00-\x1F\x7F]/', (string) $value)) {
        databaseConfigurationError($variableName . ' contains unsupported characters.');
    }
}

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $dbHost,
    (int) $dbPort,
    $dbName,
    $dbCharset
);

try {
    $mysqlconnection = new PDO($dsn, $dbUser, $dbPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $exception) {
    reportDatabaseError($exception, 'Connection failed');
    databaseConfigurationError('Unable to establish a database connection.');
}
