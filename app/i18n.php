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

function translationCatalogue($languageCode)
{
    static $catalogues = [];
    $languageCode = in_array($languageCode, supportedLanguages(), true) ? $languageCode : 'en';
    if (!array_key_exists($languageCode, $catalogues)) {
        $path = __DIR__ . '/translations/' . $languageCode . '.php';
        $catalogue = is_file($path) ? require $path : [];
        $catalogues[$languageCode] = is_array($catalogue) ? $catalogue : [];
    }
    return $catalogues[$languageCode];
}

function translations($languageCode = null)
{
    return translationCatalogue($languageCode ?? language());
}

function hasTranslation($key, $languageCode = null)
{
    $catalogue = translationCatalogue($languageCode ?? language());
    return array_key_exists((string) $key, $catalogue) && is_string($catalogue[(string) $key]);
}

function readableTranslationKey($key)
{
    $parts = explode('.', (string) $key);
    $label = str_replace(['_', '-'], ' ', (string) end($parts));
    return ucfirst(trim($label));
}

function t($key, array $parameters = [])
{
    $key = (string) $key;
    $current = translationCatalogue(language());
    $english = translationCatalogue('en');
    $value = $current[$key] ?? $english[$key] ?? readableTranslationKey($key);
    if (!is_string($value)) {
        $value = isset($english[$key]) && is_string($english[$key])
            ? $english[$key]
            : readableTranslationKey($key);
    }
    foreach ($parameters as $name => $replacement) {
        if (!is_scalar($replacement) && $replacement !== null) {
            $replacement = '';
        }
        $value = str_replace(':' . (string) $name, (string) $replacement, $value);
    }
    return $value;
}

function translateInLanguage($key, $language, array $parameters = [])
{
    $language = in_array($language, supportedLanguages(), true) ? $language : 'en';
    $catalogue = translationCatalogue($language);
    $english = $language === 'en' ? $catalogue : translationCatalogue('en');
    $value = $catalogue[$key] ?? $english[$key] ?? readableTranslationKey($key);
    foreach ($parameters as $name => $replacement) {
        if (is_scalar($replacement) || $replacement === null) {
            $value = str_replace(':' . $name, (string) $replacement, (string) $value);
        }
    }
    return (string) $value;
}

function translatedRole($role)
{
    $normalized = normalizeRole($role);
    $keys = [
        ROLE_OWNER => 'role.owner',
        ROLE_AGENCY_MANAGER => 'role.agency_manager',
        ROLE_RENTAL_AGENT => 'role.rental_agent',
        ROLE_ACCOUNTANT => 'role.accountant',
        ROLE_FLEET_AGENT => 'role.fleet_agent',
        ROLE_CUSTOMER => 'role.customer',
    ];
    return isset($keys[$normalized]) ? t($keys[$normalized]) : readableTranslationKey($normalized);
}

function translatedStatus($status)
{
    $normalized = strtolower(trim((string) $status));
    $key = 'status.' . preg_replace('/[^a-z0-9_]+/', '_', $normalized);
    return hasTranslation($key) || hasTranslation($key, 'en')
        ? t($key)
        : ucwords(str_replace('_', ' ', $normalized));
}

function translatedBoolean($value)
{
    return t($value ? 'common.yes' : 'common.no');
}

function translatedValidationMessage($key, array $parameters = [])
{
    $key = str_starts_with((string) $key, 'validation.') ? (string) $key : 'validation.' . $key;
    return t($key, $parameters);
}

function localizedDateObject($value)
{
    if ($value instanceof DateTimeInterface) {
        return DateTimeImmutable::createFromInterface($value);
    }
    if (!is_string($value) || trim($value) === '') {
        return null;
    }
    try {
        return new DateTimeImmutable($value);
    } catch (Throwable $exception) {
        return null;
    }
}

function localizedMonthName($month, $languageCode, $short = true)
{
    $months = [
        'en' => [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec'],
        'fr' => [1=>'janv.',2=>'févr.',3=>'mars',4=>'avr.',5=>'mai',6=>'juin',7=>'juil.',8=>'août',9=>'sept.',10=>'oct.',11=>'nov.',12=>'déc.'],
        'ar' => [1=>'يناير',2=>'فبراير',3=>'مارس',4=>'أبريل',5=>'مايو',6=>'يونيو',7=>'يوليو',8=>'أغسطس',9=>'سبتمبر',10=>'أكتوبر',11=>'نوفمبر',12=>'ديسمبر'],
    ];
    return $months[$languageCode][(int) $month] ?? '';
}

function localizedDate($value, $languageCode = null)
{
    $date = localizedDateObject($value);
    if (!$date) return '';
    $languageCode = in_array($languageCode, supportedLanguages(), true) ? $languageCode : language();
    return $date->format('j') . ' ' . localizedMonthName((int) $date->format('n'), $languageCode) . ' ' . $date->format('Y');
}

function localizedDateTime($value, $languageCode = null)
{
    $date = localizedDateObject($value);
    if (!$date) return '';
    $languageCode = in_array($languageCode, supportedLanguages(), true) ? $languageCode : language();
    $separator = $languageCode === 'fr' ? ' à ' : ($languageCode === 'ar' ? '، ' : ', ');
    return localizedDate($date, $languageCode) . $separator . $date->format('H:i');
}

function localizedMoney($amount, $currency = null, $languageCode = null)
{
    if (!is_numeric($amount)) return '';
    $languageCode = in_array($languageCode, supportedLanguages(), true) ? $languageCode : language();
    $currency = strtoupper((string) ($currency ?? appConfig('currency')));
    if ($languageCode === 'fr') {
        return number_format((float) $amount, 2, ',', ' ') . ' ' . $currency;
    }
    $formatted = number_format((float) $amount, 2, '.', ',');
    return $languageCode === 'en' ? $currency . ' ' . $formatted : $formatted . ' ' . $currency;
}

function languageSwitchUrl($languageCode, $basePath = null, array $query = null)
{
    $languageCode = in_array($languageCode, supportedLanguages(), true) ? $languageCode : 'en';
    $basePath = $basePath ?? (string) ($_SERVER['PHP_SELF'] ?? '');
    $basePath = basename(str_replace('\\', '/', $basePath));
    if ($basePath === '' || !preg_match('/^[A-Za-z0-9_.-]+\.php$/', $basePath)) {
        $basePath = 'index.php';
    }
    $query = $query ?? $_GET;
    $safe = ['lang' => $languageCode];
    foreach ($query as $name => $value) {
        if (!is_string($value)) continue;
        if (in_array($name, ['page','agency_id','customer_id','vehicle_id','reservation_id'], true)
            && ctype_digit($value) && (int) $value > 0) {
            $safe[$name] = (string) (int) $value;
        } elseif (in_array($name, ['status','tab'], true) && preg_match('/^[A-Za-z0-9_-]{1,40}$/', $value)) {
            $safe[$name] = $value;
        } elseif (in_array($name, ['search','q'], true) && strlen($value) <= 100 && !preg_match('/[\x00-\x1F\x7F]/', $value)) {
            $safe[$name] = $value;
        } elseif (in_array($name, ['date_from','date_to','from','to'], true)) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if ($date && $date->format('Y-m-d') === $value) $safe[$name] = $value;
        }
    }
    return $basePath . '?' . http_build_query($safe);
}
