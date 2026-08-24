<?php

namespace App\Service;

use App\Domain\User;
use App\Repository\UserRepository;
use App\Support\Session;

final class AuthService
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function attempt(string $email, string $password): ?User
    {
        $user = $this->users->findByEmail($email);

        if ($user === null || !$user->verifyPassword($password)) {
            return null;
        }

        return $user;
    }

    public function login(User $user): void
    {
        // Regenerate the session id on privilege change to prevent session fixation.
        Session::regenerate();
        Session::set('user_id', $user->id);
        Session::set('username', $user->fullName);
        Session::set('email', $user->email);
        Session::set('role', $user->role->toDatabaseValue());
    }

    public function logout(): void
    {
        Session::destroy();
    }

    public function currentUserId(): ?int
    {
        return Session::get('user_id');
    }

    public function check(): bool
    {
        return Session::has('user_id');
    }
}
