<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NormalBalance;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\FinancialPeriod;
use App\Models\JournalLine;
use App\Models\User;
use Illuminate\Support\Collection;

class BalanceService
{
    public function cashBalance(CashAccount $account): int
    {
        return (int) $account->opening_balance + $this->netMovement('cash_account_id', (int) $account->id);
    }

    public function bankBalance(BankAccount $account): int
    {
        return (int) $account->opening_balance + $this->netMovement('bank_account_id', (int) $account->id);
    }

    public function cashBalances(Collection $accounts): array
    {
        return $this->balancesFor('cash_account_id', $accounts);
    }

    public function bankBalances(Collection $accounts): array
    {
        return $this->balancesFor('bank_account_id', $accounts);
    }

    public function unitIds(User $user): ?array
    {
        if ($user->unit_id !== null) {
            return [(int) $user->unit_id];
        }

        $context = session('context_unit_id');

        return $context ? [(int) $context] : null;
    }

    public function total(string $accountType, ?array $unitIds, string $start, string $end): int
    {
        if ($unitIds === []) {
            return 0;
        }

        $normal = $accountType === 'expense' ? 'debit' : 'credit';
        $expression = $normal === 'debit'
            ? 'COALESCE(SUM(journal_lines.debit),0) - COALESCE(SUM(journal_lines.credit),0)'
            : 'COALESCE(SUM(journal_lines.credit),0) - COALESCE(SUM(journal_lines.debit),0)';

        $query = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.type', $accountType)
            ->whereDate('journal_entries.entry_date', '>=', $start)
            ->whereDate('journal_entries.entry_date', '<=', $end);

        if ($unitIds !== null) {
            $query->whereIn('journal_lines.unit_id', $unitIds);
        }

        return (int) $query->selectRaw($expression.' as total')->value('total');
    }

    public function byUnit(string $accountType, string $start, string $end, ?array $unitIds = null): Collection
    {
        $expression = $accountType === 'expense'
            ? 'COALESCE(SUM(journal_lines.debit),0) - COALESCE(SUM(journal_lines.credit),0)'
            : 'COALESCE(SUM(journal_lines.credit),0) - COALESCE(SUM(journal_lines.debit),0)';

        $query = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.type', $accountType)
            ->whereDate('journal_entries.entry_date', '>=', $start)
            ->whereDate('journal_entries.entry_date', '<=', $end);

        if ($unitIds !== null) {
            $query->whereIn('journal_lines.unit_id', $unitIds);
        }

        return $query
            ->groupBy('journal_lines.unit_id')
            ->selectRaw('journal_lines.unit_id as unit_id, '.$expression.' as total')
            ->pluck('total', 'unit_id')
            ->map(fn ($value) => (int) $value);
    }

