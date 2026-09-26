<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\FinancialPeriod;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;
use App\Services\ApprovalEngine;
use App\Services\PeriodGuard;
use App\Services\ReconciliationService;
use App\Services\TransactionService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $transactions = app(TransactionService::class);
        $engine = app(ApprovalEngine::class);
        $yayasan = User::query()->where('email', 'yayasan@tanwiriyyah.test')->firstOrFail();

        $story = [
            'RA' => ['4100', [6_500_000, 7_000_000, 7_200_000], '5100', [3_200_000, 3_400_000, 3_100_000]],
            'MI' => ['4100', [15_000_000, 16_500_000, 18_000_000], '5100', [10_000_000, 11_000_000, 12_000_000]],
            'DTA' => ['4100', [3_200_000, 3_400_000, 3_600_000], '5400', [1_400_000, 1_500_000, 1_600_000]],
            'MTs' => ['4100', [12_000_000, 12_800_000, 13_500_000], '5100', [8_000_000, 8_400_000, 8_800_000]],
            'MA' => ['4100', [13_000_000, 13_600_000, 14_200_000], '5100', [9_000_000, 9_200_000, 9_500_000]],
            'PP' => ['4100', [22_000_000, 23_000_000, 24_500_000], '5300', [9_000_000, 9_500_000, 10_000_000]],
            'MT' => ['4300', [2_500_000, 2_800_000, 3_000_000], '5300', [900_000, 1_000_000, 1_100_000]],
            'BLKK' => ['4400', [8_000_000, 8_600_000, 9_400_000], '5100', [4_000_000, 4_200_000, 4_400_000]],
        ];

        $months = ['2026-07-12', '2026-08-12', '2026-09-12'];

        foreach ($story as $code => [$incomeCode, $incomes, $expenseCode, $expenses]) {
            $unitUser = User::query()->where('email', $this->email($code))->firstOrFail();
            $unit = Unit::query()->where('code', $code)->firstOrFail();
            $cash = CashAccount::query()->where('unit_id', $unit->id)->firstOrFail();
            $bank = BankAccount::query()->where('unit_id', $unit->id)->firstOrFail();
            $income = Account::query()->where('code', $incomeCode)->firstOrFail();
            $expense = Account::query()->where('code', $expenseCode)->firstOrFail();

            foreach ($months as $index => $date) {
                $this->post($transactions, $engine, $unitUser, $yayasan, [
                    'unit_id' => $unit->id,
                    'type' => 'income',
                    'transacted_on' => $date,
                    'posting_date' => $date,
                    'category_account_id' => $income->id,
                    'payment_method' => 'cash',
                    'cash_account_id' => $cash->id,
                    'amount' => $incomes[$index],
                    'description' => 'Penerimaan '.$unit->short_name.' '.substr($date, 0, 7),
                    'reference' => 'IN-'.$code.'-'.($index + 1),
                ]);

                $this->post($transactions, $engine, $unitUser, $yayasan, [
                    'unit_id' => $unit->id,
                    'type' => 'expense',
                    'transacted_on' => $date,
                    'posting_date' => $date,
                    'category_account_id' => $expense->id,
                    'payment_method' => 'bank',
                    'bank_account_id' => $bank->id,
                    'amount' => $expenses[$index],
                    'description' => 'Pembayaran '.$expense->name.' '.$unit->short_name,
                    'reference' => 'EX-'.$code.'-'.($index + 1),
                ]);
            }
        }

        $this->lock('Juli 2026');
        $this->lock('Agustus 2026');
        $this->septemberExtras($transactions, $engine, $yayasan);
    }

    private function septemberExtras(TransactionService $transactions, ApprovalEngine $engine, User $yayasan): void
    {
        $miUser = User::query()->where('email', 'mi@tanwiriyyah.test')->firstOrFail();
        $mi = Unit::query()->where('code', 'MI')->firstOrFail();
        $cash = CashAccount::query()->where('unit_id', $mi->id)->firstOrFail();
        $bank = BankAccount::query()->where('unit_id', $mi->id)->firstOrFail();
        $utilities = Account::query()->where('code', '5200')->firstOrFail();
        $operations = Account::query()->where('code', '5300')->firstOrFail();

        $utility = $this->post($transactions, $engine, $miUser, $yayasan, [
            'unit_id' => $mi->id,
            'type' => 'expense',
            'transacted_on' => '2026-09-18',
            'posting_date' => '2026-09-18',
            'category_account_id' => $utilities->id,
            'payment_method' => 'bank',
            'bank_account_id' => $bank->id,
            'amount' => 3_200_000,
            'description' => 'Listrik dan air MI September',
            'reference' => 'INV-MI-0926',
        ]);

        $this->attachSample($utility, $miUser);

        $blkkUser = User::query()->where('email', 'blkk@tanwiriyyah.test')->firstOrFail();
        $blkk = Unit::query()->where('code', 'BLKK')->firstOrFail();
        $blkkBank = BankAccount::query()->where('unit_id', $blkk->id)->firstOrFail();

        $this->post($transactions, $engine, $blkkUser, $yayasan, [
            'unit_id' => $blkk->id,
            'type' => 'expense',
            'transacted_on' => '2026-09-20',
            'posting_date' => '2026-09-20',
            'category_account_id' => $operations->id,
            'payment_method' => 'bank',
            'bank_account_id' => $blkkBank->id,
            'amount' => 6_000_000,
            'description' => 'Operasional pelatihan BLKK melebihi rencana',
            'reference' => 'BLKK-OPS-09',
        ]);

        $this->post($transactions, $engine, $miUser, $yayasan, [
            'unit_id' => $mi->id,
            'type' => 'transfer',
            'transacted_on' => '2026-09-21',
            'posting_date' => '2026-09-21',
            'payment_method' => 'cash',
            'cash_account_id' => $cash->id,
            'destination_bank_account_id' => $bank->id,
            'amount' => 5_000_000,
            'description' => 'Setoran kas MI ke rekening BSI',
            'reference' => 'TRF-MI-0921',
        ]);

        $draft = $transactions->create($miUser, [
            'idempotency_key' => (string) Str::uuid(),
            'unit_id' => $mi->id,
            'type' => 'expense',
            'transacted_on' => '2026-09-24',
            'posting_date' => '2026-09-24',
            'category_account_id' => $operations->id,
            'payment_method' => 'cash',
            'cash_account_id' => $cash->id,
            'amount' => 750_000,
            'description' => 'Draf pembelian alat tulis MI',
            'reference' => null,
        ]);

        $pending = $transactions->create($miUser, [
            'idempotency_key' => (string) Str::uuid(),
            'unit_id' => $mi->id,
            'type' => 'expense',
            'transacted_on' => '2026-09-25',
            'posting_date' => '2026-09-25',
            'category_account_id' => $operations->id,
            'payment_method' => 'cash',
            'cash_account_id' => $cash->id,
            'amount' => 1_250_000,
            'description' => 'Konsumsi rapat guru MI',
            'reference' => 'MI-RAPAT-09',
        ]);
        $engine->submit($pending, $miUser);

        $this->statement($yayasan, $bank, $utility, $draft);
    }

    private function statement(User $yayasan, BankAccount $bank, Transaction $matched, Transaction $ignored): void
    {
        $csv = "date,description,amount,reference\n"
            ."2026-09-18,Listrik dan air,-3200000,INV-MI-0926\n"
            ."2026-09-22,Biaya administrasi bank,-15000,ADM-09\n";
        $path = storage_path('app/private/demo-statement.csv');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $csv);
        $file = new UploadedFile($path, 'rekening-mi.csv', 'text/csv', null, true);
        $statement = app(ReconciliationService::class)->import($yayasan, $bank, $file, [
            'statement_date' => '2026-09-26',
            'opening_balance' => 80_000_000,
            'closing_balance' => 76_785_000,
        ]);

        $line = $statement->lines()->where('reference', 'INV-MI-0926')->firstOrFail();
        app(ReconciliationService::class)->match($yayasan, $line, $matched);
        unset($ignored);
    }

    private function attachSample(Transaction $transaction, User $user): void
    {
        $binary = "%PDF-1.1\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";
        $path = 'documents/'.$transaction->id.'/bukti-listrik.pdf';
        Storage::disk('local')->put($path, $binary);
        $transaction->documents()->create([
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'bukti-listrik.pdf',
            'mime' => 'application/pdf',
            'size' => strlen($binary),
            'kind' => 'invoice',
            'uploaded_by' => $user->id,
        ]);
    }

    private function post(TransactionService $transactions, ApprovalEngine $engine, User $unitUser, User $yayasan, array $data): Transaction
    {
        $data['idempotency_key'] = (string) Str::uuid();
        $transaction = $transactions->create($unitUser, $data);
        $engine->submit($transaction, $unitUser);
        $engine->act($transaction->refresh(), $yayasan, 'verify', 'Bukti sesuai.');
        $engine->act($transaction->refresh(), $yayasan, 'approve', 'Disetujui.');

        return $engine->act($transaction->refresh(), $yayasan, 'post');
    }

    private function lock(string $name): void
    {
        $period = FinancialPeriod::query()->where('name', $name)->firstOrFail();
        $yayasan = User::query()->where('email', 'yayasan@tanwiriyyah.test')->firstOrFail();
        app(PeriodGuard::class)->close($period, $yayasan);
    }

    private function email(string $code): string
    {
        return match ($code) {
            'PP' => 'pesantren@tanwiriyyah.test',
            'MT' => 'majelis@tanwiriyyah.test',
            default => strtolower($code).'@tanwiriyyah.test',
        };
    }
}
