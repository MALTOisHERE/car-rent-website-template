<?php

namespace App\Support;

/**
 * Session-backed flash messages. Stores raw text — callers must
 * htmlspecialchars() at render time, not when the message is written,
 * so messages are never double-encoded (or double-decoded) on redisplay.
 */
final class Flash
{
    private const SESSION_KEY = 'flash_messages';

    public static function success(string $message): void
    {
        self::add('success', $message);
    }

    public static function error(string $message): void
    {
        self::add('error', $message);
    }

    private static function add(string $type, string $message): void
    {
        $_SESSION[self::SESSION_KEY][$type][] = $message;
    }

    /** @return string[] */
    public static function pull(string $type): array
    {
        $messages = $_SESSION[self::SESSION_KEY][$type] ?? [];
        unset($_SESSION[self::SESSION_KEY][$type]);

        return $messages;
    }
}
