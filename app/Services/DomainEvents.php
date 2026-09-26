<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DomainEvent;

class DomainEvents
{
    public function publish(string $type, ?int $unitId, array $payload): DomainEvent
    {
        return DomainEvent::query()->create([
            'type' => $type,
            'unit_id' => $unitId,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }
}
