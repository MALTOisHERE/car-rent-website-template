<?php

function supportedLanguages()
{
    return ['en', 'fr', 'ar'];
}

function language()
{
    $language = $_SESSION['lang'] ?? 'en';
    return in_array($language, supportedLanguages(), true) ? $language : 'en';
}

function translations()
{
    static $catalogues = [];
    $language = language();
    if (!isset($catalogues[$language])) {
        $path = __DIR__ . '/translations/' . $language . '.php';
        $catalogues[$language] = is_file($path) ? require $path : [];
    }
    return $catalogues[$language];
}

function t($key, array $replace = [])
{
    $value = translations()[$key] ?? $key;
    foreach ($replace as $name => $replacement) {
        $value = str_replace(':' . $name, (string) $replacement, $value);
    }
    return $value;
}

