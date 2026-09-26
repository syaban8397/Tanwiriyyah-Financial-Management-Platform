<?php

declare(strict_types=1);

namespace App\Enums;

enum PeriodStatus: string
{
    case Open = 'open';
    case Closing = 'closing';
    case Locked = 'locked';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Terbuka',
            self::Closing => 'Penutupan',
            self::Locked => 'Terkunci',
        };
    }

    public function acceptsEntries(): bool
    {
        return $this === self::Open;
    }
}
