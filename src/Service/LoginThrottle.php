<?php

namespace App\Service;

use App\Support\Session;

/**
 * The anti-bruteforce + arithmetic-captcha logic that used to be copy-pasted
 * inline in each language directory's login.php, extracted into one place.
 */
final class LoginThrottle
{
    public function __construct(
        private readonly int $maxAttempts = 3,
        private readonly int $lockoutSeconds = 30,
        private readonly int $captchaAfterAttempts = 2
    ) {
    }

    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function captchaRequired(): bool
    {
        return Session::get('login_attempts', 0) >= $this->captchaAfterAttempts;
    }

    public function ensureCaptchaChallenge(): void
    {
        if (!Session::has('captcha_sum') || $this->captchaRequired()) {
            $a = random_int(1, 15);
            $b = random_int(1, 15);
            Session::set('captcha_sum', $a + $b);
            Session::set('captcha_num1', $a);
            Session::set('captcha_num2', $b);
        }
    }

    public function captchaNumbers(): array
    {
        return [Session::get('captcha_num1'), Session::get('captcha_num2')];
    }

    /** Seconds remaining before a locked-out user may try again, or null if not locked out. */
    public function secondsUntilUnlocked(): ?int
    {
        $attempts = Session::get('login_attempts', 0);
        $lastFailedAt = Session::get('last_failed_time');

        if ($attempts < $this->maxAttempts || $lastFailedAt === null) {
            return null;
        }

        $remaining = $this->lockoutSeconds - (time() - $lastFailedAt);

        return $remaining > 0 ? $remaining : null;
    }

    public function verifyCaptchaIfRequired(?int $submitted): bool
    {
        if (!$this->captchaRequired()) {
            return true;
        }

        return $submitted !== null && $submitted === Session::get('captcha_sum');
    }

    public function recordFailure(): void
    {
        Session::set('login_attempts', Session::get('login_attempts', 0) + 1);
        Session::set('last_failed_time', time());
    }

    public function clear(): void
    {
        Session::remove('login_attempts');
        Session::remove('last_failed_time');
        Session::remove('captcha_sum');
        Session::remove('captcha_num1');
        Session::remove('captcha_num2');
    }

    public function remainingAttempts(): int
    {
        return max(0, $this->maxAttempts - Session::get('login_attempts', 0));
    }
}
