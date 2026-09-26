<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Approval;
use App\Models\BankAccount;
use App\Models\Budget;
use App\Models\CashAccount;
use App\Models\FinancialPeriod;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;

class DashboardService
{
    public function __construct(private BalanceService $balances, private BudgetService $budgets, private PeriodGuard $periods) {}

    public function compose(User $user): array
    {
        $period = $this->currentPeriod();
        $unitIds = $this->balances->unitIds($user);
        $position = $period
            ? $this->balances->position($period, $unitIds)
            : ['revenue' => 0, 'expense' => 0, 'net' => 0];

        $prior = $this->priorPeriod($period);
        $priorPosition = $prior
            ? $this->balances->position($prior, $unitIds)
            : ['revenue' => 0, 'expense' => 0, 'net' => 0];

        $cashAccounts = CashAccount::query()->with('unit')->inContext($user)->where('is_active', true)->get();
        $bankAccounts = BankAccount::query()->with('unit')->inContext($user)->where('is_active', true)->get();
        $cashBalances = $this->balances->cashBalances($cashAccounts);
        $bankBalances = $this->balances->bankBalances($bankAccounts);

        return [
            'period' => $period ? $this->periodPayload($period) : null,
            'prior_period' => $prior?->name,
            'scope' => $this->scopeLabel($user),
            'position' => $position,
            'prior' => $priorPosition,
            'cash' => array_sum($cashBalances),
            'bank' => array_sum($bankBalances),
            'cash_accounts' => $cashAccounts->map(fn (CashAccount $account) => [
                'id' => $account->id,
                'name' => $account->name,
                'unit' => $account->unit?->code,
                'balance' => $cashBalances[$account->id] ?? 0,
            ])->values(),
            'bank_accounts' => $bankAccounts->map(fn (BankAccount $account) => [
                'id' => $account->id,
                'name' => $account->bank_name,
                'unit' => $account->unit?->code,
                'masked' => $account->masked_number,
                'balance' => $bankBalances[$account->id] ?? 0,
            ])->values(),
            'trend' => $this->trend($user, $period),
            'units' => $user->unit_id === null ? $this->units($period, $unitIds) : [],
            'activity' => $this->activity($user),
            'queue' => $this->queue($user),
            'alerts' => $this->alerts($user, $period),
            'blockers' => $period ? $this->periods->blockers($period) : [],
        ];
    }

    private function currentPeriod(): ?FinancialPeriod
    {
        return FinancialPeriod::query()
            ->whereDate('starts_on', '<=', today())
            ->whereDate('ends_on', '>=', today())
            ->first()
            ?? FinancialPeriod::query()->orderByDesc('starts_on')->first();
    }

    private function priorPeriod(?FinancialPeriod $period): ?FinancialPeriod
    {
        if (! $period) {
            return null;
        }

        return FinancialPeriod::query()
            ->whereDate('ends_on', '<', $period->starts_on->toDateString())
            ->orderByDesc('starts_on')
            ->first();
    }

    private function periodPayload(FinancialPeriod $period): array
    {
        return [
            'id' => $period->id,
            'name' => $period->name,
            'status' => $period->status->value,
            'status_label' => $period->status->label(),
            'starts_on' => $period->starts_on->toDateString(),
            'ends_on' => $period->ends_on->toDateString(),
        ];
    }

    private function scopeLabel(User $user): string
    {
        if ($user->unit_id !== null) {
            return $user->unit?->name ?? 'Unit';
        }

        $context = session('context_unit_id');

        if ($context) {
            return Unit::query()->find($context)?->name ?? 'Unit';
        }

        return 'Konsolidasi yayasan';
    }

