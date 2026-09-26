<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Models\Approval;
use App\Models\ApprovalWorkflowStep;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Support\FinanceRules;
use Illuminate\Support\Facades\DB;

class ApprovalEngine
{
    public function __construct(
        private PeriodGuard $periods,
        private PostingService $posting,
        private BudgetService $budgets,
        private AuditLogger $audit,
        private DomainEvents $events,
        private Notifier $notifier,
    ) {}

    public function nextAction(Transaction $transaction): ?array
    {
        $step = $this->expectedStep($transaction);

        if (! $step) {
            return null;
        }

        return [
            'action' => $step->action,
            'permission' => $step->permission,
            'label' => match ($step->action) {
                'verify' => 'Tinjau',
                'approve' => 'Setujui',
                'post' => 'Posting',
                default => ucfirst($step->action),
            },
        ];
    }

    public function submit(Transaction $transaction, User $actor): Transaction
    {
        return DB::transaction(function () use ($transaction, $actor) {
            $transaction = $this->lock($transaction);

            if ($transaction->status !== TransactionStatus::Draft) {
                FinanceRules::fail('status', 'Hanya draf yang dapat diajukan.');
            }

            $this->assertPermission($actor, 'FINANCE_SUBMIT');
            $this->assertUnit($actor, $transaction);
            $this->periods->assertOpen($transaction->period);

            $transaction->forceFill([
                'status' => TransactionStatus::Submitted,
                'submitted_by' => $actor->id,
                'submitted_at' => now(),
                'lock_version' => $transaction->lock_version + 1,
            ])->save();

            $this->record($transaction, $actor, 'submit', null);
            $this->audit->log($actor, 'transaction.submitted', 'transaction', $transaction->id, (int) $transaction->unit_id, null, [
                'status' => TransactionStatus::Submitted->value,
            ]);
            $this->notifier->submitted($transaction, $actor);
            $this->events->publish('transaction.submitted', (int) $transaction->unit_id, $this->payload($transaction));

            return $transaction->refresh();
        });
    }

    public function act(Transaction $transaction, User $actor, string $action, ?string $comment = null): Transaction
    {
        return DB::transaction(function () use ($transaction, $actor, $action, $comment) {
            $transaction = $this->lock($transaction);
            $step = $this->expectedStep($transaction);

            if (! $step || $step->action !== $action) {
                FinanceRules::fail('status', 'Tindakan ini tidak sesuai tahap persetujuan saat ini.');
            }

            $this->assertPermission($actor, $step->permission);
            $this->assertUnit($actor, $transaction);
            $this->assertNotSelf($actor, $transaction);
            $this->periods->assertOpen($transaction->period);

            if ($action === 'post') {
                $this->posting->post($transaction, $actor);
            }

            $fields = [
                'status' => $this->statusAfter($transaction->status),
                'lock_version' => $transaction->lock_version + 1,
            ];

            if ($action === 'verify') {
                $fields['reviewed_by'] = $actor->id;
                $fields['reviewed_at'] = now();
            }

            if ($action === 'approve') {
                $fields['approved_by'] = $actor->id;
                $fields['approved_at'] = now();
            }

            if ($action === 'post') {
                $fields['posted_by'] = $actor->id;
                $fields['posted_at'] = now();
                $fields['posting_date'] = $transaction->posting_date->toDateString();
            }

            $transaction->forceFill($fields)->save();
            $this->record($transaction, $actor, $action, $comment);
            $this->audit->log($actor, 'transaction.'.$action, 'transaction', $transaction->id, (int) $transaction->unit_id, null, [
                'status' => $transaction->status->value,
            ]);
            $this->events->publish('transaction.'.$action, (int) $transaction->unit_id, $this->payload($transaction));

            if ($action === 'verify') {
                $this->notifier->verified($transaction, $actor);
            }

            if ($action === 'approve') {
                $this->notifier->approved($transaction, $actor);
            }

            if ($action === 'post') {
                $this->budgets->evaluate($transaction->refresh());
                $this->notifier->posted($transaction->refresh(), $actor);
            }

            return $transaction->refresh();
        });
    }

