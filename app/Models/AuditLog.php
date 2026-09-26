<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\ScopedToUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use ScopedToUnit;

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'role', 'unit_id', 'action', 'entity_type', 'entity_id',
        'old_values', 'new_values', 'ip_address', 'user_agent', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
