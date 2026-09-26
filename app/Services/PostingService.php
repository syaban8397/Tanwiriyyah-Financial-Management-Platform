<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\TransactionType;
use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Support\FinanceRules;
use App\Support\Rupiah;

class PostingService
{
    public function __construct(private BalanceService $balances) {}

    public function post(Transaction $transaction, User $actor): JournalEntry
    {
        if ($transaction->journalEntry()->exists()) {
            FinanceRules::fail('status', 'Transaksi ini sudah memiliki jurnal.');
        }

        $this->lockTreasury($transaction);
        $this->assertFunds($transaction);

        $lines = $this->linesFor($transaction);
        $debit = 0;
        $credit = 0;

        foreach ($lines as $line) {
            if (($line['debit'] > 0 && $line['credit'] > 0) || ($line['debit'] === 0 && $line['credit'] === 0)) {
                FinanceRules::fail('journal', 'Baris jurnal tidak valid.');
            }

            $debit += $line['debit'];
            $credit += $line['credit'];
        }

        if ($debit !== $credit || $debit <= 0) {
            FinanceRules::fail('journal', 'Jurnal tidak seimbang.');
        }

        $entry = JournalEntry::query()->create([
            'transaction_id' => $transaction->id,
            'unit_id' => $transaction->unit_id,
            'period_id' => $transaction->period_id,
            'entry_date' => $transaction->posting_date->toDateString(),
            'description' => $transaction->description,
            'posted_by' => $actor->id,
        ]);

        foreach ($lines as $line) {
            $entry->lines()->create($line);
        }

        return $entry->load('lines');
    }

    private function linesFor(Transaction $transaction): array
    {
        return match ($transaction->type) {
            TransactionType::Income => $this->paired($transaction, debitAsset: true),
            TransactionType::Expense => $this->paired($transaction, debitAsset: false),
            TransactionType::Transfer => $this->transfer($transaction),
            TransactionType::Adjustment => $this->adjustment($transaction),
        };
    }

    private function paired(Transaction $transaction, bool $debitAsset): array
    {
        $asset = $this->sourceAsset($transaction);
        $amount = (int) $transaction->amount;

        return [
            $this->line(
                $asset['account_id'],
                (int) $transaction->unit_id,
                $asset['cash_account_id'],
                $asset['bank_account_id'],
                $debitAsset ? $amount : 0,
                $debitAsset ? 0 : $amount,
                $transaction->description,
            ),
            $this->line(
                (int) $transaction->category_account_id,
                (int) $transaction->unit_id,
                null,
                null,
                $debitAsset ? 0 : $amount,
                $debitAsset ? $amount : 0,
                $transaction->description,
            ),
        ];
    }

    private function transfer(Transaction $transaction): array
    {
        $source = $this->sourceAsset($transaction);
        $destination = $this->destinationAsset($transaction);
        $amount = (int) $transaction->amount;

        return [
            $this->line(
                $destination['account_id'],
                (int) $transaction->unit_id,
                $destination['cash_account_id'],
                $destination['bank_account_id'],
                $amount,
                0,
                $transaction->description,
            ),
            $this->line(
                $source['account_id'],
                (int) $transaction->unit_id,
                $source['cash_account_id'],
                $source['bank_account_id'],
                0,
                $amount,
                $transaction->description,
            ),
        ];
    }

    private function adjustment(Transaction $transaction): array
    {
        $original = $transaction->original;

        if (! $original) {
            FinanceRules::fail('adjustment', 'Penyesuaian harus merujuk transaksi asal.');
        }

        $creditsAsset = $this->adjustmentCreditsAsset($transaction);
        $synthetic = $transaction->replicate();
        $synthetic->type = $original->type;

        return $this->paired($synthetic, debitAsset: ! $creditsAsset);
    }

    private function adjustmentCreditsAsset(Transaction $transaction): bool
    {
        $original = $transaction->original;
        $increase = $transaction->effect === 'increase';

        if ($original?->type === TransactionType::Expense) {
            return $increase;
        }

        if ($original?->type === TransactionType::Income) {
            return ! $increase;
        }

        FinanceRules::fail('adjustment', 'Jenis transaksi ini tidak dapat disesuaikan.');
    }

    private function assertFunds(Transaction $transaction): void
    {
        $needsFunds = match ($transaction->type) {
            TransactionType::Expense, TransactionType::Transfer => true,
            TransactionType::Adjustment => $this->adjustmentCreditsAsset($transaction),
            default => false,
        };

        if (! $needsFunds) {
            return;
        }

        $balance = $this->sourceBalance($transaction);

        if ($balance < (int) $transaction->amount) {
            FinanceRules::fail('amount', 'Saldo tidak mencukupi. Tersedia '.Rupiah::format($balance).'.');
        }
    }

    private function sourceBalance(Transaction $transaction): int
    {
        if ($transaction->payment_method === PaymentMethod::Cash) {
            $account = CashAccount::query()->findOrFail($transaction->cash_account_id);

            return $this->balances->cashBalance($account);
        }

        $account = BankAccount::query()->findOrFail($transaction->bank_account_id);

        return $this->balances->bankBalance($account);
    }

    private function sourceAsset(Transaction $transaction): array
    {
        if ($transaction->payment_method === PaymentMethod::Cash) {
            $account = $transaction->cashAccount ?? CashAccount::query()->findOrFail($transaction->cash_account_id);

            return [
                'account_id' => (int) $account->account_id,
                'cash_account_id' => (int) $account->id,
                'bank_account_id' => null,
            ];
        }

        $account = $transaction->bankAccount ?? BankAccount::query()->findOrFail($transaction->bank_account_id);

        return [
            'account_id' => (int) $account->account_id,
            'cash_account_id' => null,
            'bank_account_id' => (int) $account->id,
        ];
    }

    private function destinationAsset(Transaction $transaction): array
    {
        if ($transaction->destination_cash_account_id) {
            $account = $transaction->destinationCashAccount ?? CashAccount::query()->findOrFail($transaction->destination_cash_account_id);

            return [
                'account_id' => (int) $account->account_id,
                'cash_account_id' => (int) $account->id,
                'bank_account_id' => null,
            ];
        }

        $account = $transaction->destinationBankAccount ?? BankAccount::query()->findOrFail($transaction->destination_bank_account_id);

        return [
            'account_id' => (int) $account->account_id,
            'cash_account_id' => null,
            'bank_account_id' => (int) $account->id,
        ];
    }

    private function lockTreasury(Transaction $transaction): void
    {
        foreach (array_filter([$transaction->cash_account_id, $transaction->destination_cash_account_id]) as $id) {
            CashAccount::query()->whereKey($id)->lockForUpdate()->first();
        }

        foreach (array_filter([$transaction->bank_account_id, $transaction->destination_bank_account_id]) as $id) {
            BankAccount::query()->whereKey($id)->lockForUpdate()->first();
        }
    }

    private function line(int $accountId, int $unitId, ?int $cashId, ?int $bankId, int $debit, int $credit, string $memo): array
    {
        return [
            'account_id' => $accountId,
            'unit_id' => $unitId,
            'cash_account_id' => $cashId,
            'bank_account_id' => $bankId,
            'debit' => $debit,
            'credit' => $credit,
            'memo' => $memo,
        ];
    }
}
