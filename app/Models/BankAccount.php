<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\ScopedToUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankAccount extends Model
{
    use ScopedToUnit;

    protected $fillable = [
        'unit_id', 'account_id', 'bank_name', 'account_name', 'account_number', 'opening_balance', 'is_active',
    ];

    protected $hidden = ['account_number'];

    protected $appends = ['masked_number'];

    protected function casts(): array
    {
        return [
            'account_number' => 'encrypted',
            'opening_balance' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function getMaskedNumberAttribute(): string
    {
        return (string) $this->account_number;
    }
}
