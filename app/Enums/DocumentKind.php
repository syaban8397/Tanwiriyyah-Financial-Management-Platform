<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentKind: string
{
    case Invoice = 'invoice';
    case Receipt = 'receipt';
    case BankProof = 'bank_proof';
    case Contract = 'contract';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Invoice',
            self::Receipt => 'Kuitansi',
            self::BankProof => 'Bukti bank',
            self::Contract => 'Kontrak',
            self::Other => 'Pendukung',
        };
    }
}
