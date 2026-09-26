<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait ScopedToUnit
{
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->unit_id !== null) {
            $query->where($query->getModel()->getTable().'.unit_id', $user->unit_id);
        }

        return $query;
    }

    public function scopeInContext(Builder $query, User $user): Builder
    {
        $query->visibleTo($user);

        if ($user->unit_id === null) {
            $context = session('context_unit_id');

            if ($context) {
                $query->where($query->getModel()->getTable().'.unit_id', (int) $context);
            }
        }

        return $query;
    }
}
