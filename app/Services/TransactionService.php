<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\DocumentKind;
use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\Document;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;
use App\Support\FinanceRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\UniqueConstraintViolationException;

class TransactionService
{
    private const ALLOWED_MIME = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function __construct(
        private PeriodGuard $periods,
        private AuditLogger $audit,
        private DomainEvents $events,
    ) {}

    public function create(User $actor, array $data, array $files = []): Transaction
    {
        $isAdjustment = ($data['type'] ?? null) === TransactionType::Adjustment->value;

        if (! $actor->hasPermission('FINANCE_CREATE') && ! ($isAdjustment && $actor->hasPermission('FINANCE_ADJUST'))) {
            abort(403);
        }

        $stored = [];

        try {
            return DB::transaction(function () use ($actor, $data, $files, &$stored) {
                $existing = Transaction::query()->where('idempotency_key', $data['idempotency_key'])->first();

                if ($existing) {
                    if (! $actor->canAccessUnit((int) $existing->unit_id)) {
                        abort(403);
                    }

                    return $existing;
                }

                $attributes = $this->attributes($actor, $data, null);
                $unit = Unit::query()->findOrFail($attributes['unit_id']);
                $attributes['number'] = $this->nextNumber($unit, $attributes['posting_date']);
                $attributes['created_by'] = $actor->id;
                $attributes['status'] = TransactionStatus::Draft;
                $attributes['idempotency_key'] = $data['idempotency_key'];

                $transaction = Transaction::query()->create($attributes);
                $stored = $this->storeDocuments($transaction, $actor, $files, $data['document_kinds'] ?? []);

                $this->audit->log($actor, 'transaction.created', 'transaction', $transaction->id, (int) $transaction->unit_id, null, [
                    'number' => $transaction->number,
                    'amount' => (int) $transaction->amount,
                    'type' => $transaction->type->value,
                ]);
                $this->events->publish('transaction.created', (int) $transaction->unit_id, [
                    'id' => $transaction->id,
                    'number' => $transaction->number,
                ]);

                return $transaction->refresh();
            });
        } catch (UniqueConstraintViolationException $exception) {
            Storage::disk('local')->delete($stored);
            $existing = Transaction::query()->where('idempotency_key', $data['idempotency_key'])->first();

            if ($existing && $actor->canAccessUnit((int) $existing->unit_id)) {
                return $existing;
            }

            throw $exception;
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($stored);

            throw $exception;
        }
    }

    public function update(User $actor, Transaction $transaction, array $data): Transaction
    {
        if (! $actor->hasPermission('FINANCE_EDIT') || ! $actor->canAccessUnit((int) $transaction->unit_id)) {
            abort(403);
        }

        return DB::transaction(function () use ($actor, $transaction, $data) {
            $current = Transaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();

            if (! $current->status->isEditable()) {
                FinanceRules::fail('status', 'Transaksi yang sudah diposting tidak dapat diubah. Buat penyesuaian.');
            }

            $this->periods->assertOpen($current->period);
            $attributes = $this->attributes($actor, $data, $current);

            if ($current->period_id !== $attributes['period_id']) {
                $period = $this->periods->forDate($attributes['posting_date']);
                $this->periods->assertOpen($period);
            }

            $updated = Transaction::query()
                ->whereKey($current->id)
                ->where('lock_version', $current->lock_version)
                ->update([
                    ...$attributes,
                    'lock_version' => $current->lock_version + 1,
                    'updated_at' => now(),
                ]);

            if ($updated === 0) {
                FinanceRules::fail('lock_version', 'Transaksi baru saja diubah orang lain. Muat ulang lalu coba lagi.');
            }

            $fresh = $current->refresh();
            $this->audit->log($actor, 'transaction.updated', 'transaction', $fresh->id, (int) $fresh->unit_id, null, [
                'amount' => (int) $fresh->amount,
                'description' => $fresh->description,
            ]);

            return $fresh;
        });
    }

