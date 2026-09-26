<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\BankAccount;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\FinancialPeriod;
use App\Models\Transaction;
use App\Models\User;
use App\Support\FinanceRules;
use App\Support\Rupiah;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ReconciliationService
{
    public function __construct(
        private BalanceService $balances,
        private AuditLogger $audit,
        private DomainEvents $events,
        private PeriodGuard $periods,
    ) {}

    public function import(User $actor, BankAccount $account, UploadedFile $file, array $data): BankStatement
    {
        if (! $actor->hasPermission('RECONCILIATION_CREATE') || ! $actor->canAccessUnit((int) $account->unit_id)) {
            abort(403);
        }

        $period = $this->periods->forDate($data['statement_date']);
        $rows = $this->parse($file);

        if ($rows === []) {
            FinanceRules::fail('file', 'Berkas tidak berisi baris mutasi.');
        }

        return DB::transaction(function () use ($actor, $account, $data, $period, $rows) {
            $statement = BankStatement::query()->create([
                'bank_account_id' => $account->id,
                'period_id' => $period->id,
                'statement_date' => $data['statement_date'],
                'opening_balance' => (int) $data['opening_balance'],
                'closing_balance' => (int) $data['closing_balance'],
                'status' => 'open',
                'imported_by' => $actor->id,
            ]);

            foreach ($rows as $row) {
                $statement->lines()->create($row);
            }

            $this->audit->log($actor, 'reconciliation.imported', 'bank_statement', $statement->id, (int) $account->unit_id, null, [
                'lines' => count($rows),
                'bank_account_id' => $account->id,
            ]);
            $this->events->publish('reconciliation.imported', (int) $account->unit_id, ['id' => $statement->id]);

            return $statement->load('lines');
        });
    }

    public function match(User $actor, BankStatementLine $line, Transaction $transaction): void
    {
        $line->load('statement.bankAccount');
        $bank = $line->statement->bankAccount;
        $this->guard($actor, $bank);

        if ($line->match_status !== 'unmatched') {
            FinanceRules::fail('line', 'Baris ini sudah diselesaikan.');
        }

        if ($transaction->status !== TransactionStatus::Posted) {
            FinanceRules::fail('transaction', 'Hanya transaksi yang sudah diposting yang dapat dicocokkan.');
        }

        if ((int) $transaction->unit_id !== (int) $bank->unit_id) {
            abort(403);
        }

        $direction = $this->direction($transaction, (int) $bank->id);

        if ($direction === null) {
            FinanceRules::fail('transaction', 'Transaksi ini tidak bergerak pada rekening tersebut.');
        }

        $expected = $direction === 'out' ? -1 * (int) $transaction->amount : (int) $transaction->amount;

        if ($expected !== (int) $line->amount) {
            FinanceRules::fail('amount', 'Nominal tidak sama. Selisih '.Rupiah::format((int) $line->amount - $expected).'.');
        }

        DB::transaction(function () use ($actor, $line, $transaction, $bank) {
            $line->update([
                'match_status' => 'matched',
                'transaction_id' => $transaction->id,
            ]);
            $transaction->update([
                'status' => TransactionStatus::Reconciled,
                'reconciled_at' => now(),
            ]);
            $this->refreshStatement($line->statement);
            $this->audit->log($actor, 'reconciliation.matched', 'bank_statement_line', $line->id, (int) $bank->unit_id, null, [
                'transaction_id' => $transaction->id,
            ]);
            $this->events->publish('reconciliation.matched', (int) $bank->unit_id, [
                'statement_id' => $line->bank_statement_id,
                'transaction_id' => $transaction->id,
            ]);
        });
    }

    public function resolve(User $actor, BankStatementLine $line, string $comment): void
    {
        $line->load('statement.bankAccount');
        $bank = $line->statement->bankAccount;
        $this->guard($actor, $bank);

        if ($line->match_status !== 'unmatched') {
            FinanceRules::fail('line', 'Baris ini sudah diselesaikan.');
        }

        DB::transaction(function () use ($actor, $line, $comment, $bank) {
            $line->update(['match_status' => 'resolved']);
            $this->refreshStatement($line->statement);
            $this->audit->log($actor, 'reconciliation.resolved', 'bank_statement_line', $line->id, (int) $bank->unit_id, null, [
                'comment' => $comment,
            ]);
        });
    }

    public function snapshot(BankStatement $statement): array
    {
        $statement->load('bankAccount.unit', 'lines.transaction', 'period');
        $book = $this->balances->bankBalance($statement->bankAccount);
        $movement = (int) $statement->lines->sum('amount');
        $computedClosing = (int) $statement->opening_balance + $movement;

        return [
            'book_balance' => $book,
            'statement_closing' => (int) $statement->closing_balance,
            'computed_closing' => $computedClosing,
            'statement_difference' => (int) $statement->closing_balance - $computedClosing,
            'book_difference' => (int) $statement->closing_balance - $book,
            'unmatched' => $statement->lines->where('match_status', 'unmatched')->count(),
        ];
    }

    private function refreshStatement(BankStatement $statement): void
    {
        $open = $statement->lines()->where('match_status', 'unmatched')->exists();
        $statement->update(['status' => $open ? 'open' : 'reconciled']);
    }

    private function guard(User $actor, BankAccount $bank): void
    {
        if (! $actor->hasPermission('RECONCILIATION_CREATE') || ! $actor->canAccessUnit((int) $bank->unit_id)) {
            abort(403);
        }
    }

    private function direction(Transaction $transaction, int $bankId): ?string
    {
        if ((int) $transaction->destination_bank_account_id === $bankId) {
            return 'in';
        }

        if ((int) $transaction->bank_account_id !== $bankId || $transaction->payment_method !== PaymentMethod::Bank) {
            return null;
        }

        return match ($transaction->type) {
            TransactionType::Income => 'in',
            TransactionType::Expense, TransactionType::Transfer => 'out',
            TransactionType::Adjustment => $this->adjustmentDirection($transaction),
        };
    }

    private function adjustmentDirection(Transaction $transaction): ?string
    {
        $original = $transaction->original;

        if (! $original) {
            return null;
        }

        $increase = $transaction->effect === 'increase';

        return match ($original->type) {
            TransactionType::Income => $increase ? 'in' : 'out',
            TransactionType::Expense => $increase ? 'out' : 'in',
            default => null,
        };
    }

    private function parse(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            FinanceRules::fail('file', 'Berkas tidak dapat dibaca.');
        }

        $header = fgetcsv($handle);
        $map = [];

        foreach ($header ?: [] as $index => $column) {
            $key = strtolower(trim((string) $column));
            $map[$key] = $index;
        }

        foreach (['date', 'description', 'amount'] as $required) {
            if (! array_key_exists($required, $map)) {
                fclose($handle);
                FinanceRules::fail('file', 'CSV harus memiliki kolom date, description, dan amount.');
            }
        }

        $rows = [];

        while (($csv = fgetcsv($handle)) !== false) {
            if (count(array_filter($csv, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $rawAmount = trim((string) ($csv[$map['amount']] ?? '0'));
            $negative = str_contains($rawAmount, '(') || str_starts_with($rawAmount, '-');
            $amount = abs(Rupiah::parse($rawAmount));

            if ($negative) {
                $amount *= -1;
            }

            $rows[] = [
                'line_date' => trim((string) $csv[$map['date']]),
                'description' => trim((string) $csv[$map['description']]),
                'amount' => $amount,
                'reference' => isset($map['reference']) ? trim((string) ($csv[$map['reference']] ?? '')) : null,
                'match_status' => 'unmatched',
            ];
        }

        fclose($handle);

        return $rows;
    }
}
