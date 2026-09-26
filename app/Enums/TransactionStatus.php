<?php

declare(strict_types=1);

namespace App\Enums;

enum TransactionStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Posted = 'posted';
    case Reconciled = 'reconciled';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Submitted => 'Diajukan',
            self::UnderReview => 'Ditinjau',
            self::Approved => 'Disetujui',
            self::Posted => 'Diposting',
            self::Reconciled => 'Direkonsiliasi',
            self::Rejected => 'Ditolak',
        };
    }

    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Rejected;
    }

    public function isFinal(): bool
    {
        return $this === self::Posted || $this === self::Reconciled;
    }
}
