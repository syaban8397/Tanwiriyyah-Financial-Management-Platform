<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\FinancialPeriod;
use App\Models\Unit;
use App\Services\BalanceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LedgerController extends Controller
{
    public function index(Request $request, BalanceService $balances): Response
    {
        $accounts = Account::query()->where('is_postable', true)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'type']);
        $periods = FinancialPeriod::query()->orderByDesc('starts_on')->get();
        $period = $periods->firstWhere('id', (int) $request->input('period_id')) ?? $periods->first(fn ($item) => $item->starts_on->toDateString() <= now()->toDateString() && $item->ends_on->toDateString() >= now()->toDateString()) ?? $periods->first();
        $account = $accounts->firstWhere('id', (int) $request->input('account_id')) ?? $accounts->firstWhere('code', '1100') ?? $accounts->first();
        $unitIds = $balances->unitIds($request->user());

        if ($request->filled('unit_id') && $request->user()->canAccessUnit((int) $request->input('unit_id'))) {
            $unitIds = [(int) $request->input('unit_id')];
        }

        $ledger = $account && $period
            ? $balances->ledger(Account::query()->findOrFail($account->id), $period->starts_on->toDateString(), $period->ends_on->toDateString(), $unitIds)
            : ['opening' => 0, 'debit' => 0, 'credit' => 0, 'closing' => 0, 'rows' => []];

        $units = Unit::query()->whereIn('id', collect($ledger['rows'])->pluck('unit_id')->filter()->unique())->pluck('code', 'id');

        return Inertia::render('Ledger/Index', [
            'accounts' => $accounts,
            'periods' => $periods->map(fn ($item) => ['id' => $item->id, 'name' => $item->name]),
            'filters' => [
                'account_id' => $account?->id,
                'period_id' => $period?->id,
                'unit_id' => $request->input('unit_id'),
            ],
            'ledger' => [
                'opening' => $ledger['opening'],
                'debit' => $ledger['debit'],
                'credit' => $ledger['credit'],
                'closing' => $ledger['closing'],
                'rows' => collect($ledger['rows'])->map(fn ($row) => [
                    ...$row,
                    'date' => $row['date'] instanceof \DateTimeInterface ? $row['date']->format('Y-m-d') : (string) $row['date'],
                    'unit' => $units[$row['unit_id']] ?? null,
                ])->values(),
            ],
        ]);
    }
}