    public function reject(Transaction $transaction, User $actor, string $reason): Transaction
    {
        return DB::transaction(function () use ($transaction, $actor, $reason) {
            $transaction = $this->lock($transaction);

            if (! in_array($transaction->status, [TransactionStatus::Submitted, TransactionStatus::UnderReview, TransactionStatus::Approved], true)) {
                FinanceRules::fail('status', 'Transaksi ini tidak sedang menunggu keputusan.');
            }

            $this->assertPermission($actor, 'FINANCE_REJECT');
            $this->assertUnit($actor, $transaction);
            $this->assertNotSelf($actor, $transaction);

            $transaction->forceFill([
                'status' => TransactionStatus::Rejected,
                'rejected_by' => $actor->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
                'lock_version' => $transaction->lock_version + 1,
            ])->save();

            $this->record($transaction, $actor, 'reject', $reason);
            $this->audit->log($actor, 'transaction.rejected', 'transaction', $transaction->id, (int) $transaction->unit_id, null, [
                'reason' => $reason,
            ]);
            $this->notifier->rejected($transaction, $actor);
            $this->events->publish('transaction.rejected', (int) $transaction->unit_id, $this->payload($transaction));

            return $transaction->refresh();
        });
    }

    public function revise(Transaction $transaction, User $actor): Transaction
    {
        return DB::transaction(function () use ($transaction, $actor) {
            $transaction = $this->lock($transaction);

            if ($transaction->status !== TransactionStatus::Rejected) {
                FinanceRules::fail('status', 'Hanya transaksi yang ditolak yang dapat direvisi.');
            }

            $this->assertPermission($actor, 'FINANCE_EDIT');
            $this->assertUnit($actor, $transaction);
            $this->periods->assertOpen($transaction->period);

            $transaction->forceFill([
                'status' => TransactionStatus::Draft,
                'approval_cycle' => $transaction->approval_cycle + 1,
                'rejection_reason' => null,
                'lock_version' => $transaction->lock_version + 1,
            ])->save();

            $this->record($transaction, $actor, 'revision', null);
            $this->audit->log($actor, 'transaction.revised', 'transaction', $transaction->id, (int) $transaction->unit_id, null, [
                'status' => 'draft',
            ]);

            return $transaction->refresh();
        });
    }

    private function expectedStep(Transaction $transaction): ?ApprovalWorkflowStep
    {
        $sequence = match ($transaction->status) {
            TransactionStatus::Submitted => 1,
            TransactionStatus::UnderReview => 2,
            TransactionStatus::Approved => 3,
            default => null,
        };

        if ($sequence === null) {
            return null;
        }

        return ApprovalWorkflowStep::query()
            ->where('sequence', $sequence)
            ->whereHas('workflow', fn ($query) => $query->where('subject', 'transaction'))
            ->first();
    }

    private function statusAfter(TransactionStatus $status): TransactionStatus
    {
        return match ($status) {
            TransactionStatus::Submitted => TransactionStatus::UnderReview,
            TransactionStatus::UnderReview => TransactionStatus::Approved,
            TransactionStatus::Approved => TransactionStatus::Posted,
            default => FinanceRules::fail('status', 'Status tidak dapat dilanjutkan.'),
        };
    }

    private function lock(Transaction $transaction): Transaction
    {
        $locked = Transaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
        $locked->load('period', 'original');

        return $locked;
    }

    private function assertPermission(User $actor, string $permission): void
    {
        if (! $actor->hasPermission($permission)) {
            abort(403);
        }
    }

    private function assertUnit(User $actor, Transaction $transaction): void
    {
        if (! $actor->canAccessUnit((int) $transaction->unit_id)) {
            abort(403);
        }
    }

    private function assertNotSelf(User $actor, Transaction $transaction): void
    {
        if (Setting::flag('prevent_self_approval', true) && (int) $actor->id === (int) $transaction->created_by) {
            FinanceRules::fail('approval', 'Anda tidak dapat meninjau, menyetujui, menolak, atau memposting transaksi yang Anda buat sendiri.');
        }
    }

    private function record(Transaction $transaction, User $actor, string $action, ?string $comment): void
    {
        Approval::query()->create([
            'transaction_id' => $transaction->id,
            'cycle' => $transaction->approval_cycle,
            'action' => $action,
            'actor_id' => $actor->id,
            'comment' => $comment,
            'created_at' => now(),
        ]);
    }

    private function payload(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'number' => $transaction->number,
            'status' => $transaction->status->value,
            'amount' => (int) $transaction->amount,
            'unit_id' => (int) $transaction->unit_id,
        ];
    }
}
