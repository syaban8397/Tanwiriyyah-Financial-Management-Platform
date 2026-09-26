<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\Unit;

class UnitProvisioner
{
    public function provision(
        Unit $unit,
        int $cashOpening = 0,
        int $bankOpening = 0,
        string $bankName = 'Bank Syariah',
        string $accountName = 'Rekening Operasional',
        string $accountNumber = '0000000000',
    ): void {
        $cash = Account::query()->where('code', '1100')->firstOrFail();
        $bank = Account::query()->where('code', '1200')->firstOrFail();

        CashAccount::query()->firstOrCreate(
            ['unit_id' => $unit->id, 'name' => 'Kas '.$unit->code],
            ['account_id' => $cash->id, 'opening_balance' => $cashOpening, 'is_active' => true],
        );

        BankAccount::query()->firstOrCreate(
            ['unit_id' => $unit->id, 'account_name' => $accountName.' '.$unit->code],
            [
                'account_id' => $bank->id,
                'bank_name' => $bankName,
                'account_number' => $accountNumber,
                'opening_balance' => $bankOpening,
                'is_active' => true,
            ],
        );
    }
}
