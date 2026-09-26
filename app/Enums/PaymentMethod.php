<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Bank = 'bank';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Kas',
            self::Bank => 'Bank',
        };
    }
}
