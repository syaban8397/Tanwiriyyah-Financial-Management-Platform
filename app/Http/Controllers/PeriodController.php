<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FinancialPeriod;
use App\Services\AuditLogger;
use App\Services\PeriodGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PeriodController extends Controller
{
    public function index(Request $request, PeriodGuard $periods): Response
    {
        return Inertia::render('Periods/Index', [
            'periods' => FinancialPeriod::query()->orderByDesc('starts_on')->get()->map(fn (FinancialPeriod $period) => [
                'id' => $period->id,
                'name' => $period->name,
                'starts_on' => $period->starts_on->toDateString(),
                'ends_on' => $period->ends_on->toDateString(),
                'status' => $period->status->value,
                'status_label' => $period->status->label(),
                'blockers' => $periods->blockers($period),
            ]),
            'can_open' => $request->user()->hasPermission('PERIOD_OPEN'),
            'can_close' => $request->user()->hasPermission('PERIOD_CLOSE'),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('PERIOD_OPEN'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ]);
        $period = FinancialPeriod::query()->create([...$data, 'status' => 'open']);
        $audit->log($request->user(), 'period.opened', 'financial_period', $period->id, null, null, $data);

        return back()->with('success', 'Periode dibuka.');
    }

    public function close(Request $request, FinancialPeriod $period, PeriodGuard $periods): RedirectResponse
    {
        $periods->close($period, $request->user());

        return back()->with('success', $period->refresh()->status->value === 'locked'
            ? 'Periode dikunci.'
            : 'Penutupan dimulai.');
    }
}
