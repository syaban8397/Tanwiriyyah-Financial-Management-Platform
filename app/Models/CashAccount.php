<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\ScopedToUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashAccount extends Model
{
    use ScopedToUnit;

    protected $fillable = ['unit_id', 'account_id', 'name', 'opening_balance', 'is_active'];

    protected function casts(): array
    {
        return [
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
}
