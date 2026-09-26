<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankStatement extends Model
{
    protected $fillable = [
        'bank_account_id', 'period_id', 'statement_date', 'opening_balance', 'closing_balance', 'status', 'imported_by',
    ];

    protected function casts(): array
    {
        return [
            'statement_date' => 'date',
            'opening_balance' => 'integer',
            'closing_balance' => 'integer',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'period_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class);
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
