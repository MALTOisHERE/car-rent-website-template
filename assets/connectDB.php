<?php

/**
 * Central database bootstrap.
 *
 * Thin shim over App\Infrastructure\Database so the existing procedural
 * pages can keep doing `include("../assets/connectDB.php")` and using the
 * legacy $mysqlconnection variable and reportDatabaseError() helper. New
 * code should use App\Infrastructure\Database::connection() directly.
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Infrastructure\Database;
use App\Infrastructure\DatabaseErrorReporter;

if (!function_exists('reportDatabaseError')) {
    function reportDatabaseError(Throwable $exception, $context = 'Database operation failed')
    {
        return DatabaseErrorReporter::report($exception, $context);
    }
}

$mysqlconnection = Database::connection();
