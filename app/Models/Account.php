<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\NormalBalance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $fillable = [
        'code', 'name', 'type', 'normal_balance', 'parent_id', 'is_postable', 'is_system', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'normal_balance' => NormalBalance::class,
            'is_postable' => 'boolean',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
