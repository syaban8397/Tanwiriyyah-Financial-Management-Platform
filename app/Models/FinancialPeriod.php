<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PeriodStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialPeriod extends Model
{
    protected $fillable = [
        'name', 'starts_on', 'ends_on', 'status', 'closed_by', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'closed_at' => 'datetime',
            'status' => PeriodStatus::class,
        ];
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function contains(string $date): bool
    {
        return $this->starts_on->toDateString() <= $date && $this->ends_on->toDateString() >= $date;
    }
}
