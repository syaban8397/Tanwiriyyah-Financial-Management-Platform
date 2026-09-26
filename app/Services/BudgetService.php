<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\BudgetLine;
use App\Models\Setting;
use App\Models\Transaction;
use App\Support\Rupiah;

class BudgetService
{
    public function __construct(
        private BalanceService $balances,
        private Notifier $notifier,
        private DomainEvents $events,
    ) {}

    public function actual(int $unitId, int $periodId, int $accountId, string $start, string $end): int
    {
        return (int) \App\Models\JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.unit_id', $unitId)
            ->where('journal_lines.account_id', $accountId)
            ->where('journal_entries.period_id', $periodId)
            ->whereDate('journal_entries.entry_date', '>=', $start)
            ->whereDate('journal_entries.entry_date', '<=', $end)
            ->selectRaw('COALESCE(SUM(journal_lines.debit),0) - COALESCE(SUM(journal_lines.credit),0) as total')
            ->value('total');
    }

    public function committed(int $unitId, int $periodId, int $accountId): int
    {
        return (int) Transaction::query()
            ->where('unit_id', $unitId)
            ->where('period_id', $periodId)
            ->where('category_account_id', $accountId)
            ->where('type', TransactionType::Expense->value)
            ->whereIn('status', ['submitted', 'under_review', 'approved'])
            ->sum('amount');
    }

    public function present(BudgetLine $line): array
    {
        $line->loadMissing('budget.period', 'account', 'alerts');
        $budget = $line->budget;
        $start = $budget->period->starts_on->toDateString();
        $end = $budget->period->ends_on->toDateString();
        $actual = $this->actual((int) $budget->unit_id, (int) $budget->period_id, (int) $line->account_id, $start, $end);
        $committed = $this->committed((int) $budget->unit_id, (int) $budget->period_id, (int) $line->account_id);
        $planned = (int) $line->planned;
        $remaining = $planned - $actual - $committed;
        $used = $actual + $committed;
        $ratio = $planned > 0 ? (int) round(($used * 100) / $planned) : 0;

        $state = 'normal';
        $stateLabel = 'Normal';

        if ($used > $planned) {
            $state = 'exceeded';
            $stateLabel = 'Melebihi anggaran';
        } elseif ($ratio >= 100) {
            $state = 'exceeded';
            $stateLabel = 'Mencapai batas';
        } elseif ($ratio >= 90) {
            $state = 'critical';
            $stateLabel = 'Mendekati batas';
        } elseif ($ratio >= 75) {
            $state = 'approaching';
            $stateLabel = 'Perlu perhatian';
        }

        return [
            'id' => $line->id,
            'account_id' => $line->account_id,
            'code' => $line->account?->code,
            'name' => $line->account?->name,
            'planned' => $planned,
            'actual' => $actual,
            'committed' => $committed,
            'remaining' => $remaining,
            'ratio' => $ratio,
            'state' => $state,
            'state_label' => $stateLabel,
        ];
    }

    public function evaluate(Transaction $transaction): void
    {
        if (! in_array($transaction->type, [TransactionType::Expense, TransactionType::Adjustment], true)) {
            return;
        }

        if ($transaction->type === TransactionType::Adjustment && $transaction->effect !== 'increase') {
            return;
        }

        $accountId = (int) $transaction->category_account_id;

        if ($accountId === 0) {
            return;
        }

        $line = BudgetLine::query()
            ->with('budget.period', 'account')
            ->where('account_id', $accountId)
            ->whereHas('budget', function ($query) use ($transaction) {
                $query->where('unit_id', $transaction->unit_id)
                    ->where('period_id', $transaction->period_id)
                    ->where('status', 'approved');
            })
            ->first();

        if (! $line || (int) $line->planned <= 0) {
            return;
        }

        $snapshot = $this->present($line);
        $thresholds = array_map('intval', Setting::values('budget_thresholds', [75, 90, 100]));
        sort($thresholds);

        foreach ($thresholds as $threshold) {
            if ($snapshot['ratio'] < $threshold && ! ($threshold === 100 && $snapshot['state'] === 'exceeded')) {
                continue;
            }

            if ($snapshot['ratio'] < $threshold) {
                continue;
            }

            $alert = $line->alerts()->firstOrCreate(
                ['threshold' => $threshold],
                ['created_at' => now()],
            );

            if ($alert->wasRecentlyCreated) {
                $body = ($line->account?->name ?? 'Akun').' '.($line->budget->unit?->code ?? '').' mencapai '.$threshold.'% ('
                    .Rupiah::format($snapshot['actual']).' dari '.Rupiah::format($snapshot['planned']).').';
                $this->notifier->budget((int) $transaction->unit_id, $body, '/budgets/'.$line->budget_id);
                $this->events->publish('budget.alert', (int) $transaction->unit_id, [
                    'budget_id' => $line->budget_id,
                    'threshold' => $threshold,
                    'account' => $line->account?->code,
                ]);
            }
        }
    }
}
