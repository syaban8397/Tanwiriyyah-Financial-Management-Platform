<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FinancialPeriod;
use App\Models\Unit;
use App\Models\User;
use App\Support\Rupiah;

class InquiryService
{
    public function __construct(private BalanceService $balances) {}

    public function questions(): array
    {
        return [
            ['key' => 'unit_spend', 'label' => 'Berapa beban sebuah unit pada periode ini?'],
            ['key' => 'highest_expense', 'label' => 'Unit mana yang bebannya paling besar?'],
            ['key' => 'expense_mix', 'label' => 'Akun beban mana yang terbesar?'],
            ['key' => 'compare_units', 'label' => 'Bandingkan neto dua unit.'],
        ];
    }

    public function answer(User $user, string $question, array $params): array
    {
        $period = FinancialPeriod::query()->find($params['period_id'] ?? null);

        if (! $period) {
            return ['understood' => false, 'facts' => [], 'text' => 'Pilih periode yang ada di sistem.'];
        }

        $start = $period->starts_on->toDateString();
        $end = $period->ends_on->toDateString();
        $allowed = $this->balances->unitIds($user);

        return match ($question) {
            'unit_spend' => $this->unitSpend($user, $period, $start, $end, (int) ($params['unit_id'] ?? 0)),
            'highest_expense' => $this->highest($period, $start, $end, $allowed),
            'expense_mix' => $this->mix($period, $start, $end, $allowed),
            'compare_units' => $this->compare($user, $period, $start, $end, (int) ($params['left_id'] ?? 0), (int) ($params['right_id'] ?? 0)),
            default => ['understood' => false, 'facts' => [], 'text' => 'Pertanyaan ini tidak ada dalam daftar. Tidak ada angka yang dibuat di luar buku besar.'],
        };
    }

    private function unitSpend(User $user, FinancialPeriod $period, string $start, string $end, int $unitId): array
    {
        if ($unitId === 0 || ! $user->canAccessUnit($unitId)) {
            return ['understood' => false, 'facts' => [], 'text' => 'Unit itu tidak dapat diakses.'];
        }

        $unit = Unit::query()->findOrFail($unitId);
        $expense = $this->balances->total('expense', [$unitId], $start, $end);
        $income = $this->balances->total('income', [$unitId], $start, $end);

        return [
            'understood' => true,
            'facts' => [
                ['label' => 'Unit', 'value' => $unit->name],
                ['label' => 'Periode', 'value' => $period->name],
                ['label' => 'Beban', 'value' => $expense],
                ['label' => 'Pendapatan', 'value' => $income],
            ],
            'text' => $unit->short_name.' mencatat beban '.Rupiah::format($expense).' dan pendapatan '.Rupiah::format($income).' pada '.$period->name.'. Angka ini diambil dari jurnal yang sudah diposting.',
        ];
    }

    private function highest(FinancialPeriod $period, string $start, string $end, ?array $allowed): array
    {
        $byUnit = $this->balances->byUnit('expense', $start, $end, $allowed);
        $unitId = $byUnit->sortDesc()->keys()->first();

        if (! $unitId) {
            return ['understood' => true, 'facts' => [], 'text' => 'Belum ada beban terposting pada '.$period->name.'.'];
        }

        $unit = Unit::query()->find($unitId);
        $amount = (int) $byUnit[$unitId];

        return [
            'understood' => true,
            'facts' => [
                ['label' => 'Unit', 'value' => $unit?->name],
                ['label' => 'Beban', 'value' => $amount],
            ],
            'text' => ($unit?->short_name ?? 'Unit').' memiliki beban terbesar, '.Rupiah::format($amount).', pada '.$period->name.'.',
        ];
    }

    private function mix(FinancialPeriod $period, string $start, string $end, ?array $allowed): array
    {
        $accounts = $this->balances->byAccount('expense', $start, $end, $allowed)->sortByDesc('total')->values();
        $top = $accounts->first();

        if (! $top) {
            return ['understood' => true, 'facts' => [], 'text' => 'Belum ada beban terposting pada '.$period->name.'.'];
        }

        return [
            'understood' => true,
            'facts' => $accounts->take(5)->map(fn ($row) => ['label' => $row['code'].' '.$row['name'], 'value' => $row['total']])->all(),
            'text' => 'Akun beban terbesar adalah '.$top['code'].' '.$top['name'].' sebesar '.Rupiah::format($top['total']).'.',
        ];
    }

    private function compare(User $user, FinancialPeriod $period, string $start, string $end, int $leftId, int $rightId): array
    {
        if (! $user->canAccessUnit($leftId) || ! $user->canAccessUnit($rightId)) {
            return ['understood' => false, 'facts' => [], 'text' => 'Salah satu unit tidak dapat diakses.'];
        }

        $left = Unit::query()->find($leftId);
        $right = Unit::query()->find($rightId);

        if (! $left || ! $right) {
            return ['understood' => false, 'facts' => [], 'text' => 'Unit tidak ditemukan.'];
        }

        $leftNet = $this->balances->total('income', [$leftId], $start, $end) - $this->balances->total('expense', [$leftId], $start, $end);
        $rightNet = $this->balances->total('income', [$rightId], $start, $end) - $this->balances->total('expense', [$rightId], $start, $end);

        return [
            'understood' => true,
            'facts' => [
                ['label' => $left->code.' neto', 'value' => $leftNet],
                ['label' => $right->code.' neto', 'value' => $rightNet],
                ['label' => 'Selisih', 'value' => $leftNet - $rightNet],
            ],
            'text' => 'Pada '.$period->name.', neto '.$left->code.' adalah '.Rupiah::format($leftNet).' dan neto '.$right->code.' adalah '.Rupiah::format($rightNet).'. Selisih '.Rupiah::format($leftNet - $rightNet).'.',
        ];
    }
}
