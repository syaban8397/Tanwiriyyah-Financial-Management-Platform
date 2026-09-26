<?php

namespace Tests\Feature\Finance;

use App\Enums\TransactionStatus;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\FinancialPeriod;
use App\Models\JournalEntry;
use App\Models\ReportRun;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;
use App\Services\ApprovalEngine;
use App\Services\BalanceService;
use App\Services\ReportBuilder;
use Database\Seeders\FinanceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CriticalFlowsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FinanceCatalogSeeder::class);
    }

    public function test_income_is_approved_posted_and_visible_on_dashboard(): void
    {
        $mi = $this->user('mi@tanwiriyyah.test');
        $yayasan = $this->user('yayasan@tanwiriyyah.test');
        $cash = $this->cash('MI');
        $before = app(BalanceService::class)->cashBalance($cash);

        $transaction = $this->createDraft($mi, [
            'type' => 'income',
            'category_account_id' => $this->account('4100'),
            'payment_method' => 'cash',
            'cash_account_id' => $cash->id,
            'amount' => 5_000_000,
            'description' => 'SPP September',
        ]);

        $this->actingAs($mi)->post("/transactions/{$transaction->id}/submit")->assertRedirect();
        $this->actingAs($yayasan)->post("/transactions/{$transaction->id}/verify", ['comment' => 'Sesuai'])->assertRedirect();
        $this->actingAs($yayasan)->post("/transactions/{$transaction->id}/approve", ['comment' => 'Disetujui'])->assertRedirect();
        $this->actingAs($yayasan)->post("/transactions/{$transaction->id}/post")->assertRedirect();

        $transaction->refresh();
        $this->assertSame(TransactionStatus::Posted, $transaction->status);
        $entry = $transaction->journalEntry()->with('lines')->first();
        $this->assertNotNull($entry);
        $this->assertSame((int) $entry->lines->sum('debit'), (int) $entry->lines->sum('credit'));
        $this->assertSame($before + 5_000_000, app(BalanceService::class)->cashBalance($cash->fresh()));

        $this->actingAs($yayasan)->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Index')
            ->where('position.revenue', 5_000_000)
            ->where('position.net', 5_000_000));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'transaction.post',
            'entity_id' => $transaction->id,
        ]);
        $this->assertTrue($mi->fresh()->notifications()->where('data->kind', 'posted')->exists());
    }

    public function test_bendahara_mi_cannot_read_another_units_transaction(): void
    {
        $mts = $this->user('mts@tanwiriyyah.test');
        $mi = $this->user('mi@tanwiriyyah.test');
        $transaction = $this->createDraft($mts, [
            'unit_id' => Unit::query()->where('code', 'MTs')->value('id'),
            'type' => 'income',
            'category_account_id' => $this->account('4100'),
            'payment_method' => 'cash',
            'cash_account_id' => $this->cash('MTs')->id,
            'amount' => 1_000_000,
            'description' => 'Penerimaan MTs',
        ]);

        $this->actingAs($mi)->get("/transactions/{$transaction->id}")->assertForbidden();
        $this->actingAs($mi)->getJson("/api/transactions/{$transaction->id}")->assertForbidden();
        $this->actingAs($mts)->get("/transactions/{$transaction->id}")->assertOk();
    }

    public function test_expense_with_document_posts_a_balanced_journal(): void
    {
        Storage::fake('local');
        $mi = $this->user('mi@tanwiriyyah.test');
        $yayasan = $this->user('yayasan@tanwiriyyah.test');
        $bank = $this->bank('MI');

        $this->actingAs($mi)->post('/transactions', [
            'idempotency_key' => (string) Str::uuid(),
            'unit_id' => $this->unit('MI'),
            'type' => 'expense',
            'transacted_on' => '2026-09-11',
            'posting_date' => '2026-09-11',
            'category_account_id' => $this->account('5200'),
            'payment_method' => 'bank',
            'bank_account_id' => $bank->id,
            'amount' => 2_000_000,
            'description' => 'Listrik MI',
            'documents' => [UploadedFile::fake()->create('invoice.pdf', 80, 'application/pdf')],
            'document_kinds' => ['invoice'],
        ])->assertRedirect();

        $transaction = Transaction::query()->firstOrFail();
        $this->assertCount(1, $transaction->documents);
        Storage::disk('local')->assertExists($transaction->documents->first()->path);

        $this->finish($transaction, $mi, $yayasan);
        $transaction->refresh();
        $lines = $transaction->journalEntry->lines;
        $this->assertSame(2_000_000, (int) $lines->sum('debit'));
        $this->assertSame(2_000_000, (int) $lines->sum('credit'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'transaction.post', 'entity_id' => $transaction->id]);
    }

    public function test_transfer_moves_cash_to_bank_without_changing_net_income(): void
    {
        $mi = $this->user('mi@tanwiriyyah.test');
        $yayasan = $this->user('yayasan@tanwiriyyah.test');
        $cash = $this->cash('MI');
        $bank = $this->bank('MI');
        $balances = app(BalanceService::class);
        $cashBefore = $balances->cashBalance($cash);
        $bankBefore = $balances->bankBalance($bank);

        $this->actingAs($mi)->post('/transfers', [
            'idempotency_key' => (string) Str::uuid(),
            'unit_id' => $this->unit('MI'),
            'transacted_on' => '2026-09-12',
            'posting_date' => '2026-09-12',
            'amount' => 5_000_000,
            'description' => 'Setoran kas',
            'source_type' => 'cash',
            'source_id' => $cash->id,
            'destination_type' => 'bank',
            'destination_id' => $bank->id,
        ])->assertRedirect();

        $transaction = Transaction::query()->firstOrFail();
        $this->finish($transaction, $mi, $yayasan);

        $this->assertSame($cashBefore - 5_000_000, $balances->cashBalance($cash->fresh()));
        $this->assertSame($bankBefore + 5_000_000, $balances->bankBalance($bank->fresh()));
        $position = $balances->position(FinancialPeriod::query()->where('name', 'September 2026')->firstOrFail(), [$this->unit('MI')]);
        $this->assertSame(0, $position['revenue']);
        $this->assertSame(0, $position['expense']);
        $this->assertSame(0, $position['net']);
    }

    public function test_budget_utilization_raises_a_threshold_alert(): void
    {
        $mi = $this->user('mi@tanwiriyyah.test');
        $yayasan = $this->user('yayasan@tanwiriyyah.test');
        $transaction = $this->createDraft($mi, [
            'type' => 'expense',
            'category_account_id' => $this->account('5200'),
            'payment_method' => 'bank',
            'bank_account_id' => $this->bank('MI')->id,
            'amount' => 3_200_000,
            'description' => 'Utilitas mendekati batas',
        ]);
        $this->finish($transaction, $mi, $yayasan);

        $this->assertDatabaseHas('budget_alerts', ['threshold' => 75]);
        $this->assertTrue($yayasan->fresh()->notifications()->where('data->kind', 'budget_warning')->exists());
    }

    public function test_locked_period_rejects_edits_and_backdating(): void
    {
        $mi = $this->user('mi@tanwiriyyah.test');
        $yayasan = $this->user('yayasan@tanwiriyyah.test');
        $transaction = $this->createDraft($mi, [
            'type' => 'income',
            'category_account_id' => $this->account('4100'),
            'payment_method' => 'cash',
            'cash_account_id' => $this->cash('MI')->id,
            'amount' => 1_500_000,
            'description' => 'Penerimaan sebelum tutup buku',
        ]);
        $this->finish($transaction, $mi, $yayasan);

        $period = FinancialPeriod::query()->where('name', 'September 2026')->firstOrFail();
        $this->actingAs($yayasan)->post("/periods/{$period->id}/close")->assertRedirect();
        $this->assertSame('locked', $period->fresh()->status->value);

        $this->actingAs($mi)->put("/transactions/{$transaction->id}", $this->payload($mi, [
            'type' => 'income',
            'category_account_id' => $this->account('4100'),
            'payment_method' => 'cash',
            'cash_account_id' => $this->cash('MI')->id,
            'amount' => 9_999_000,
            'description' => 'Ubah diam-diam',
        ]))->assertSessionHasErrors('status');

        $this->assertSame(1_500_000, (int) $transaction->fresh()->amount);

        $this->actingAs($mi)->post('/transactions', $this->payload($mi, [
            'type' => 'income',
            'category_account_id' => $this->account('4100'),
            'payment_method' => 'cash',
            'cash_account_id' => $this->cash('MI')->id,
            'amount' => 100_000,
            'description' => 'Backdate',
        ]))->assertSessionHasErrors('period');
    }

    public function test_duplicate_submission_creates_one_transaction(): void
    {
        $mi = $this->user('mi@tanwiriyyah.test');
        $payload = $this->payload($mi, [
            'type' => 'income',
            'category_account_id' => $this->account('4100'),
            'payment_method' => 'cash',
            'cash_account_id' => $this->cash('MI')->id,
            'amount' => 750_000,
            'description' => 'Klik ganda',
        ]);

        $this->actingAs($mi)->post('/transactions', $payload)->assertRedirect();
        $this->actingAs($mi)->post('/transactions', $payload)->assertRedirect();

        $this->assertSame(1, Transaction::query()->count());
    }

    public function test_realtime_stream_receives_a_submitted_transaction_for_authorized_users_only(): void
    {
        $mi = $this->user('mi@tanwiriyyah.test');
        $yayasan = $this->user('yayasan@tanwiriyyah.test');
        $mts = $this->user('mts@tanwiriyyah.test');
        $transaction = $this->createDraft($mi, [
            'type' => 'income',
            'category_account_id' => $this->account('4100'),
            'payment_method' => 'cash',
            'cash_account_id' => $this->cash('MI')->id,
            'amount' => 900_000,
            'description' => 'Untuk realtime',
        ]);
        $this->actingAs($mi)->post("/transactions/{$transaction->id}/submit")->assertRedirect();

        $yayasanStream = $this->actingAs($yayasan)->get('/realtime/stream?after=0');
        $this->assertStringContainsString('transaction.submitted', $yayasanStream->streamedContent());

        $otherUnit = $this->actingAs($mts)->get('/realtime/stream?after=0')->streamedContent();
        $this->assertStringNotContainsString('transaction.submitted', $otherUnit);

        $tip = $this->actingAs($yayasan)->get('/realtime/stream')->streamedContent();
        $this->assertStringNotContainsString('transaction.submitted', $tip);
    }

    public function test_two_expenses_reduce_the_bank_balance_exactly_once_each(): void
    {
        $mi = $this->user('mi@tanwiriyyah.test');
        $yayasan = $this->user('yayasan@tanwiriyyah.test');
        $bank = $this->bank('MI');
        $before = app(BalanceService::class)->bankBalance($bank);

        foreach ([3_000_000, 2_000_000] as $amount) {
            $transaction = $this->createDraft($mi, [
                'type' => 'expense',
                'category_account_id' => $this->account('5300'),
                'payment_method' => 'bank',
                'bank_account_id' => $bank->id,
                'amount' => $amount,
                'description' => 'Beban '.$amount,
            ]);
            $this->finish($transaction, $mi, $yayasan);
        }

        $posted = Transaction::query()->where('status', TransactionStatus::Posted)->firstOrFail();

        try {
            app(ApprovalEngine::class)->act($posted, $yayasan, 'post');
            $this->fail('Posting kedua harus ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame($before - 5_000_000, app(BalanceService::class)->bankBalance($bank->fresh()));
        $this->assertSame(2, JournalEntry::query()->count());
    }

    public function test_income_report_matches_the_ledger(): void
    {
        $mi = $this->user('mi@tanwiriyyah.test');
        $yayasan = $this->user('yayasan@tanwiriyyah.test');
        $transaction = $this->createDraft($mi, [
            'type' => 'income',
            'category_account_id' => $this->account('4100'),
            'payment_method' => 'cash',
            'cash_account_id' => $this->cash('MI')->id,
            'amount' => 4_250_000,
            'description' => 'Untuk laporan',
        ]);
        $this->finish($transaction, $mi, $yayasan);

        $period = FinancialPeriod::query()->where('name', 'September 2026')->firstOrFail();
        $report = app(ReportBuilder::class)->build($yayasan, 'income', ['period_id' => $period->id]);
        $ledger = app(BalanceService::class)->total('income', null, '2026-09-01', '2026-09-30');
        $this->assertSame($ledger, $report['total']);
        $this->assertSame(4_250_000, $report['total']);

        $this->actingAs($yayasan)->post('/reports', [
            'report' => 'income',
            'format' => 'xlsx',
            'period_id' => $period->id,
        ])->assertRedirect();

        $run = ReportRun::query()->firstOrFail();
        $this->assertSame('ready', $run->status);
        $this->assertFileExists(storage_path('app/private/'.$run->path));
    }

    public function test_adjustment_posts_only_the_difference_and_keeps_the_original(): void
    {
        $mi = $this->user('mi@tanwiriyyah.test');
        $yayasan = $this->user('yayasan@tanwiriyyah.test');
        $cash = $this->cash('MI');
        $before = app(BalanceService::class)->cashBalance($cash);
        $original = $this->createDraft($mi, [
            'type' => 'income',
            'category_account_id' => $this->account('4100'),
            'payment_method' => 'cash',
            'cash_account_id' => $cash->id,
            'amount' => 5_000_000,
            'description' => 'Penerimaan yang akan disesuaikan',
        ]);
        $this->finish($original, $mi, $yayasan);

        $this->actingAs($mi)->post("/transactions/{$original->id}/adjust", [
            'idempotency_key' => (string) Str::uuid(),
            'amount' => 6_000_000,
            'transacted_on' => '2026-09-15',
            'posting_date' => '2026-09-15',
            'description' => 'Koreksi penerimaan menjadi 6 juta',
        ])->assertRedirect();

        $adjustment = Transaction::query()->where('original_transaction_id', $original->id)->firstOrFail();
        $this->assertSame(1_000_000, (int) $adjustment->amount);
        $this->assertSame(5_000_000, (int) $original->fresh()->amount);
        $this->finish($adjustment, $mi, $yayasan);

        $this->assertSame($before + 6_000_000, app(BalanceService::class)->cashBalance($cash->fresh()));
        $this->assertSame(6_000_000, app(BalanceService::class)->total('income', [$this->unit('MI')], '2026-09-01', '2026-09-30'));
    }

    public function test_creator_cannot_approve_their_own_transaction(): void
    {
        $yayasan = $this->user('yayasan@tanwiriyyah.test');
        $transaction = $this->createDraft($yayasan, [
            'type' => 'income',
            'category_account_id' => $this->account('4200'),
            'payment_method' => 'cash',
            'cash_account_id' => $this->cash('MI')->id,
            'amount' => 500_000,
            'description' => 'Dibuat bendahara yayasan',
        ]);

        $this->actingAs($yayasan)->post("/transactions/{$transaction->id}/submit")->assertRedirect();
        $this->actingAs($yayasan)->post("/transactions/{$transaction->id}/verify")->assertSessionHasErrors('approval');
        $this->assertSame(TransactionStatus::Submitted, $transaction->fresh()->status);
    }

    private function finish(Transaction $transaction, User $maker, User $yayasan): void
    {
        $this->actingAs($maker)->post("/transactions/{$transaction->id}/submit")->assertRedirect();
        $this->actingAs($yayasan)->post("/transactions/{$transaction->id}/verify")->assertRedirect();
        $this->actingAs($yayasan)->post("/transactions/{$transaction->id}/approve")->assertRedirect();
        $this->actingAs($yayasan)->post("/transactions/{$transaction->id}/post")->assertRedirect();
    }

    private function createDraft(User $user, array $overrides): Transaction
    {
        $this->actingAs($user)->post('/transactions', $this->payload($user, $overrides))->assertRedirect();

        return Transaction::query()->latest('id')->firstOrFail();
    }

    private function payload(User $user, array $overrides): array
    {
        return array_merge([
            'idempotency_key' => (string) Str::uuid(),
            'unit_id' => $user->unit_id ?: $this->unit('MI'),
            'transacted_on' => '2026-09-10',
            'posting_date' => '2026-09-10',
            'description' => 'Transaksi uji',
            'reference' => null,
        ], $overrides);
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    private function unit(string $code): int
    {
        return (int) Unit::query()->where('code', $code)->value('id');
    }

    private function account(string $code): int
    {
        return (int) Account::query()->where('code', $code)->value('id');
    }

    private function cash(string $code): CashAccount
    {
        return CashAccount::query()->where('unit_id', $this->unit($code))->firstOrFail();
    }

    private function bank(string $code): BankAccount
    {
        return BankAccount::query()->where('unit_id', $this->unit($code))->firstOrFail();
    }
}
