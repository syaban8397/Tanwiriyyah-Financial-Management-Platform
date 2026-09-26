<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\ScopedToUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    use ScopedToUnit;

    protected $fillable = [
        'transaction_id', 'unit_id', 'period_id', 'entry_date', 'description', 'posted_by',
    ];

    protected function casts(): array
    {
        return ['entry_date' => 'date'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'period_id');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }
}
