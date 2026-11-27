<?php

namespace App\Shop;

class Money
{
    public static function format(int $cents, ?string $currency = null): string
    {
        $currency ??= ShopSettings::currency();
        $amount = number_format($cents / 100, 2, ',', '.');

        return $currency === 'EUR' ? $amount.' €' : $currency.' '.$amount;
    }

    /** "12,50" oder "12.50" oder "12" in Cent umrechnen */
    public static function parse(string|int|float|null $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $v = trim((string) $value);
        if (str_contains($v, ',')) {
            $v = str_replace(['.', ','], ['', '.'], $v);
        }
        if (! is_numeric($v)) {
            return null;
        }

        return (int) round(((float) $v) * 100);
    }

    /** Im Bruttobetrag enthaltene Steuer */
    public static function includedTax(int $grossCents, float $rate): int
    {
        return $rate > 0 ? (int) round($grossCents * $rate / (100 + $rate)) : 0;
    }
}
