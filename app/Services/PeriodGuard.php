<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PeriodStatus;
use App\Models\BankStatementLine;
use App\Models\FinancialPeriod;
use App\Models\Transaction;
use App\Models\User;
use App\Support\FinanceRules;

class PeriodGuard
{
    public function __construct(private AuditLogger $audit, private DomainEvents $events, private Notifier $notifier) {}

    public function forDate(string $date): FinancialPeriod
    {
        $period = FinancialPeriod::query()
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->first();

        if (! $period) {
            FinanceRules::fail('posting_date', 'Tidak ada periode keuangan untuk tanggal ini.');
        }

        return $period;
    }

    public function assertOpen(FinancialPeriod $period): void
    {
        if (! $period->status->acceptsEntries()) {
            FinanceRules::fail('period', 'Periode '.$period->name.' berstatus '.$period->status->label().'. Gunakan penyesuaian pada periode yang masih terbuka.');
        }
    }

    public function blockers(FinancialPeriod $period): array
    {
        $pending = Transaction::query()
            ->where('period_id', $period->id)
            ->whereIn('status', ['submitted', 'under_review', 'approved'])
            ->count();

        $unmatched = BankStatementLine::query()
            ->where('match_status', 'unmatched')
            ->whereHas('statement', fn ($query) => $query->where('period_id', $period->id))
            ->count();

        $items = [];

        if ($pending > 0) {
            $items[] = $pending.' transaksi masih menunggu tinjauan, persetujuan, atau posting.';
        }

        if ($unmatched > 0) {
            $items[] = $unmatched.' baris rekening koran belum dicocokkan.';
        }

        return $items;
    }

    public function close(FinancialPeriod $period, User $actor): FinancialPeriod
    {
        if (! $actor->hasPermission('PERIOD_CLOSE')) {
            abort(403);
        }

        if ($period->status === PeriodStatus::Locked) {
            FinanceRules::fail('period', 'Periode sudah terkunci.');
        }

        if ($period->status === PeriodStatus::Open) {
            $period->update(['status' => PeriodStatus::Closing]);
            $this->audit->log($actor, 'period.closing', 'financial_period', $period->id, null, ['status' => 'open'], ['status' => 'closing']);
            $this->events->publish('period.closing', null, ['id' => $period->id, 'name' => $period->name]);
            $this->notifier->period('Penutupan '.$period->name.' dimulai. Selesaikan antrean sebelum mengunci.');
        }

        $blockers = $this->blockers($period->refresh());

        if ($blockers !== []) {
            FinanceRules::fail('period', 'Periode belum dapat dikunci. '.implode(' ', $blockers));
        }

        $period->update([
            'status' => PeriodStatus::Locked,
            'closed_by' => $actor->id,
            'closed_at' => now(),
        ]);

        $this->audit->log($actor, 'period.locked', 'financial_period', $period->id, null, ['status' => 'closing'], ['status' => 'locked']);
        $this->events->publish('period.locked', null, ['id' => $period->id, 'name' => $period->name]);

        return $period->refresh();
    }
}
