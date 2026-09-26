<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    protected $fillable = [
        'transaction_id', 'disk', 'path', 'original_name', 'mime', 'size', 'kind', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'kind' => DocumentKind::class,
            'size' => 'integer',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
