<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementLine extends Model
{
    protected $fillable = [
        'bank_statement_id', 'line_date', 'description', 'amount', 'reference', 'match_status', 'transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'line_date' => 'date',
            'amount' => 'integer',
        ];
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class, 'bank_statement_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