    public function destroy(User $actor, Transaction $transaction): void
    {
        if (! $actor->hasPermission('FINANCE_EDIT') || ! $actor->canAccessUnit((int) $transaction->unit_id)) {
            abort(403);
        }

        if ($transaction->status !== TransactionStatus::Draft) {
            FinanceRules::fail('status', 'Hanya draf yang dapat dihapus.');
        }

        DB::transaction(function () use ($actor, $transaction) {
            $this->periods->assertOpen($transaction->period);

            foreach ($transaction->documents as $document) {
                Storage::disk($document->disk)->delete($document->path);
            }

            $this->audit->log($actor, 'transaction.deleted', 'transaction', $transaction->id, (int) $transaction->unit_id, [
                'number' => $transaction->number,
            ], null);
            $transaction->delete();
        });
    }

    public function adjust(User $actor, Transaction $original, array $data): Transaction
    {
        if (! $actor->hasPermission('FINANCE_ADJUST') || ! $actor->canAccessUnit((int) $original->unit_id)) {
            abort(403);
        }

        if (! $original->status->isFinal()) {
            FinanceRules::fail('status', 'Hanya transaksi yang sudah diposting yang memerlukan penyesuaian.');
        }

        if (! in_array($original->type, [TransactionType::Income, TransactionType::Expense], true)) {
            FinanceRules::fail('type', 'Transfer disesuaikan dengan transfer baru, bukan penyesuaian diam-diam.');
        }

        $newAmount = (int) $data['amount'];

        if ($newAmount === (int) $original->amount) {
            FinanceRules::fail('amount', 'Nilai baru sama dengan transaksi asal. Tidak ada yang disesuaikan.');
        }

        return $this->create($actor, [
            'idempotency_key' => $data['idempotency_key'],
            'unit_id' => $original->unit_id,
            'type' => TransactionType::Adjustment->value,
            'transacted_on' => $data['transacted_on'],
            'posting_date' => $data['posting_date'],
            'category_account_id' => $original->category_account_id,
            'payment_method' => $original->payment_method->value,
            'cash_account_id' => $original->cash_account_id,
            'bank_account_id' => $original->bank_account_id,
            'amount' => abs($newAmount - (int) $original->amount),
            'effect' => $newAmount > (int) $original->amount ? 'increase' : 'decrease',
            'description' => $data['description'],
            'reference' => $original->number,
            'original_transaction_id' => $original->id,
        ]);
    }

    public function addDocuments(User $actor, Transaction $transaction, array $files, array $kinds = []): void
    {
        if (! $actor->canAccessUnit((int) $transaction->unit_id) || ! $actor->hasPermission('FINANCE_EDIT')) {
            abort(403);
        }

        if ($transaction->status->isFinal()) {
            FinanceRules::fail('status', 'Dokumen pada transaksi yang sudah diposting tidak dapat ditambah dari formulir biasa.');
        }

        $this->storeDocuments($transaction, $actor, $files, $kinds);
    }

    private function attributes(User $actor, array $data, ?Transaction $current): array
    {
        $type = TransactionType::from($data['type']);
        $unitId = (int) $data['unit_id'];

        if (! $actor->canAccessUnit($unitId)) {
            abort(403);
        }

        $postingDate = (string) ($data['posting_date'] ?: $data['transacted_on']);
        $period = $this->periods->forDate($postingDate);
        $this->periods->assertOpen($period);

        $payment = PaymentMethod::from($data['payment_method']);
        $categoryId = isset($data['category_account_id']) ? (int) $data['category_account_id'] : null;

        if ($type !== TransactionType::Transfer) {
            $this->assertCategory($type, $categoryId, $current);
        }

        $cashId = $payment === PaymentMethod::Cash ? (int) ($data['cash_account_id'] ?? 0) : null;
        $bankId = $payment === PaymentMethod::Bank ? (int) ($data['bank_account_id'] ?? 0) : null;

        if ($payment === PaymentMethod::Cash && ! $cashId) {
            FinanceRules::fail('cash_account_id', 'Pilih akun kas.');
        }

        if ($payment === PaymentMethod::Bank && ! $bankId) {
            FinanceRules::fail('bank_account_id', 'Pilih rekening bank.');
        }

        $this->assertTreasury($unitId, $cashId ?: null, $bankId ?: null);

        $destinationCash = null;
        $destinationBank = null;

        if ($type === TransactionType::Transfer) {
            $destinationCash = ! empty($data['destination_cash_account_id']) ? (int) $data['destination_cash_account_id'] : null;
            $destinationBank = ! empty($data['destination_bank_account_id']) ? (int) $data['destination_bank_account_id'] : null;
            $this->assertTreasury($unitId, $destinationCash, $destinationBank);

            if (($cashId && $cashId === $destinationCash) || ($bankId && $bankId === $destinationBank) || (! $destinationCash && ! $destinationBank)) {
                FinanceRules::fail('destination', 'Rekening tujuan transfer harus berbeda dari sumber.');
            }
        }

        return [
            'unit_id' => $unitId,
            'period_id' => $period->id,
            'type' => $type,
            'transacted_on' => $data['transacted_on'],
            'posting_date' => $postingDate,
            'category_account_id' => $type === TransactionType::Transfer ? null : $categoryId,
            'cash_account_id' => $cashId ?: null,
            'bank_account_id' => $bankId ?: null,
            'destination_cash_account_id' => $destinationCash,
            'destination_bank_account_id' => $destinationBank,
            'payment_method' => $payment,
            'amount' => (int) $data['amount'],
            'effect' => $data['effect'] ?? null,
            'description' => trim((string) $data['description']),
            'reference' => $data['reference'] ?? null,
            'original_transaction_id' => $data['original_transaction_id'] ?? $current?->original_transaction_id,
        ];
    }

