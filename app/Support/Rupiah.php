<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final class Rupiah
{
    public static function format(int $amount): string
    {
        $sign = $amount < 0 ? '-' : '';

        return $sign.'Rp '.number_format(abs($amount), 0, ',', '.');
    }

    public static function parse(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            throw new InvalidArgumentException('Nilai uang tidak boleh berasal dari bilangan pecahan.');
        }

        $digits = preg_replace('/[^\d-]/', '', (string) $value) ?? '';

        if ($digits === '' || $digits === '-') {
            return 0;
        }

        return (int) $digits;
    }
}
