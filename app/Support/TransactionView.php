<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Transaction;
use App\Models\User;
use App\Services\ApprovalEngine;

class TransactionView
{
    public function __construct(private ApprovalEngine $engine) {}

    public function listItem(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'number' => $transaction->number,
            'unit' => $transaction->unit?->code,
            'unit_id' => $transaction->unit_id,
            'type' => $transaction->type->value,
            'type_label' => $transaction->type->label(),
            'status' => $transaction->status->value,
            'status_label' => $transaction->status->label(),
            'transacted_on' => $transaction->transacted_on?->toDateString(),
            'description' => $transaction->description,
            'amount' => (int) $transaction->amount,
            'category' => $transaction->category?->name,
            'reference' => $transaction->reference,
        ];
    }

    public function detail(Transaction $transaction, User $user): array
    {
        $transaction->load([
            'unit', 'period', 'category', 'cashAccount', 'bankAccount', 'destinationCashAccount',
            'destinationBankAccount', 'creator', 'submitter', 'reviewer', 'approver', 'poster',
            'approvals.actor', 'documents', 'journalEntry.lines.account', 'adjustments', 'original',
        ]);

        $next = $this->engine->nextAction($transaction);

        return [
            'id' => $transaction->id,
            'number' => $transaction->number,
            'type' => $transaction->type->value,
            'type_label' => $transaction->type->label(),
            'status' => $transaction->status->value,
            'status_label' => $transaction->status->label(),
            'amount' => (int) $transaction->amount,
            'effect' => $transaction->effect,
            'description' => $transaction->description,
            'reference' => $transaction->reference,
            'transacted_on' => $transaction->transacted_on?->toDateString(),
            'posting_date' => $transaction->posting_date?->toDateString(),
            'unit' => ['id' => $transaction->unit_id, 'code' => $transaction->unit?->code, 'name' => $transaction->unit?->name],
            'period' => ['id' => $transaction->period_id, 'name' => $transaction->period?->name, 'status' => $transaction->period?->status?->value],
            'category' => $transaction->category ? ['id' => $transaction->category->id, 'code' => $transaction->category->code, 'name' => $transaction->category->name] : null,
            'payment_method' => $transaction->payment_method->value,
            'payment_label' => $transaction->payment_method->label(),
            'cash_account' => $transaction->cashAccount ? ['id' => $transaction->cash_account_id, 'name' => $transaction->cashAccount->name] : null,
            'bank_account' => $transaction->bankAccount ? ['id' => $transaction->bank_account_id, 'name' => $transaction->bankAccount->bank_name, 'masked' => $transaction->bankAccount->masked_number] : null,
            'destination_cash' => $transaction->destinationCashAccount?->name,
            'destination_bank' => $transaction->destinationBankAccount ? $transaction->destinationBankAccount->bank_name.' '.$transaction->destinationBankAccount->masked_number : null,
            'people' => [
                'creator' => $transaction->creator?->name,
                'submitter' => $transaction->submitter?->name,
                'reviewer' => $transaction->reviewer?->name,
                'approver' => $transaction->approver?->name,
                'poster' => $transaction->poster?->name,
            ],
            'rejection_reason' => $transaction->rejection_reason,
            'original' => $transaction->original ? ['id' => $transaction->original->id, 'number' => $transaction->original->number] : null,
            'adjustments' => $transaction->adjustments->map(fn (Transaction $item) => [
                'id' => $item->id,
                'number' => $item->number,
                'amount' => (int) $item->amount,
                'status_label' => $item->status->label(),
                'effect' => $item->effect,
            ])->values(),
            'timeline' => $transaction->approvals->map(fn ($approval) => [
                'action' => $approval->action,
                'label' => $this->actionLabel($approval->action),
                'actor' => $approval->actor?->name,
                'comment' => $approval->comment,
                'at' => $approval->created_at?->toIso8601String(),
            ])->values(),
            'journal' => $transaction->journalEntry ? [
                'id' => $transaction->journalEntry->id,
                'date' => $transaction->journalEntry->entry_date?->toDateString(),
                'lines' => $transaction->journalEntry->lines->map(fn ($line) => [
                    'account' => $line->account?->code.' '.$line->account?->name,
                    'debit' => (int) $line->debit,
                    'credit' => (int) $line->credit,
                ])->values(),
                'balanced' => (int) $transaction->journalEntry->lines->sum('debit') === (int) $transaction->journalEntry->lines->sum('credit'),
            ] : null,
            'documents' => $transaction->documents->map(fn ($document) => [
                'id' => $document->id,
                'name' => $document->original_name,
                'kind' => $document->kind->value,
                'kind_label' => $document->kind->label(),
                'mime' => $document->mime,
                'size' => $document->size,
            ])->values(),
            'next' => $next && $user->hasPermission($next['permission']) ? $next : null,
            'can_edit' => $transaction->status->isEditable() && $user->hasPermission('FINANCE_EDIT'),
            'can_submit' => $transaction->status->value === 'draft' && $user->hasPermission('FINANCE_SUBMIT'),
            'can_reject' => in_array($transaction->status->value, ['submitted', 'under_review', 'approved'], true) && $user->hasPermission('FINANCE_REJECT') && (int) $user->id !== (int) $transaction->created_by,
            'can_revise' => $transaction->status->value === 'rejected' && $user->hasPermission('FINANCE_EDIT'),
            'can_adjust' => $transaction->status->isFinal() && $user->hasPermission('FINANCE_ADJUST') && in_array($transaction->type->value, ['income', 'expense'], true),
            'lock_version' => $transaction->lock_version,
        ];
    }

    private function actionLabel(string $action): string
    {
        return match ($action) {
            'submit' => 'Diajukan',
            'verify' => 'Ditinjau',
            'approve' => 'Disetujui',
            'reject' => 'Ditolak',
            'post' => 'Diposting',
            'revision' => 'Direvisi',
            default => $action,
        };
    }
}
