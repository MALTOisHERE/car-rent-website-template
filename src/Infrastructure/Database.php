<?php

namespace App\Infrastructure;

use PDO;
use Throwable;

/**
 * Lazily-created PDO connection built from the process environment
 * (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD, DB_CHARSET).
 */
final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection === null) {
            self::$connection = self::createConnection();
        }

        return self::$connection;
    }

    private static function createConnection(): PDO
    {
        $host = getenv('DB_HOST');
        $port = getenv('DB_PORT');
        $name = getenv('DB_NAME');
        $user = getenv('DB_USER');
        $password = getenv('DB_PASSWORD');
        $charset = getenv('DB_CHARSET');

        $port = ($port === false || $port === '') ? '3306' : $port;
        $charset = ($charset === false || $charset === '') ? 'utf8mb4' : $charset;

        $missing = [];
        foreach (['DB_HOST' => $host, 'DB_NAME' => $name, 'DB_USER' => $user, 'DB_PASSWORD' => $password] as $key => $value) {
            if ($value === false || ($key !== 'DB_PASSWORD' && $value === '')) {
                $missing[] = $key;
            }
        }

        if ($missing !== []) {
            self::configurationError('Missing required environment variables: ' . implode(', ', $missing));
        }

        if (!ctype_digit((string) $port) || (int) $port < 1 || (int) $port > 65535) {
            self::configurationError('DB_PORT must be an integer between 1 and 65535.');
        }

        if (!preg_match('/^[A-Za-z0-9_]+$/', (string) $charset)) {
            self::configurationError('DB_CHARSET contains unsupported characters.');
        }

        if (strtolower((string) $charset) !== 'utf8mb4') {
            self::configurationError('DB_CHARSET must be utf8mb4.');
        }

        foreach (['DB_HOST' => $host, 'DB_NAME' => $name] as $key => $value) {
            if (preg_match('/[;\x00-\x1F\x7F]/', (string) $value)) {
                self::configurationError($key . ' contains unsupported characters.');
            }
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, (int) $port, $name);

        try {
            return new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (Throwable $exception) {
            DatabaseErrorReporter::report($exception, 'Connection failed');
            self::configurationError('Unable to establish a database connection.');
        }
    }

    /** Stop startup without exposing configuration or connection details. */
    private static function configurationError(string $technicalMessage): void
    {
        error_log('[database] Configuration error: ' . $technicalMessage);

        if (!headers_sent()) {
            http_response_code(500);
        }

        exit('The service is temporarily unavailable. Please try again later.');
    }
}
