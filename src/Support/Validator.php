<?php

namespace App\Support;

/**
 * Minimal input validator. Validates and trims — never escapes; escaping
 * for HTML output is the view's job at render time.
 */
final class Validator
{
    /** @var array<string, string[]> */
    private array $errors = [];

    public function requiredString(array $data, string $field, string $label, int $minLength = 1): string
    {
        $value = trim((string) ($data[$field] ?? ''));

        if (mb_strlen($value) < $minLength) {
            $this->errors[$field][] = $label . ' is required.';
        }

        return $value;
    }

    public function email(array $data, string $field = 'email'): string
    {
        $value = trim((string) ($data[$field] ?? ''));

        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $this->errors[$field][] = 'A valid email address is required.';
        }

        return $value;
    }

    public function matches(string $field, string $value, string $other, string $message): void
    {
        if ($value !== $other) {
            $this->errors[$field][] = $message;
        }
    }

    public function fail(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    /** @return array<string, string[]> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $fieldErrors) {
            return $fieldErrors[0];
        }

        return null;
    }
}
