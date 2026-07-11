<?php

function normalizedEmail($email)
{
    return strtolower(trim((string) $email));
}

function normalizedPhone($phone)
{
    return preg_replace('/[^0-9]/', '', (string) $phone);
}

function validDateValue($value, $format = 'Y-m-d')
{
    $date = DateTimeImmutable::createFromFormat('!' . $format, (string) $value);
    return $date && $date->format($format) === $value ? $date : null;
}

function validDateTimeValue($value)
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', (string) $value);
    if ($date && $date->format('Y-m-d\TH:i') === $value) {
        return $date;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', (string) $value);
    return $date && $date->format('Y-m-d H:i:s') === $value ? $date : null;
}

function positiveMoney($value)
{
    $value = trim((string) $value);
    if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $value)) {
        return null;
    }
    return centsToMoney(moneyToCents($value));
}

function moneyToCents($value)
{
    $value=trim((string)$value);
    if(!preg_match('/^-?\d{1,12}(?:\.\d{1,2})?$/',$value))return null;
    $negative=str_starts_with($value,'-');$value=ltrim($value,'-');
    [$whole,$fraction]=array_pad(explode('.',$value,2),2,'');
    $fraction=str_pad($fraction,2,'0');$cents=((int)$whole*100)+(int)substr($fraction,0,2);
    return $negative?-$cents:$cents;
}

function centsToMoney($cents)
{
    $cents=(int)$cents;$negative=$cents<0;$cents=abs($cents);
    return($negative?'-':'').intdiv($cents,100).'.'.str_pad((string)($cents%100),2,'0',STR_PAD_LEFT);
}

function percentageToBasisPoints($value)
{
    $value=trim((string)$value);
    if(!preg_match('/^\d{1,3}(?:\.\d{1,2})?$/',$value))return null;
    [$whole,$fraction]=array_pad(explode('.',$value,2),2,'');
    return min(10000,((int)$whole*100)+(int)str_pad($fraction,2,'0'));
}

function percentageOfCents($cents,$basisPoints)
{
    return intdiv(((int)$cents*(int)$basisPoints)+5000,10000);
}

function validateChoice($value, array $allowed, $default = null)
{
    return in_array($value, $allowed, true) ? $value : $default;
}

function paginationParameters($defaultSize = 25, $maximumSize = 100)
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $size = min($maximumSize, max(1, (int) ($_GET['size'] ?? $defaultSize)));
    return [$page, $size, ($page - 1) * $size];
}
