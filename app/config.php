<?php

function envValue($name, $default = null)
{
    $value = getenv($name);
    return ($value === false || $value === '') ? $default : $value;
}

function envInt($name, $default, $minimum = null, $maximum = null)
{
    $value = envValue($name, (string) $default);
    if (!ctype_digit((string) $value)) {
        return $default;
    }

    $value = (int) $value;
    if ($minimum !== null && $value < $minimum) {
        return $default;
    }
    if ($maximum !== null && $value > $maximum) {
        return $default;
    }

    return $value;
}

function appConfig($key = null)
{
    static $config;
    if ($config === null) {
        $config = [
            'name' => envValue('APP_NAME', 'Rental Agency Manager'),
            'environment' => envValue('APP_ENV', 'production'),
            'base_url' => rtrim((string) envValue('APP_BASE_URL', ''), '/'),
            'timezone' => envValue('APP_TIMEZONE', 'Africa/Casablanca'),
            'currency' => envValue('APP_CURRENCY', 'MAD'),
            'session_idle_timeout' => envInt('SESSION_IDLE_TIMEOUT', 1800, 300, 86400),
            'session_absolute_timeout' => envInt('SESSION_ABSOLUTE_TIMEOUT', 43200, 1800, 604800),
            'session_regenerate_interval' => envInt('SESSION_REGENERATE_INTERVAL', 900, 60, 86400),
            'login_max_attempts' => envInt('LOGIN_MAX_ATTEMPTS', 5, 3, 20),
            'login_window_seconds' => envInt('LOGIN_WINDOW_SECONDS', 900, 60, 86400),
            'pending_reservation_minutes' => envInt('PENDING_RESERVATION_MINUTES', 30, 5, 1440),
            'manager_discount_threshold' => envInt('MANAGER_DISCOUNT_THRESHOLD', 10, 0, 100),
            'minimum_driver_age' => envInt('MINIMUM_DRIVER_AGE', 21, 18, 99),
            'minimum_licence_months' => envInt('MINIMUM_LICENCE_MONTHS', 12, 0, 600),
            'upload_max_bytes' => envInt('UPLOAD_MAX_BYTES', 5242880, 1024, 20971520),
        ];
        date_default_timezone_set($config['timezone']);
    }

    return $key === null ? $config : ($config[$key] ?? null);
}
