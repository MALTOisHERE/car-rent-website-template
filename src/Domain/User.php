<?php

namespace App\Domain;

final class User
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $fullName,
        public readonly string $email,
        public readonly ?string $phone,
        public readonly string $passwordHash,
        public readonly UserRole $role
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: isset($row['iduser']) ? (int) $row['iduser'] : null,
            fullName: (string) $row['fullname'],
            email: (string) $row['email'],
            phone: $row['phone'] ?? null,
            passwordHash: (string) $row['password'],
            role: UserRole::fromDatabaseValue($row['role'] ?? null)
        );
    }

    public function verifyPassword(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->passwordHash);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }
}
