<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Budget;
use App\Models\FinancialPeriod;
use App\Services\AuditLogger;
use App\Services\BudgetService;
use App\Services\DomainEvents;
use App\Services\PeriodGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    public function index(Request $request): Response
    {
        $budgets = Budget::query()
            ->with('unit:id,code,name', 'period:id,name')
            ->inContext($request->user())
            ->latest('id')
            ->paginate(12)
            ->through(fn (Budget $budget) => [
                'id' => $budget->id,
                'name' => $budget->name,
                'unit' => $budget->unit?->code,
                'period' => $budget->period?->name,
                'status' => $budget->status,
            ]);

        return Inertia::render('Budgets/Index', [
            'budgets' => $budgets,
            'can_create' => $request->user()->hasPermission('BUDGET_CREATE'),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('BUDGET_CREATE'), 403);

        return Inertia::render('Budgets/Form', [
            'periods' => FinancialPeriod::query()->orderByDesc('starts_on')->get(['id', 'name', 'status']),
            'units' => \App\Models\Unit::query()->when($request->user()->unit_id, fn ($query) => $query->whereKey($request->user()->unit_id))->orderBy('code')->get(['id', 'code', 'name']),
            'accounts' => Account::query()->where('type', 'expense')->where('is_postable', true)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request, AuditLogger $audit, PeriodGuard $periods): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('BUDGET_CREATE'), 403);
        $data = $request->validate([
            'unit_id' => ['required', 'exists:units,id'],
            'period_id' => ['required', 'exists:financial_periods,id'],
            'name' => ['required', 'string', 'max:160'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.account_id' => ['required', 'exists:accounts,id'],
            'lines.*.planned' => ['required', 'integer', 'min:0'],
        ]);
        abort_unless($request->user()->canAccessUnit((int) $data['unit_id']), 403);
        $period = FinancialPeriod::query()->findOrFail($data['period_id']);
        $periods->assertOpen($period);

        $budget = DB::transaction(function () use ($data, $request) {
            $budget = Budget::query()->create([
                'unit_id' => $data['unit_id'],
                'period_id' => $data['period_id'],
                'name' => $data['name'],
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);

            foreach ($data['lines'] as $line) {
                $budget->lines()->create($line);
            }

            return $budget;
        });

        $audit->log($request->user(), 'budget.created', 'budget', $budget->id, (int) $budget->unit_id, null, ['name' => $budget->name]);

        return redirect()->route('budgets.show', $budget)->with('success', 'Anggaran disimpan.');
    }

    public function show(Request $request, Budget $budget, BudgetService $service): Response
    {
        abort_unless($request->user()->canAccessUnit((int) $budget->unit_id), 403);
        $budget->load('unit', 'period', 'lines.account', 'lines.alerts');

        return Inertia::render('Budgets/Show', [
            'budget' => [
                'id' => $budget->id,
                'name' => $budget->name,
                'status' => $budget->status,
                'unit' => $budget->unit?->name,
                'period' => $budget->period?->name,
                'lines' => $budget->lines->map(fn ($line) => $service->present($line))->values(),
            ],
            'can_approve' => $budget->status === 'draft' && $request->user()->hasPermission('BUDGET_APPROVE'),
        ]);
    }

    public function approve(Request $request, Budget $budget, AuditLogger $audit, DomainEvents $events): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('BUDGET_APPROVE'), 403);
        abort_unless($request->user()->canAccessUnit((int) $budget->unit_id), 403);
        $budget->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);
        $audit->log($request->user(), 'budget.approved', 'budget', $budget->id, (int) $budget->unit_id, null, ['status' => 'approved']);
        $events->publish('budget.approved', (int) $budget->unit_id, ['id' => $budget->id]);

        return back()->with('success', 'Anggaran disetujui.');
    }
}
