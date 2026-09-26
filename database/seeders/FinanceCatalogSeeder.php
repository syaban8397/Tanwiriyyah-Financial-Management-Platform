<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\ApprovalWorkflow;
use App\Models\Budget;
use App\Models\FinancialPeriod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use App\Services\UnitProvisioner;
use Illuminate\Database\Seeder;

class FinanceCatalogSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public function run(): void
    {
        $permissions = [
            ['FINANCE_VIEW', 'Melihat keuangan', 'Keuangan'],
            ['FINANCE_CREATE', 'Membuat transaksi', 'Keuangan'],
            ['FINANCE_EDIT', 'Mengubah draf', 'Keuangan'],
            ['FINANCE_SUBMIT', 'Mengajukan transaksi', 'Keuangan'],
            ['FINANCE_VERIFY', 'Meninjau transaksi', 'Keuangan'],
            ['FINANCE_APPROVE', 'Menyetujui transaksi', 'Keuangan'],
            ['FINANCE_REJECT', 'Menolak transaksi', 'Keuangan'],
            ['FINANCE_POST', 'Memposting jurnal', 'Keuangan'],
            ['FINANCE_ADJUST', 'Membuat penyesuaian', 'Keuangan'],
            ['BUDGET_VIEW', 'Melihat anggaran', 'Anggaran'],
            ['BUDGET_CREATE', 'Menyusun anggaran', 'Anggaran'],
            ['BUDGET_EDIT', 'Mengubah anggaran', 'Anggaran'],
            ['BUDGET_APPROVE', 'Menyetujui anggaran', 'Anggaran'],
            ['RECONCILIATION_VIEW', 'Melihat rekonsiliasi', 'Kas dan bank'],
            ['RECONCILIATION_CREATE', 'Mengerjakan rekonsiliasi', 'Kas dan bank'],
            ['REPORT_VIEW', 'Melihat laporan', 'Laporan'],
            ['REPORT_EXPORT', 'Mengekspor laporan', 'Laporan'],
            ['PERIOD_OPEN', 'Membuka periode', 'Periode'],
            ['PERIOD_CLOSE', 'Menutup periode', 'Periode'],
            ['AUDIT_VIEW', 'Melihat jejak audit', 'Audit'],
            ['USER_MANAGE', 'Mengelola pengguna dan peran', 'Administrasi'],
            ['UNIT_MANAGE', 'Mengelola unit', 'Administrasi'],
            ['SYSTEM_SETTINGS_MANAGE', 'Mengelola pengaturan sistem', 'Administrasi'],
        ];

        foreach ($permissions as [$name, $label, $group]) {
            Permission::query()->updateOrCreate(['name' => $name], ['label' => $label, 'group' => $group]);
        }

        $roles = [
            'super_admin' => [
                'label' => 'Super Admin',
                'description' => 'Administrasi sistem. Tidak otomatis memiliki wewenang persetujuan keuangan.',
                'permissions' => [
                    'USER_MANAGE', 'UNIT_MANAGE', 'SYSTEM_SETTINGS_MANAGE', 'AUDIT_VIEW',
                    'REPORT_VIEW', 'REPORT_EXPORT', 'FINANCE_VIEW', 'PERIOD_OPEN', 'PERIOD_CLOSE',
                    'BUDGET_VIEW', 'RECONCILIATION_VIEW',
                ],
            ],
            'bendahara_yayasan' => [
                'label' => 'Bendahara Yayasan',
                'description' => 'Konsolidasi, persetujuan, posting, anggaran, rekonsiliasi, dan penutupan periode.',
                'permissions' => [
                    'FINANCE_VIEW', 'FINANCE_CREATE', 'FINANCE_EDIT', 'FINANCE_SUBMIT', 'FINANCE_VERIFY',
                    'FINANCE_APPROVE', 'FINANCE_REJECT', 'FINANCE_POST', 'FINANCE_ADJUST',
                    'BUDGET_VIEW', 'BUDGET_CREATE', 'BUDGET_EDIT', 'BUDGET_APPROVE',
                    'RECONCILIATION_VIEW', 'RECONCILIATION_CREATE',
                    'REPORT_VIEW', 'REPORT_EXPORT', 'PERIOD_OPEN', 'PERIOD_CLOSE', 'AUDIT_VIEW',
                ],
            ],
            'bendahara_unit' => [
                'label' => 'Bendahara Unit',
                'description' => 'Mengelola transaksi unit sendiri sampai tahap pengajuan.',
                'permissions' => [
                    'FINANCE_VIEW', 'FINANCE_CREATE', 'FINANCE_EDIT', 'FINANCE_SUBMIT', 'FINANCE_ADJUST',
                    'BUDGET_VIEW', 'RECONCILIATION_VIEW', 'REPORT_VIEW', 'REPORT_EXPORT',
                ],
            ],
        ];

        foreach ($roles as $name => $role) {
            $model = Role::query()->updateOrCreate(
                ['name' => $name],
                ['label' => $role['label'], 'description' => $role['description']],
            );
            $model->permissions()->sync(Permission::query()->whereIn('name', $role['permissions'])->pluck('id'));
        }

        $this->accounts();

        $units = [
            ['RA', 'Raudhatul Athfal', 'RA', 12_000_000, 25_000_000, '001100001'],
            ['MI', 'Madrasah Ibtidaiyah', 'MI', 35_000_000, 80_000_000, '001100002'],
            ['DTA', 'Diniyah Takmiliyah Awaliyah', 'DTA', 8_000_000, 15_000_000, '001100003'],
            ['MTs', 'Madrasah Tsanawiyah', 'MTs', 28_000_000, 60_000_000, '001100004'],
            ['MA', 'Madrasah Aliyah', 'MA', 30_000_000, 70_000_000, '001100005'],
            ['PP', 'Pondok Pesantren', 'Pesantren', 45_000_000, 120_000_000, '001100006'],
            ['MT', "Majelis Ta'lim", "Majelis Ta'lim", 6_000_000, 10_000_000, '001100007'],
            ['BLKK', 'Balai Latihan Kerja Komunitas', 'BLKK', 18_000_000, 40_000_000, '001100008'],
        ];

        foreach ($units as [$code, $name, $short, $cash, $bank, $number]) {
            $unit = Unit::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'short_name' => $short, 'is_active' => true],
            );
            app(UnitProvisioner::class)->provision($unit, $cash, $bank, 'BSI', 'Rekening Operasional', $number);
        }

        $this->periods();
        $this->workflow();
        $this->settings();
        $this->users();
        $this->budgets();
    }

    private function accounts(): void
    {
        $headers = [
            ['1000', 'Aset', AccountType::Asset],
            ['2000', 'Kewajiban', AccountType::Liability],
            ['3000', 'Dana', AccountType::Equity],
            ['4000', 'Pendapatan', AccountType::Income],
            ['5000', 'Beban', AccountType::Expense],
        ];

        foreach ($headers as [$code, $name, $type]) {
            Account::query()->updateOrCreate(['code' => $code], [
                'name' => $name,
                'type' => $type,
                'normal_balance' => $type->normalBalance(),
                'parent_id' => null,
                'is_postable' => false,
                'is_system' => true,
                'is_active' => true,
            ]);
        }

        $children = [
            ['1100', 'Kas', '1000'],
            ['1200', 'Bank', '1000'],
            ['1300', 'Piutang', '1000'],
            ['4100', 'Pendapatan pendidikan', '4000'],
            ['4200', 'Donasi', '4000'],
            ['4300', 'Infaq', '4000'],
            ['4400', 'Pendapatan BLKK', '4000'],
            ['5100', 'Beban gaji', '5000'],
            ['5200', 'Beban utilitas', '5000'],
            ['5300', 'Beban operasional', '5000'],
            ['5400', 'Beban pendidikan', '5000'],
            ['5500', 'Beban pemeliharaan', '5000'],
        ];

        foreach ($children as [$code, $name, $parentCode]) {
            $parent = Account::query()->where('code', $parentCode)->firstOrFail();
            Account::query()->updateOrCreate(['code' => $code], [
                'name' => $name,
                'type' => $parent->type,
                'normal_balance' => $parent->type->normalBalance(),
                'parent_id' => $parent->id,
                'is_postable' => true,
                'is_system' => true,
                'is_active' => true,
            ]);
        }
    }

    private function periods(): void
    {
        foreach ([
            ['Juli 2026', '2026-07-01', '2026-07-31'],
            ['Agustus 2026', '2026-08-01', '2026-08-31'],
            ['September 2026', '2026-09-01', '2026-09-30'],
        ] as [$name, $start, $end]) {
            FinancialPeriod::query()->firstOrCreate(
                ['starts_on' => $start, 'ends_on' => $end],
                ['name' => $name, 'status' => 'open'],
            );
        }
    }

    private function workflow(): void
    {
        $workflow = ApprovalWorkflow::query()->updateOrCreate(
            ['subject' => 'transaction'],
            ['name' => 'Persetujuan transaksi'],
        );

        $steps = [
            [1, 'verify', 'FINANCE_VERIFY'],
            [2, 'approve', 'FINANCE_APPROVE'],
            [3, 'post', 'FINANCE_POST'],
        ];

        foreach ($steps as [$sequence, $action, $permission]) {
            $workflow->steps()->updateOrCreate(
                ['sequence' => $sequence],
                ['action' => $action, 'permission' => $permission],
            );
        }
    }

    private function settings(): void
    {
        Setting::query()->updateOrCreate(['key' => 'prevent_self_approval'], ['value' => ['enabled' => true]]);
        Setting::query()->updateOrCreate(['key' => 'budget_thresholds'], ['value' => ['values' => [75, 90, 100]]]);
        Setting::query()->updateOrCreate(['key' => 'organization_name'], ['value' => ['text' => 'Yayasan Tanwiriyyah']]);
    }

    /**
     * Satu akun untuk tiap peran. Bendahara unit ada satu per unit karena peran yang sama dibatasi unit.
     *
     * @return list<array{role: string, role_name: string, name: string, email: string, unit: ?string, unit_code: ?string}>
     */
    public static function demoLogins(): array
    {
        return [
            ['role' => 'Super Admin', 'role_name' => 'super_admin', 'name' => 'Super Admin', 'email' => 'admin@tanwiriyyah.test', 'unit' => null, 'unit_code' => null],
            ['role' => 'Bendahara Yayasan', 'role_name' => 'bendahara_yayasan', 'name' => 'Bendahara Yayasan', 'email' => 'yayasan@tanwiriyyah.test', 'unit' => null, 'unit_code' => null],
            ['role' => 'Bendahara Unit', 'role_name' => 'bendahara_unit', 'name' => 'Bendahara RA', 'email' => 'ra@tanwiriyyah.test', 'unit' => 'Raudhatul Athfal', 'unit_code' => 'RA'],
            ['role' => 'Bendahara Unit', 'role_name' => 'bendahara_unit', 'name' => 'Bendahara MI', 'email' => 'mi@tanwiriyyah.test', 'unit' => 'Madrasah Ibtidaiyah', 'unit_code' => 'MI'],
            ['role' => 'Bendahara Unit', 'role_name' => 'bendahara_unit', 'name' => 'Bendahara DTA', 'email' => 'dta@tanwiriyyah.test', 'unit' => 'Diniyah Takmiliyah', 'unit_code' => 'DTA'],
            ['role' => 'Bendahara Unit', 'role_name' => 'bendahara_unit', 'name' => 'Bendahara MTs', 'email' => 'mts@tanwiriyyah.test', 'unit' => 'Madrasah Tsanawiyah', 'unit_code' => 'MTs'],
            ['role' => 'Bendahara Unit', 'role_name' => 'bendahara_unit', 'name' => 'Bendahara MA', 'email' => 'ma@tanwiriyyah.test', 'unit' => 'Madrasah Aliyah', 'unit_code' => 'MA'],
            ['role' => 'Bendahara Unit', 'role_name' => 'bendahara_unit', 'name' => 'Bendahara Pesantren', 'email' => 'pesantren@tanwiriyyah.test', 'unit' => 'Pondok Pesantren', 'unit_code' => 'PP'],
            ['role' => 'Bendahara Unit', 'role_name' => 'bendahara_unit', 'name' => "Bendahara Majelis Ta'lim", 'email' => 'majelis@tanwiriyyah.test', 'unit' => "Majelis Ta'lim", 'unit_code' => 'MT'],
            ['role' => 'Bendahara Unit', 'role_name' => 'bendahara_unit', 'name' => 'Bendahara BLKK', 'email' => 'blkk@tanwiriyyah.test', 'unit' => 'Balai Latihan Kerja', 'unit_code' => 'BLKK'],
        ];
    }

    private function users(): void
    {
        foreach (self::demoLogins() as $account) {
            $unitId = $account['unit_code'] === null
                ? null
                : Unit::query()->where('code', $account['unit_code'])->firstOrFail()->id;

            $user = $this->user($account['name'], $account['email'], $unitId);
            $user->roles()->sync(Role::query()->where('name', $account['role_name'])->pluck('id'));
        }
    }

    private function user(string $name, string $email, ?int $unitId): User
    {
        return User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => self::PASSWORD,
                'unit_id' => $unitId,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
    }

    private function budgets(): void
    {
        $period = FinancialPeriod::query()->where('name', 'September 2026')->firstOrFail();
        $yayasan = User::query()->where('email', 'yayasan@tanwiriyyah.test')->firstOrFail();
        $accounts = Account::query()->whereIn('code', ['5100', '5200', '5300', '5400', '5500'])->get()->keyBy('code');

        $plans = [
            'RA' => ['5100' => 8_000_000, '5200' => 2_000_000, '5300' => 3_000_000, '5400' => 4_000_000, '5500' => 1_500_000],
            'MI' => ['5100' => 25_000_000, '5200' => 4_000_000, '5300' => 8_000_000, '5400' => 10_000_000, '5500' => 3_000_000],
            'DTA' => ['5100' => 6_000_000, '5200' => 1_500_000, '5300' => 2_000_000, '5400' => 2_500_000, '5500' => 1_000_000],
            'MTs' => ['5100' => 20_000_000, '5200' => 4_500_000, '5300' => 7_000_000, '5400' => 8_000_000, '5500' => 2_500_000],
            'MA' => ['5100' => 22_000_000, '5200' => 5_000_000, '5300' => 7_500_000, '5400' => 9_000_000, '5500' => 3_000_000],
            'PP' => ['5100' => 30_000_000, '5200' => 8_000_000, '5300' => 12_000_000, '5400' => 10_000_000, '5500' => 6_000_000],
            'MT' => ['5100' => 3_000_000, '5200' => 1_000_000, '5300' => 1_500_000, '5400' => 1_000_000, '5500' => 800_000],
            'BLKK' => ['5100' => 12_000_000, '5200' => 3_000_000, '5300' => 5_000_000, '5400' => 4_000_000, '5500' => 2_000_000],
        ];

        foreach ($plans as $code => $lines) {
            $unit = Unit::query()->where('code', $code)->firstOrFail();
            $budget = Budget::query()->updateOrCreate(
                ['unit_id' => $unit->id, 'period_id' => $period->id],
                [
                    'name' => 'Anggaran '.$unit->code.' September 2026',
                    'status' => 'approved',
                    'created_by' => $yayasan->id,
                    'approved_by' => $yayasan->id,
                    'approved_at' => now(),
                ],
            );

            foreach ($lines as $accountCode => $planned) {
                $budget->lines()->updateOrCreate(
                    ['account_id' => $accounts[$accountCode]->id],
                    ['planned' => $planned],
                );
            }
        }
    }
}
