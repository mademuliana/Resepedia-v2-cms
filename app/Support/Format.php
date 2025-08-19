<?php

namespace App\Support;

class Format
{
    public static function idr(float|int|null $amount, int $decimals = 2): string
    {
        $n = is_null($amount) ? 0 : (float) $amount;
        return 'Rp.' . number_format($n, $decimals, '.', ',');
    }

    public static function kcal(float|int|null $amount, int $decimals = 2): string
    {
        $n = is_null($amount) ? 0 : (float) $amount;
        return number_format($n, $decimals, '.', ',') . ' kcal';
    }

    public static function pcs(float|int|null $amount): string
    {
        $n = is_null($amount) ? 0 : (float) $amount;
        return $n . ' pcs';
    }

    public static function minute(float|int|null $amount): string
    {
        $n = is_null($amount) ? 0 : (float) $amount;
        return $n . ' minute';
    }

    public static function qty(float|int|null $amount, ?string $unit = null, int $decimals = 0): string
    {
        $n = is_null($amount) ? 0 : (float) $amount;
        return rtrim(rtrim(number_format($n, $decimals, '.', ','), '0'), '.') . ($unit ? " {$unit}" : '');
    }
}
