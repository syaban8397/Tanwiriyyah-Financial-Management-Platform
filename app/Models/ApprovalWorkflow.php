<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalWorkflow extends Model
{
    protected $fillable = ['name', 'subject'];

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalWorkflowStep::class)->orderBy('sequence');
    }
}