    public function byAccount(string $accountType, string $start, string $end, ?array $unitIds = null): Collection
    {
        $expression = $accountType === 'expense'
            ? 'COALESCE(SUM(journal_lines.debit),0) - COALESCE(SUM(journal_lines.credit),0)'
            : 'COALESCE(SUM(journal_lines.credit),0) - COALESCE(SUM(journal_lines.debit),0)';

        $query = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.type', $accountType)
            ->where('accounts.is_postable', true)
            ->whereDate('journal_entries.entry_date', '>=', $start)
            ->whereDate('journal_entries.entry_date', '<=', $end);

        if ($unitIds !== null) {
            $query->whereIn('journal_lines.unit_id', $unitIds);
        }

        return $query
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name')
            ->selectRaw('accounts.id as id, accounts.code as code, accounts.name as name, '.$expression.' as total')
            ->orderBy('accounts.code')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'code' => $row->code,
                'name' => $row->name,
                'total' => (int) $row->total,
            ]);
    }

    public function ledger(Account $account, string $start, string $end, ?array $unitIds): array
    {
        $openingQuery = $this->ledgerQuery($account, $unitIds)->whereDate('journal_entries.entry_date', '<', $start);
        $openingMovement = $this->signed($account, $openingQuery);
        $opening = $openingMovement;

        $lines = $this->ledgerQuery($account, $unitIds)
            ->whereDate('journal_entries.entry_date', '>=', $start)
            ->whereDate('journal_entries.entry_date', '<=', $end)
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_lines.id')
            ->get([
                'journal_lines.id',
                'journal_lines.debit',
                'journal_lines.credit',
                'journal_lines.memo',
                'journal_lines.unit_id',
                'journal_entries.entry_date',
                'journal_entries.transaction_id',
                'journal_entries.description',
            ]);

        $running = $opening;
        $rows = [];
        $debit = 0;
        $credit = 0;

        foreach ($lines as $line) {
            $debit += (int) $line->debit;
            $credit += (int) $line->credit;
            $running += $this->effect($account, (int) $line->debit, (int) $line->credit);
            $rows[] = [
                'id' => (int) $line->id,
                'date' => $line->entry_date,
                'transaction_id' => (int) $line->transaction_id,
                'description' => $line->description,
                'memo' => $line->memo,
                'unit_id' => (int) $line->unit_id,
                'debit' => (int) $line->debit,
                'credit' => (int) $line->credit,
                'balance' => $running,
            ];
        }

        return [
            'opening' => $opening,
            'debit' => $debit,
            'credit' => $credit,
            'closing' => $running,
            'rows' => $rows,
        ];
    }

    public function trialBalance(string $start, string $end, ?array $unitIds): array
    {
        $query = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.is_postable', true)
            ->whereDate('journal_entries.entry_date', '>=', $start)
            ->whereDate('journal_entries.entry_date', '<=', $end);

        if ($unitIds !== null) {
            $query->whereIn('journal_lines.unit_id', $unitIds);
        }

        $rows = $query
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.normal_balance')
            ->selectRaw('accounts.code as code, accounts.name as name, accounts.normal_balance as normal_balance, COALESCE(SUM(journal_lines.debit),0) as debit, COALESCE(SUM(journal_lines.credit),0) as credit')
            ->orderBy('accounts.code')
            ->get();

        $debit = 0;
        $credit = 0;
        $mapped = [];

        foreach ($rows as $row) {
            $rowDebit = (int) $row->debit;
            $rowCredit = (int) $row->credit;
            $debit += $rowDebit;
            $credit += $rowCredit;
            $mapped[] = [
                'code' => $row->code,
                'name' => $row->name,
                'debit' => $rowDebit,
                'credit' => $rowCredit,
            ];
        }

        return [
            'rows' => $mapped,
            'debit' => $debit,
            'credit' => $credit,
            'balanced' => $debit === $credit,
        ];
    }

    public function position(FinancialPeriod $period, ?array $unitIds): array
    {
        $start = $period->starts_on->toDateString();
        $end = $period->ends_on->toDateString();

        $revenue = $this->total('income', $unitIds, $start, $end);
        $expense = $this->total('expense', $unitIds, $start, $end);

        return [
            'revenue' => $revenue,
            'expense' => $expense,
            'net' => $revenue - $expense,
        ];
    }

    private function balancesFor(string $column, Collection $accounts): array
    {
        if ($accounts->isEmpty()) {
            return [];
        }

        $movement = JournalLine::query()
            ->whereIn($column, $accounts->pluck('id'))
            ->groupBy($column)
            ->selectRaw($column.' as account_id, COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as net')
            ->pluck('net', 'account_id');

        $balances = [];

        foreach ($accounts as $account) {
            $balances[$account->id] = (int) $account->opening_balance + (int) ($movement[$account->id] ?? 0);
        }

        return $balances;
    }

    private function netMovement(string $column, int $id): int
    {
        return (int) JournalLine::query()
            ->where($column, $id)
            ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as net')
            ->value('net');
    }

    private function ledgerQuery(Account $account, ?array $unitIds)
    {
        $query = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.account_id', $account->id);

        if ($unitIds !== null) {
            $query->whereIn('journal_lines.unit_id', $unitIds);
        }

        return $query;
    }

    private function signed(Account $account, $query): int
    {
        $debit = (int) (clone $query)->sum('journal_lines.debit');
        $credit = (int) (clone $query)->sum('journal_lines.credit');

        return $this->effect($account, $debit, $credit);
    }

    private function effect(Account $account, int $debit, int $credit): int
    {
        return $account->normal_balance === NormalBalance::Debit
            ? $debit - $credit
            : $credit - $debit;
    }
}
