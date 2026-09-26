<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\ScopedToUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalLine extends Model
{
    use ScopedToUnit;

    public $timestamps = false;

    protected $fillable = [
        'journal_entry_id', 'account_id', 'unit_id', 'cash_account_id', 'bank_account_id',
        'debit', 'credit', 'memo',
    ];

    protected function casts(): array
    {
        return [
            'debit' => 'integer',
            'credit' => 'integer',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
