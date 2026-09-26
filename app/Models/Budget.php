<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\ScopedToUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Budget extends Model
{
    use ScopedToUnit;

    protected $fillable = [
        'unit_id', 'period_id', 'name', 'status', 'created_by', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'period_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
