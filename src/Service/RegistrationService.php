<?php

namespace App\Service;

use App\Domain\User;
use App\Repository\UserRepository;
use App\Service\Exception\EmailAlreadyRegisteredException;
use RuntimeException;

/**
 * Single source of truth for account creation — previously duplicated
 * (and diverging) between signup.php and confirm_reservation.php.
 */
final class RegistrationService
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function register(string $fullName, string $email, ?string $phone, string $plainPassword): User
    {
        if ($this->users->existsWithEmail($email)) {
            throw new EmailAlreadyRegisteredException('An account with this email already exists.');
        }

        $id = $this->users->create($fullName, $email, $phone, $plainPassword);
        $user = $this->users->find($id);

        if ($user === null) {
            throw new RuntimeException('Failed to load the newly created account.');
        }

        return $user;
    }
}
