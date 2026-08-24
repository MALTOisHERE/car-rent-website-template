<?php

namespace App\I18n;

final class Translator
{
    private const SUPPORTED_LOCALES = ['en', 'fr', 'ar'];
    private const DEFAULT_LOCALE = 'en';

    /** @var array<string, string> */
    private array $strings;

    private function __construct(private readonly string $locale, array $strings)
    {
        $this->strings = $strings;
    }

    public static function forLocale(?string $locale): self
    {
        $locale = in_array($locale, self::SUPPORTED_LOCALES, true) ? $locale : self::DEFAULT_LOCALE;
        $path = dirname(__DIR__, 2) . "/lang/{$locale}.php";
        $strings = is_file($path) ? require $path : [];

        return new self($locale, $strings);
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function isRtl(): bool
    {
        return $this->locale === 'ar';
    }

    public function t(string $key, array $replacements = []): string
    {
        $value = $this->strings[$key] ?? $key;

        foreach ($replacements as $placeholder => $replacement) {
            $value = str_replace(':' . $placeholder, (string) $replacement, $value);
        }

        return $value;
    }
}
