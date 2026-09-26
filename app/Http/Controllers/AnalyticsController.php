<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FinancialPeriod;
use App\Models\Transaction;
use App\Models\Unit;
use App\Services\BalanceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function expenses(Request $request, BalanceService $balances): Response
    {
        $period = FinancialPeriod::query()->find($request->input('period_id'))
            ?? FinancialPeriod::query()->whereDate('starts_on', '<=', today())->whereDate('ends_on', '>=', today())->first()
            ?? FinancialPeriod::query()->orderByDesc('starts_on')->firstOrFail();

        $unitId = $request->filled('unit_id') ? (int) $request->input('unit_id') : null;

        if ($unitId && ! $request->user()->canAccessUnit($unitId)) {
            abort(403);
        }

        if ($request->user()->unit_id !== null) {
            $unitId = (int) $request->user()->unit_id;
        }

        $start = $period->starts_on->toDateString();
        $end = $period->ends_on->toDateString();
        $level = 'units';
        $rows = [];

        if (! $unitId) {
            $totals = $balances->byUnit('expense', $start, $end, null);
            $rows = Unit::query()->orderBy('code')->get()->map(fn (Unit $unit) => [
                'id' => $unit->id,
                'label' => $unit->code.' · '.$unit->short_name,
                'amount' => (int) ($totals[$unit->id] ?? 0),
                'href' => '/analytics/expenses?period_id='.$period->id.'&unit_id='.$unit->id,
            ])->filter(fn ($row) => $row['amount'] !== 0)->values();
        } elseif (! $request->filled('account_id')) {
            $level = 'accounts';
            $rows = $balances->byAccount('expense', $start, $end, [$unitId])->map(fn ($row) => [
                'id' => $row['id'],
                'label' => $row['code'].' '.$row['name'],
                'amount' => $row['total'],
                'href' => '/analytics/expenses?period_id='.$period->id.'&unit_id='.$unitId.'&account_id='.$row['id'],
            ])->values();
        } else {
            $level = 'transactions';
            $rows = Transaction::query()
                ->where('unit_id', $unitId)
                ->where('category_account_id', (int) $request->input('account_id'))
                ->whereIn('status', ['posted', 'reconciled'])
                ->whereDate('posting_date', '>=', $start)
                ->whereDate('posting_date', '<=', $end)
                ->latest('id')
                ->get()
                ->map(fn (Transaction $transaction) => [
                    'id' => $transaction->id,
                    'label' => $transaction->number.' · '.$transaction->description,
                    'amount' => (int) $transaction->amount,
                    'href' => '/transactions/'.$transaction->id,
                ]);
        }

        return Inertia::render('Analytics/Expenses', [
            'period' => ['id' => $period->id, 'name' => $period->name],
            'periods' => FinancialPeriod::query()->orderByDesc('starts_on')->get(['id', 'name']),
            'level' => $level,
            'rows' => $rows,
            'unit_id' => $unitId,
            'account_id' => $request->input('account_id'),
        ]);
    }
}
