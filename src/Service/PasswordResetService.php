<?php

namespace App\Service;

use App\Repository\UserRepository;
use DateTimeImmutable;
use RuntimeException;

/**
 * Replaces send_reset_link.php / reset_password.php, which referenced an
 * undefined $conn variable (connectDB.php only ever defined
 * $mysqlconnection) — password reset was fatally broken before this.
 */
final class PasswordResetService
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function requestReset(string $email): string
    {
        $user = $this->users->findByEmail($email);

        if ($user === null || $user->id === null) {
            throw new RuntimeException('No account found with this email.');
        }

        $token = bin2hex(random_bytes(32));
        $this->users->setPasswordResetToken($user->id, $token, new DateTimeImmutable('+1 hour'));

        return $token;
    }

    public function resetWithToken(string $token, string $newPassword): void
    {
        $user = $this->users->findByValidResetToken($token);

        if ($user === null || $user->id === null) {
            throw new RuntimeException('This password reset link is invalid or has expired.');
        }

        $this->users->resetPassword($user->id, $newPassword);
    }
}