    private function assertCategory(TransactionType $type, ?int $categoryId, ?Transaction $current): void
    {
        if ($type === TransactionType::Adjustment && $current === null) {
            return;
        }

        $expected = $type === TransactionType::Income || ($type === TransactionType::Adjustment && $current?->original?->type === TransactionType::Income)
            ? AccountType::Income
            : AccountType::Expense;

        if ($type === TransactionType::Adjustment && $current === null) {
            return;
        }

        $account = Account::query()->find($categoryId);

        if (! $account || ! $account->is_postable || ! $account->is_active) {
            FinanceRules::fail('category_account_id', 'Pilih akun yang dapat diposting.');
        }

        if ($type === TransactionType::Adjustment) {
            return;
        }

        if ($account->type !== $expected) {
            FinanceRules::fail('category_account_id', 'Akun tidak sesuai jenis transaksi.');
        }
    }

    private function assertTreasury(int $unitId, ?int $cashId, ?int $bankId): void
    {
        if ($cashId) {
            $cash = CashAccount::query()->whereKey($cashId)->where('unit_id', $unitId)->where('is_active', true)->first();

            if (! $cash) {
                FinanceRules::fail('cash_account_id', 'Kas tidak termasuk unit ini.');
            }
        }

        if ($bankId) {
            $bank = BankAccount::query()->whereKey($bankId)->where('unit_id', $unitId)->where('is_active', true)->first();

            if (! $bank) {
                FinanceRules::fail('bank_account_id', 'Rekening bank tidak termasuk unit ini.');
            }
        }
    }

    private function nextNumber(Unit $unit, string $date): string
    {
        $prefix = $unit->code.'-'.substr($date, 0, 4).'-';
        $last = Transaction::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->lockForUpdate()
            ->value('number');

        $sequence = 1;

        if (is_string($last) && str_starts_with($last, $prefix)) {
            $sequence = ((int) substr($last, strlen($prefix))) + 1;
        }

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }

    private function storeDocuments(Transaction $transaction, User $actor, array $files, array $kinds): array
    {
        $stored = [];

        foreach (array_values($files) as $index => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $mime = (string) $file->getMimeType();

            if (! in_array($mime, self::ALLOWED_MIME, true)) {
                FinanceRules::fail('documents', 'Jenis berkas tidak diizinkan.');
            }

            $kind = DocumentKind::tryFrom((string) ($kinds[$index] ?? 'other')) ?? DocumentKind::Other;
            $path = $file->store('documents/'.$transaction->id, 'local');
            $stored[] = $path;

            Document::query()->create([
                'transaction_id' => $transaction->id,
                'disk' => 'local',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $mime,
                'size' => $file->getSize(),
                'kind' => $kind,
                'uploaded_by' => $actor->id,
            ]);
        }

        if ($stored !== []) {
            $this->audit->log($actor, 'document.uploaded', 'transaction', $transaction->id, (int) $transaction->unit_id, null, [
                'count' => count($stored),
            ]);
        }

        return $stored;
    }
}
