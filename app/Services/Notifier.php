<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use App\Notifications\FinanceMessage;
use App\Support\Rupiah;
use Illuminate\Support\Facades\Notification;

class Notifier
{
    public function submitted(Transaction $transaction, User $actor): void
    {
        $this->send(
            'FINANCE_VERIFY',
            (int) $transaction->unit_id,
            new FinanceMessage(
                'approval_request',
                'Transaksi menunggu tinjauan',
                $transaction->number.' · '.Rupiah::format((int) $transaction->amount),
                '/transactions/'.$transaction->id,
            ),
            $actor,
        );
    }

    public function verified(Transaction $transaction, User $actor): void
    {
        $this->send(
            'FINANCE_APPROVE',
            (int) $transaction->unit_id,
            new FinanceMessage(
                'verified',
                'Transaksi siap disetujui',
                $transaction->number.' sudah ditinjau.',
                '/transactions/'.$transaction->id,
            ),
            $actor,
        );
    }

    public function approved(Transaction $transaction, User $actor): void
    {
        $this->send(
            'FINANCE_POST',
            (int) $transaction->unit_id,
            new FinanceMessage(
                'approved',
                'Transaksi disetujui',
                $transaction->number.' siap diposting.',
                '/transactions/'.$transaction->id,
            ),
            $actor,
        );
        $this->tellCreator($transaction, 'approved', 'Transaksi disetujui', $transaction->number.' telah disetujui.');
    }

    public function rejected(Transaction $transaction, User $actor): void
    {
        $this->tellCreator(
            $transaction,
            'rejected',
            'Transaksi ditolak',
            $transaction->number.' ditolak. '.($transaction->rejection_reason ?? ''),
        );
    }

    public function posted(Transaction $transaction, User $actor): void
    {
        $this->tellCreator(
            $transaction,
            'posted',
            'Transaksi diposting',
            $transaction->number.' telah masuk jurnal dan buku besar.',
        );
    }

    public function budget(int $unitId, string $body, string $href): void
    {
        $this->send(
            'BUDGET_VIEW',
            $unitId,
            new FinanceMessage('budget_warning', 'Anggaran mendekati batas', $body, $href),
        );
    }

    public function period(string $body): void
    {
        $users = User::query()->where('is_active', true)->whereHas('roles.permissions', function ($query) {
            $query->where('name', 'PERIOD_CLOSE');
        })->get();

        Notification::send($users, new FinanceMessage('period_closing', 'Periode keuangan', $body, '/periods'));
    }

    private function tellCreator(Transaction $transaction, string $kind, string $title, string $body): void
    {
        $creator = $transaction->creator;

        if ($creator && $creator->is_active) {
            $creator->notify(new FinanceMessage($kind, $title, $body, '/transactions/'.$transaction->id));
        }
    }

    private function send(string $permission, int $unitId, FinanceMessage $message, ?User $except = null): void
    {
        $users = User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($unitId) {
                $query->whereNull('unit_id')->orWhere('unit_id', $unitId);
            })
            ->whereHas('roles.permissions', fn ($query) => $query->where('name', $permission))
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->get();

        Notification::send($users, $message);
    }
}
