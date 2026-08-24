<?php

namespace App\Infrastructure;

use Throwable;

final class DatabaseErrorReporter
{
    /** Log technical details server-side and return a browser-safe message. */
    public static function report(Throwable $exception, string $context = 'Database operation failed'): string
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
