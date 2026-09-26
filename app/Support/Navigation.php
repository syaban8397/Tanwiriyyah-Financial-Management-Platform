<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Transaction;
use App\Models\User;

class Navigation
{
    public static function for(User $user): array
    {
        $pending = 0;

        if ($user->hasAnyPermission(['FINANCE_VERIFY', 'FINANCE_APPROVE', 'FINANCE_POST'])) {
            $pending = Transaction::query()->visibleTo($user)->whereIn('status', ['submitted', 'under_review', 'approved'])->count();
        }

        $groups = [
            [
                'id' => 'main',
                'items' => array_values(array_filter([
                    ['label' => 'Dasbor', 'href' => '/dashboard', 'icon' => 'dash'],
                ])),
            ],
            [
                'id' => 'finance',
                'label' => 'Keuangan',
                'items' => array_values(array_filter([
                    self::item($user, 'FINANCE_VIEW', 'Transaksi', '/transactions'),
                    self::item($user, 'FINANCE_VIEW', 'Jurnal', '/journal'),
                    self::item($user, 'FINANCE_VIEW', 'Buku besar', '/ledger'),
                    self::item($user, 'FINANCE_VIEW', 'Bagan akun', '/accounts'),
                ])),
            ],
            [
                'id' => 'treasury',
                'label' => 'Kas & bank',
                'items' => array_values(array_filter([
                    self::item($user, 'FINANCE_VIEW', 'Kas', '/cash'),
                    self::item($user, 'FINANCE_VIEW', 'Bank', '/bank'),
                    self::item($user, 'FINANCE_CREATE', 'Transfer', '/transfers/create'),
                    self::item($user, 'RECONCILIATION_VIEW', 'Rekonsiliasi', '/reconciliation'),
                ])),
            ],
            [
                'id' => 'planning',
                'label' => 'Pengendalian',
                'items' => array_values(array_filter([
                    self::item($user, 'BUDGET_VIEW', 'Anggaran', '/budgets'),
                    self::item($user, 'FINANCE_VIEW', 'Performa beban', '/analytics/expenses'),
                    $user->hasAnyPermission(['FINANCE_VERIFY', 'FINANCE_APPROVE', 'FINANCE_POST', 'FINANCE_REJECT'])
                        ? ['label' => 'Persetujuan', 'href' => '/approvals', 'badge' => $pending]
                        : ($user->hasPermission('FINANCE_VIEW') ? ['label' => 'Antrean', 'href' => '/approvals', 'badge' => 0] : null),
                    self::item($user, 'REPORT_VIEW', 'Laporan', '/reports'),
                    self::item($user, 'FINANCE_VIEW', 'Dokumen', '/documents'),
                    self::item($user, 'FINANCE_VIEW', 'Tanya buku', '/inquiry'),
                    self::item($user, 'AUDIT_VIEW', 'Jejak audit', '/audit'),
                    $user->hasAnyPermission(['PERIOD_OPEN', 'PERIOD_CLOSE', 'FINANCE_VIEW'])
                        ? ['label' => 'Periode', 'href' => '/periods']
                        : null,
                ])),
            ],
            [
                'id' => 'admin',
                'label' => 'Administrasi',
                'items' => array_values(array_filter([
                    self::item($user, 'USER_MANAGE', 'Pengguna', '/admin/users'),
                    self::item($user, 'USER_MANAGE', 'Peran', '/admin/roles'),
                    self::item($user, 'UNIT_MANAGE', 'Unit', '/admin/units'),
                    self::item($user, 'SYSTEM_SETTINGS_MANAGE', 'Pengaturan', '/admin/settings'),
                ])),
            ],
        ];

        return array_values(array_filter($groups, fn ($group) => $group['items'] !== []));
    }

    private static function item(User $user, string $permission, string $label, string $href): ?array
    {
        if (! $user->hasPermission($permission)) {
            return null;
        }

        return ['label' => $label, 'href' => $href];
    }
}
