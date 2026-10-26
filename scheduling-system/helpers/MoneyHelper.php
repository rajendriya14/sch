<?php

namespace Helpers;

class MoneyHelper
{
    /**
     * Format Rupiah tanpa desimal: 1234567.8 → "Rp 1.234.568"
     */
    public static function rupiah(float|int|string|null $value, bool $withPrefix = true): string
    {
        $number = (float) ($value ?? 0);
        $formatted = number_format($number, 0, ',', '.');
        return $withPrefix ? 'Rp ' . $formatted : $formatted;
    }

    /**
     * Ringkas untuk label grafik/kartu: 1.250.000 → "1,25 jt", 950.000 → "950 rb"
     */
    public static function short(float|int|string|null $value): string
    {
        $number = (float) ($value ?? 0);
        $abs = abs($number);

        if ($abs >= 1_000_000_000) {
            return self::trim($number / 1_000_000_000) . ' M';
        }
        if ($abs >= 1_000_000) {
            return self::trim($number / 1_000_000) . ' jt';
        }
        if ($abs >= 1_000) {
            return self::trim($number / 1_000) . ' rb';
        }
        return number_format($number, 0, ',', '.');
    }

    /**
     * Persentase: (a / b) * 100, aman saat b = 0.
     */
    public static function percent(float $part, float $total, int $decimals = 0): float
    {
        if (abs($total) < 0.0001) {
            return 0.0;
        }
        return round(($part / $total) * 100, $decimals);
    }

    /**
     * Teks persentase gaya Indonesia: 16.3 -> "16,3", 100.0 -> "100".
     */
    public static function percentText(float|int|string|null $value, int $decimals = 1): string
    {
        $text = number_format((float) ($value ?? 0), $decimals, ',', '.');
        $text = rtrim(rtrim($text, '0'), ',');
        return $text === '' || $text === '-' ? '0' : $text;
    }

    private static function trim(float $number): string
    {
        $formatted = number_format($number, 2, ',', '.');
        $formatted = rtrim(rtrim($formatted, '0'), ',');
        return $formatted === '' ? '0' : $formatted;
    }
}
