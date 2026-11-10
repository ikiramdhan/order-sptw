<?php

namespace App\Support;

class Fmt
{
    /** 1234567 -> "Rp 1.234.567" */
    public static function rp(int|float|null $n): string
    {
        $n = (int) round($n ?? 0);

        return ($n < 0 ? '-' : '').'Rp '.number_format(abs($n), 0, ',', '.');
    }

    /** 12.500 -> "12,5"; 80.000 -> "80" */
    public static function qty(int|float|string|null $n): string
    {
        $n = (float) $n;
        $s = rtrim(rtrim(number_format($n, 3, ',', '.'), '0'), ',');

        return $s === '' ? '0' : $s;
    }

    /** "2 Dus (80 PCS)"; bila satuan sama dengan satuan dasar: "80 PCS" */
    public static function qtyBoth(float|string $qtyInput, string $unit, float|string $qtyBase, string $baseUnit): string
    {
        if ($unit === $baseUnit) {
            return self::qty($qtyBase).' '.$baseUnit;
        }

        return self::qty($qtyInput).' '.$unit.' ('.self::qty($qtyBase).' '.$baseUnit.')';
    }

    public static function date($date, string $format = 'j M Y'): string
    {
        return $date ? \Illuminate\Support\Carbon::parse($date)->translatedFormat($format) : '-';
    }
}
