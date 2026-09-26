<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetAlert extends Model
{
    public $timestamps = false;

    protected $fillable = ['budget_line_id', 'threshold', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'threshold' => 'integer'];
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(BudgetLine::class, 'budget_line_id');
    }
}