    private function trend(User $user, ?FinancialPeriod $current): array
    {
        if (! $current) {
            return [];
        }

        $periods = FinancialPeriod::query()
            ->whereDate('starts_on', '<=', $current->starts_on->toDateString())
            ->orderByDesc('starts_on')
            ->limit(6)
            ->get()
            ->sortBy('starts_on')
            ->values();

        $unitIds = $this->balances->unitIds($user);

        return $periods->map(function (FinancialPeriod $period) use ($unitIds) {
            $position = $this->balances->position($period, $unitIds);

            return [
                'label' => $period->name,
                'revenue' => $position['revenue'],
                'expense' => $position['expense'],
                'net' => $position['net'],
            ];
        })->all();
    }

    private function units(?FinancialPeriod $period, ?array $unitIds): array
    {
        if (! $period) {
            return [];
        }

        $start = $period->starts_on->toDateString();
        $end = $period->ends_on->toDateString();
        $revenue = $this->balances->byUnit('income', $start, $end, $unitIds);
        $expense = $this->balances->byUnit('expense', $start, $end, $unitIds);
        $units = Unit::query()->where('is_active', true)->when($unitIds, fn ($query) => $query->whereIn('id', $unitIds))->orderBy('code')->get();

        return $units->map(function (Unit $unit) use ($revenue, $expense, $period) {
            $in = (int) ($revenue[$unit->id] ?? 0);
            $out = (int) ($expense[$unit->id] ?? 0);
            $budget = Budget::query()->with('lines')->where('unit_id', $unit->id)->where('period_id', $period->id)->where('status', 'approved')->first();
            $utilization = null;

            if ($budget && $budget->lines->sum('planned') > 0) {
                $planned = (int) $budget->lines->sum('planned');
                $utilization = (int) round(($out * 100) / $planned);
            }

            return [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->short_name,
                'revenue' => $in,
                'expense' => $out,
                'net' => $in - $out,
                'utilization' => $utilization,
            ];
        })->all();
    }

    private function activity(User $user): array
    {
        return Approval::query()
            ->with(['actor:id,name', 'transaction:id,number,unit_id,amount,description'])
            ->whereHas('transaction', fn ($query) => $query->inContext($user))
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (Approval $approval) => [
                'id' => $approval->id,
                'action' => $approval->action,
                'comment' => $approval->comment,
                'actor' => $approval->actor?->name,
                'at' => $approval->created_at?->toIso8601String(),
                'transaction_id' => $approval->transaction_id,
                'number' => $approval->transaction?->number,
                'amount' => (int) ($approval->transaction?->amount ?? 0),
                'description' => $approval->transaction?->description,
            ])->all();
    }

    private function queue(User $user): array
    {
        return Transaction::query()
            ->with('unit:id,code')
            ->inContext($user)
            ->whereIn('status', ['submitted', 'under_review', 'approved'])
            ->latest('submitted_at')
            ->limit(6)
            ->get()
            ->map(fn (Transaction $transaction) => [
                'id' => $transaction->id,
                'number' => $transaction->number,
                'unit' => $transaction->unit?->code,
                'description' => $transaction->description,
                'amount' => (int) $transaction->amount,
                'status' => $transaction->status->value,
                'status_label' => $transaction->status->label(),
            ])->all();
    }

    private function alerts(User $user, ?FinancialPeriod $period): array
    {
        if (! $period) {
            return [];
        }

        $alerts = [];
        $budgets = Budget::query()->with('lines.account', 'lines.alerts', 'unit')->inContext($user)->where('period_id', $period->id)->where('status', 'approved')->get();

        foreach ($budgets as $budget) {
            foreach ($budget->lines as $line) {
                $presented = $this->budgets->present($line);

                if ($presented['state'] === 'normal') {
                    continue;
                }

                $alerts[] = [
                    'unit' => $budget->unit?->code,
                    'account' => $presented['name'],
                    'state' => $presented['state'],
                    'label' => $presented['state_label'],
                    'ratio' => $presented['ratio'],
                    'href' => '/budgets/'.$budget->id,
                ];
            }
        }

        return array_slice($alerts, 0, 6);
    }
}
