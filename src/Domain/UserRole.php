<?php

namespace App\Domain;

/**
 * The `user.role` column is stored inconsistently across existing data
 * (the string 'admin', or the numeric strings '0'/'1') — this is the one
 * place that interprets it, instead of every admin page re-checking
 * `$_SESSION['role'] == 0` inline.
 */
enum UserRole: string
{
    case Customer = 'customer';
    case Admin = 'admin';

    public static function fromDatabaseValue(?string $value): self
    {
        $normalized = strtolower(trim((string) $value));

        return match (true) {
            $normalized === '' || $normalized === '0' => self::Customer,
            default => self::Admin,
        };
    }

    public function toDatabaseValue(): string
    {
        return $this === self::Admin ? 'admin' : '0';
    }
}
