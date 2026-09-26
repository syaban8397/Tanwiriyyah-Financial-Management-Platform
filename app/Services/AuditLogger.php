<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogger
{
    public function log(
        ?User $user,
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?int $unitId = null,
        ?array $old = null,
        ?array $new = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'user_id' => $user?->id,
            'role' => $user?->primaryRoleLabel(),
            'unit_id' => $unitId ?? $user?->unit_id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 1000),
            'created_at' => now(),
        ]);
    }
}
