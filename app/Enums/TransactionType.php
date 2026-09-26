<?php

declare(strict_types=1);

namespace App\Enums;

enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Transfer = 'transfer';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Income => 'Penerimaan',
            self::Expense => 'Pengeluaran',
            self::Transfer => 'Transfer',
            self::Adjustment => 'Penyesuaian',
        };
    }
}
