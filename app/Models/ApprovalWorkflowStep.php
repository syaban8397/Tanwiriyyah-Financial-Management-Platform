<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalWorkflowStep extends Model
{
    public $timestamps = false;

    protected $fillable = ['approval_workflow_id', 'sequence', 'action', 'permission'];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }
}
