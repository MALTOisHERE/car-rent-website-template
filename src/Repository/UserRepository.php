<?php

namespace App\Repository;

use App\Domain\User;
use App\Domain\UserRole;
use DateTimeImmutable;
use PDO;

final class UserRepository
{
    private const COLUMNS = 'iduser, fullname, email, phone, password, role';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function find(int $id): ?User
    {
        $statement = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM user WHERE iduser = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : User::fromRow($row);
    }

    public function findByEmail(string $email): ?User
    {
        $statement = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM user WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);
        $row = $statement->fetch();

        return $row === false ? null : User::fromRow($row);
    }

    public function existsWithEmail(string $email): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM user WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);

        return $statement->fetchColumn() !== false;
    }

    public function create(string $fullName, string $email, ?string $phone, string $plainPassword, UserRole $role = UserRole::Customer): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO user (fullname, email, phone, password, role) VALUES (:fullname, :email, :phone, :password, :role)'
        );
        $statement->execute([
            'fullname' => $fullName,
            'email' => $email,
            'phone' => $phone,
            'password' => password_hash($plainPassword, PASSWORD_DEFAULT),
            'role' => $role->toDatabaseValue(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function setPasswordResetToken(int $userId, string $token, DateTimeImmutable $expiresAt): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE user SET reset_token = :token, reset_token_expiry = :expiry WHERE iduser = :id'
        );
        $statement->execute([
            'token' => $token,
            'expiry' => $expiresAt->format('Y-m-d H:i:s'),
            'id' => $userId,
        ]);
    }

    public function findByValidResetToken(string $token): ?User
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM user WHERE reset_token = :token AND reset_token_expiry > NOW() LIMIT 1'
        );
        $statement->execute(['token' => $token]);
        $row = $statement->fetch();

        return $row === false ? null : User::fromRow($row);
    }

    public function resetPassword(int $userId, string $plainPassword): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE user SET password = :password, reset_token = NULL, reset_token_expiry = NULL WHERE iduser = :id'
        );
        $statement->execute([
            'password' => password_hash($plainPassword, PASSWORD_DEFAULT),
            'id' => $userId,
        ]);
    }
}
