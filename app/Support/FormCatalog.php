<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\FinancialPeriod;
use App\Models\Unit;
use App\Models\User;

class FormCatalog
{
    public function for(User $user): array
    {
        return [
            'units' => Unit::query()
                ->when($user->unit_id, fn ($query) => $query->whereKey($user->unit_id))
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'accounts' => Account::query()
                ->where('is_postable', true)
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'type']),
            'cash_accounts' => CashAccount::query()
                ->with('unit:id,code')
                ->visibleTo($user)
                ->where('is_active', true)
                ->get()
                ->map(fn (CashAccount $account) => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'unit_id' => $account->unit_id,
                    'unit' => $account->unit?->code,
                ])->values(),
            'bank_accounts' => BankAccount::query()
                ->with('unit:id,code')
                ->visibleTo($user)
                ->where('is_active', true)
                ->get()
                ->map(fn (BankAccount $account) => [
                    'id' => $account->id,
                    'name' => $account->bank_name,
                    'masked' => $account->masked_number,
                    'unit_id' => $account->unit_id,
                    'unit' => $account->unit?->code,
                ])->values(),
            'periods' => FinancialPeriod::query()->orderByDesc('starts_on')->get(['id', 'name', 'status', 'starts_on', 'ends_on']),
        ];
    }
}
