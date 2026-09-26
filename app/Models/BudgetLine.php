<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetLine extends Model
{
    public $timestamps = false;

    protected $fillable = ['budget_id', 'account_id', 'planned'];

    protected function casts(): array
    {
        return ['planned' => 'integer'];
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(BudgetAlert::class);
    }
}
