<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('AUDIT_VIEW'), 403);

        $logs = AuditLog::query()
            ->with(['user:id,name', 'unit:id,code'])
            ->visibleTo($request->user())
            ->when($request->input('q'), function ($query, $term) {
                $query->where(function ($inner) use ($term) {
                    $inner->where('action', 'like', '%'.$term.'%')
                        ->orWhere('entity_type', 'like', '%'.$term.'%');
                });
            })
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (AuditLog $log) => [
                'id' => $log->id,
                'at' => $log->created_at?->toIso8601String(),
                'user' => $log->user?->name,
                'role' => $log->role,
                'unit' => $log->unit?->code,
                'action' => $log->action,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'ip' => $log->ip_address,
            ]);

        return Inertia::render('Audit/Index', [
            'logs' => $logs,
            'filters' => $request->only(['q']),
        ]);
    }
}
