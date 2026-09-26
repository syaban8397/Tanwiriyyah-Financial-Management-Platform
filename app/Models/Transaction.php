<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Concerns\ScopedToUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transaction extends Model
{
    use ScopedToUnit;

    protected $fillable = [
        'number', 'unit_id', 'period_id', 'type', 'status', 'transacted_on', 'posting_date',
        'category_account_id', 'cash_account_id', 'bank_account_id', 'destination_cash_account_id',
        'destination_bank_account_id', 'payment_method', 'amount', 'effect', 'description', 'reference',
        'created_by', 'submitted_by', 'submitted_at', 'reviewed_by', 'reviewed_at', 'approved_by',
        'approved_at', 'posted_by', 'posted_at', 'rejected_by', 'rejected_at', 'rejection_reason',
        'reconciled_at', 'original_transaction_id', 'idempotency_key', 'approval_cycle', 'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'status' => TransactionStatus::class,
            'payment_method' => PaymentMethod::class,
            'transacted_on' => 'date',
            'posting_date' => 'date',
            'amount' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'reconciled_at' => 'datetime',
            'approval_cycle' => 'integer',
            'lock_version' => 'integer',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'period_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'category_account_id');
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function destinationCashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'destination_cash_account_id');
    }

    public function destinationBankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'destination_bank_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function original(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_transaction_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(self::class, 'original_transaction_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class)->orderBy('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function journalEntry(): HasOne
    {
        return $this->hasOne(JournalEntry::class);
    }
}
