<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\Budget;
use App\Models\CashAccount;
use App\Models\FinancialPeriod;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;
use App\Support\FinanceRules;
use App\Support\Rupiah;
use Barryvdh\DomPDF\Facade\Pdf;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class ReportBuilder
{
    public function __construct(private BalanceService $balances, private BudgetService $budgets) {}

    public function catalog(): array
    {
        return [
            ['key' => 'income', 'group' => 'Keuangan', 'title' => 'Pendapatan', 'description' => 'Pendapatan terposting per unit dan akun.'],
            ['key' => 'expense', 'group' => 'Keuangan', 'title' => 'Beban', 'description' => 'Beban terposting per unit dan akun.'],
            ['key' => 'cash_flow', 'group' => 'Keuangan', 'title' => 'Arus kas', 'description' => 'Penerimaan, pengeluaran, dan posisi kas-bank. Transfer tidak dihitung sebagai pendapatan.'],
            ['key' => 'journal', 'group' => 'Keuangan', 'title' => 'Jurnal', 'description' => 'Seluruh baris jurnal pada periode.'],
            ['key' => 'ledger', 'group' => 'Keuangan', 'title' => 'Buku besar', 'description' => 'Mutasi dan saldo berjalan satu akun.'],
            ['key' => 'trial_balance', 'group' => 'Keuangan', 'title' => 'Neraca saldo', 'description' => 'Total debit dan kredit periode. Keduanya harus sama.'],
            ['key' => 'budget_vs_actual', 'group' => 'Keuangan', 'title' => 'Anggaran dibanding realisasi', 'description' => 'Rencana, terikat, realisasi, dan sisa.'],
            ['key' => 'cash_position', 'group' => 'Keuangan', 'title' => 'Posisi kas dan bank', 'description' => 'Saldo berjalan setiap akun kas dan bank.'],
            ['key' => 'unit_performance', 'group' => 'Manajemen', 'title' => 'Kinerja unit', 'description' => 'Pendapatan, beban, dan neto tiap unit.'],
            ['key' => 'revenue_analysis', 'group' => 'Manajemen', 'title' => 'Analisis pendapatan', 'description' => 'Komposisi pendapatan per akun.'],
            ['key' => 'expense_analysis', 'group' => 'Manajemen', 'title' => 'Analisis beban', 'description' => 'Komposisi beban per akun.'],
            ['key' => 'monthly_comparison', 'group' => 'Manajemen', 'title' => 'Perbandingan bulanan', 'description' => 'Enam periode terakhir sampai periode yang dipilih.'],
            ['key' => 'ytd', 'group' => 'Manajemen', 'title' => 'Tahun berjalan', 'description' => 'Akumulasi dari awal tahun sampai akhir periode.'],
            ['key' => 'transaction_audit', 'group' => 'Audit', 'title' => 'Jejak transaksi', 'description' => 'Perubahan penting pada transaksi.'],
            ['key' => 'approval_history', 'group' => 'Audit', 'title' => 'Riwayat persetujuan', 'description' => 'Siapa meninjau, menyetujui, atau menolak.'],
            ['key' => 'user_activity', 'group' => 'Audit', 'title' => 'Aktivitas pengguna', 'description' => 'Jejak audit yang boleh Anda lihat.'],
        ];
    }

    public function build(User $user, string $report, array $filters): array
    {
        $known = collect($this->catalog())->firstWhere('key', $report);

        if (! $known) {
            FinanceRules::fail('report', 'Laporan tidak dikenal.');
        }

        if (in_array($report, ['transaction_audit', 'approval_history', 'user_activity'], true) && ! $user->hasPermission('AUDIT_VIEW') && $report === 'user_activity') {
            abort(403);
        }

        $period = FinancialPeriod::query()->find($filters['period_id'] ?? null);

        if (! $period && ! in_array($report, ['cash_position', 'user_activity'], true)) {
            FinanceRules::fail('period_id', 'Pilih periode.');
        }

        $unitIds = $this->scopedUnitIds($user, isset($filters['unit_id']) ? (int) $filters['unit_id'] : null);
        $built = match ($report) {
            'income' => $this->breakdown('income', $period, $unitIds),
            'expense' => $this->breakdown('expense', $period, $unitIds),
            'cash_flow' => $this->cashFlow($period, $unitIds),
            'journal' => $this->journal($period, $unitIds),
            'ledger' => $this->ledger($user, $period, $unitIds, (int) ($filters['account_id'] ?? 0)),
            'trial_balance' => $this->trial($period, $unitIds),
            'budget_vs_actual' => $this->budget($period, $unitIds),
            'cash_position' => $this->cashPosition($user),
            'unit_performance' => $this->unitPerformance($period, $unitIds),
            'revenue_analysis' => $this->accountAnalysis('income', $period, $unitIds),
            'expense_analysis' => $this->accountAnalysis('expense', $period, $unitIds),
            'monthly_comparison' => $this->monthly($period, $unitIds),
            'ytd' => $this->ytd($period, $unitIds),
            'transaction_audit' => $this->auditRows($user, 'transaction', $period),
            'approval_history' => $this->approvals($user, $period),
            'user_activity' => $this->auditRows($user, null, $period),
            default => FinanceRules::fail('report', 'Laporan tidak dikenal.'),
        };

        return [
            'key' => $report,
            'title' => $known['title'],
            'period' => $period?->name,
            'columns' => $built['columns'],
            'rows' => $built['rows'],
            'total' => $built['total'],
            'total_label' => $built['total_label'],
        ];
    }

    public function export(User $user, string $report, array $filters, string $format): string
    {
        $data = $this->build($user, $report, $filters);
        $directory = storage_path('app/private/reports');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $safe = preg_replace('/[^a-z0-9_-]+/i', '-', $report);
        $filename = $safe.'-'.now()->format('YmdHis').'.'.($format === 'pdf' ? 'pdf' : 'xlsx');
        $path = $directory.DIRECTORY_SEPARATOR.$filename;

        if ($format === 'pdf') {
            Pdf::loadView('reports.export', ['report' => $data])
                ->setPaper('a4', 'portrait')
                ->save($path);
        } else {
            $writer = new Writer;
            $writer->openToFile($path);
            $writer->addRow(Row::fromValues($data['columns']));

            foreach ($data['rows'] as $row) {
                $writer->addRow(Row::fromValues(array_values($row)));
            }

            $writer->addRow(Row::fromValues([$data['total_label'], $data['total']]));
            $writer->close();
        }

        return 'reports/'.$filename;
    }

    private function scopedUnitIds(User $user, ?int $requested): ?array
    {
        if ($user->unit_id !== null) {
            return [(int) $user->unit_id];
        }

        if ($requested) {
            return [$requested];
        }

        $context = session('context_unit_id');

        return $context ? [(int) $context] : null;
    }

    private function breakdown(string $type, FinancialPeriod $period, ?array $unitIds): array
    {
        $rows = [];
        $total = 0;
        $units = Unit::query()->when($unitIds, fn ($query) => $query->whereIn('id', $unitIds))->orderBy('code')->get();

        foreach ($units as $unit) {
            $amount = (int) ($this->balances->byUnit($type, $period->starts_on->toDateString(), $period->ends_on->toDateString(), [(int) $unit->id])[$unit->id] ?? 0);
            $total += $amount;
            $rows[] = [$unit->code, $unit->name, 'Semua akun', $amount];
        }

        return [
            'columns' => ['Kode', 'Unit', 'Akun', 'Jumlah'],
            'rows' => $rows,
            'total' => $this->balances->total($type, $unitIds, $period->starts_on->toDateString(), $period->ends_on->toDateString()),
            'total_label' => $type === 'income' ? 'Total pendapatan' : 'Total beban',
        ];
    }

    private function cashFlow(FinancialPeriod $period, ?array $unitIds): array
    {
        $start = $period->starts_on->toDateString();
        $end = $period->ends_on->toDateString();
        $revenue = $this->balances->total('income', $unitIds, $start, $end);
        $expense = $this->balances->total('expense', $unitIds, $start, $end);

        return [
            'columns' => ['Komponen', 'Jumlah'],
            'rows' => [
                ['Penerimaan', $revenue],
                ['Pengeluaran', $expense],
                ['Neto', $revenue - $expense],
            ],
            'total' => $revenue - $expense,
            'total_label' => 'Neto periode',
        ];
    }

    private function journal(FinancialPeriod $period, ?array $unitIds): array
    {
        $entries = JournalEntry::query()
            ->with('lines.account', 'unit', 'transaction')
            ->where('period_id', $period->id)
            ->when($unitIds, fn ($query) => $query->whereIn('unit_id', $unitIds))
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        $rows = [];
        $debit = 0;
        $credit = 0;

        foreach ($entries as $entry) {
            foreach ($entry->lines as $line) {
                $debit += (int) $line->debit;
                $credit += (int) $line->credit;
                $rows[] = [
                    $entry->entry_date->toDateString(),
                    $entry->transaction?->number,
                    $entry->unit?->code,
                    $line->account?->code,
                    $line->account?->name,
                    (int) $line->debit,
                    (int) $line->credit,
                ];
            }
        }

        return [
            'columns' => ['Tanggal', 'Transaksi', 'Unit', 'Kode', 'Akun', 'Debit', 'Kredit'],
            'rows' => $rows,
            'total' => $debit,
            'total_label' => $debit === $credit ? 'Total debit (seimbang)' : 'Total debit',
        ];
    }

    private function ledger(User $user, FinancialPeriod $period, ?array $unitIds, int $accountId): array
    {
        $account = Account::query()->find($accountId);

        if (! $account) {
            FinanceRules::fail('account_id', 'Pilih akun buku besar.');
        }

        $ledger = $this->balances->ledger($account, $period->starts_on->toDateString(), $period->ends_on->toDateString(), $unitIds);
        $rows = [[ 'Saldo awal', '', '', $ledger['opening'], 0, 0, $ledger['opening'] ]];

        foreach ($ledger['rows'] as $row) {
            $rows[] = [
                $row['date'] instanceof \DateTimeInterface ? $row['date']->format('Y-m-d') : (string) $row['date'],
                $row['description'],
                $row['memo'],
                0,
                $row['debit'],
                $row['credit'],
                $row['balance'],
            ];
        }

        return [
            'columns' => ['Tanggal', 'Uraian', 'Memo', 'Awal', 'Debit', 'Kredit', 'Saldo'],
            'rows' => $rows,
            'total' => $ledger['closing'],
            'total_label' => 'Saldo akhir '.$account->code,
        ];
    }

    private function trial(FinancialPeriod $period, ?array $unitIds): array
    {
        $trial = $this->balances->trialBalance($period->starts_on->toDateString(), $period->ends_on->toDateString(), $unitIds);

        return [
            'columns' => ['Kode', 'Akun', 'Debit', 'Kredit'],
            'rows' => array_map(fn ($row) => [$row['code'], $row['name'], $row['debit'], $row['credit']], $trial['rows']),
            'total' => $trial['debit'],
            'total_label' => $trial['balanced'] ? 'Total debit = kredit '.Rupiah::format($trial['debit']) : 'Tidak seimbang',
        ];
    }

    private function budget(FinancialPeriod $period, ?array $unitIds): array
    {
        $budgets = Budget::query()->with('lines.account', 'unit', 'period')->where('period_id', $period->id)->where('status', 'approved')
            ->when($unitIds, fn ($query) => $query->whereIn('unit_id', $unitIds))->get();
        $rows = [];
        $planned = 0;
        $actual = 0;

        foreach ($budgets as $budget) {
            foreach ($budget->lines as $line) {
                $presented = $this->budgets->present($line);
                $planned += $presented['planned'];
                $actual += $presented['actual'];
                $rows[] = [
                    $budget->unit?->code,
                    $presented['code'],
                    $presented['name'],
                    $presented['planned'],
                    $presented['committed'],
                    $presented['actual'],
                    $presented['remaining'],
                    $presented['state_label'],
                ];
            }
        }

        return [
            'columns' => ['Unit', 'Kode', 'Akun', 'Rencana', 'Terikat', 'Realisasi', 'Sisa', 'Status'],
            'rows' => $rows,
            'total' => $actual,
            'total_label' => 'Realisasi dari rencana '.Rupiah::format($planned),
        ];
    }

    private function cashPosition(User $user): array
    {
        $cash = CashAccount::query()->with('unit')->visibleTo($user)->where('is_active', true)->get();
        $bank = BankAccount::query()->with('unit')->visibleTo($user)->where('is_active', true)->get();
        $cashBalances = $this->balances->cashBalances($cash);
        $bankBalances = $this->balances->bankBalances($bank);
        $rows = [];
        $total = 0;

        foreach ($cash as $account) {
            $balance = $cashBalances[$account->id] ?? 0;
            $total += $balance;
            $rows[] = [$account->unit?->code, 'Kas', $account->name, $balance];
        }

        foreach ($bank as $account) {
            $balance = $bankBalances[$account->id] ?? 0;
            $total += $balance;
            $rows[] = [$account->unit?->code, 'Bank', $account->bank_name.' '.$account->masked_number, $balance];
        }

        return [
            'columns' => ['Unit', 'Jenis', 'Akun', 'Saldo'],
            'rows' => $rows,
            'total' => $total,
            'total_label' => 'Total kas dan bank',
        ];
    }

    private function unitPerformance(FinancialPeriod $period, ?array $unitIds): array
    {
        $start = $period->starts_on->toDateString();
        $end = $period->ends_on->toDateString();
        $revenue = $this->balances->byUnit('income', $start, $end, $unitIds);
        $expense = $this->balances->byUnit('expense', $start, $end, $unitIds);
        $rows = [];
        $net = 0;

        foreach (Unit::query()->when($unitIds, fn ($query) => $query->whereIn('id', $unitIds))->orderBy('code')->get() as $unit) {
            $in = (int) ($revenue[$unit->id] ?? 0);
            $out = (int) ($expense[$unit->id] ?? 0);
            $net += $in - $out;
            $rows[] = [$unit->code, $unit->name, $in, $out, $in - $out];
        }

        return [
            'columns' => ['Kode', 'Unit', 'Pendapatan', 'Beban', 'Neto'],
            'rows' => $rows,
            'total' => $net,
            'total_label' => 'Neto konsolidasi',
        ];
    }

    private function accountAnalysis(string $type, FinancialPeriod $period, ?array $unitIds): array
    {
        $accounts = $this->balances->byAccount($type, $period->starts_on->toDateString(), $period->ends_on->toDateString(), $unitIds);
        $total = (int) $accounts->sum('total');

        return [
            'columns' => ['Kode', 'Akun', 'Jumlah'],
            'rows' => $accounts->map(fn ($row) => [$row['code'], $row['name'], $row['total']])->all(),
            'total' => $total,
            'total_label' => 'Total',
        ];
    }

    private function monthly(FinancialPeriod $period, ?array $unitIds): array
    {
        $periods = FinancialPeriod::query()->whereDate('starts_on', '<=', $period->starts_on->toDateString())->orderByDesc('starts_on')->limit(6)->get()->sortBy('starts_on');
        $rows = [];
        $net = 0;

        foreach ($periods as $item) {
            $position = $this->balances->position($item, $unitIds);
            $net = $position['net'];
            $rows[] = [$item->name, $position['revenue'], $position['expense'], $position['net']];
        }

        return [
            'columns' => ['Periode', 'Pendapatan', 'Beban', 'Neto'],
            'rows' => $rows,
            'total' => $net,
            'total_label' => 'Neto periode terakhir pada tabel',
        ];
    }

    private function ytd(FinancialPeriod $period, ?array $unitIds): array
    {
        $start = $period->starts_on->copy()->startOfYear()->toDateString();
        $end = $period->ends_on->toDateString();
        $revenue = $this->balances->total('income', $unitIds, $start, $end);
        $expense = $this->balances->total('expense', $unitIds, $start, $end);

        return [
            'columns' => ['Komponen', 'Jumlah'],
            'rows' => [
                ['Pendapatan tahun berjalan', $revenue],
                ['Beban tahun berjalan', $expense],
                ['Neto', $revenue - $expense],
            ],
            'total' => $revenue - $expense,
            'total_label' => 'Neto tahun berjalan',
        ];
    }

    private function auditRows(User $user, ?string $entity, ?FinancialPeriod $period): array
    {
        if (! $user->hasPermission('AUDIT_VIEW')) {
            abort(403);
        }

        $logs = AuditLog::query()
            ->with('user:id,name')
            ->visibleTo($user)
            ->when($entity, fn ($query) => $query->where('entity_type', $entity))
            ->when($period, fn ($query) => $query->whereDate('created_at', '>=', $period->starts_on->toDateString())->whereDate('created_at', '<=', $period->ends_on->toDateString()))
            ->latest('id')
            ->limit(500)
            ->get();

        return [
            'columns' => ['Waktu', 'Pengguna', 'Aksi', 'Entitas', 'ID'],
            'rows' => $logs->map(fn (AuditLog $log) => [
                $log->created_at?->format('Y-m-d H:i'),
                $log->user?->name,
                $log->action,
                $log->entity_type,
                $log->entity_id,
            ])->all(),
            'total' => $logs->count(),
            'total_label' => 'Jumlah baris',
        ];
    }

    private function approvals(User $user, FinancialPeriod $period): array
    {
        $rows = Approval::query()
            ->with(['actor:id,name', 'transaction:id,number,unit_id,period_id'])
            ->whereHas('transaction', function ($query) use ($user, $period) {
                $query->visibleTo($user)->where('period_id', $period->id);
            })
            ->latest('id')
            ->limit(500)
            ->get();

        return [
            'columns' => ['Waktu', 'Nomor', 'Aksi', 'Pelaku', 'Catatan'],
            'rows' => $rows->map(fn (Approval $approval) => [
                $approval->created_at?->format('Y-m-d H:i'),
                $approval->transaction?->number,
                $approval->action,
                $approval->actor?->name,
                $approval->comment,
            ])->all(),
            'total' => $rows->count(),
            'total_label' => 'Jumlah keputusan',
        ];
    }
}
