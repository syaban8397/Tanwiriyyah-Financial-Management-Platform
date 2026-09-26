<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('name');
            $table->string('short_name', 80);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('password');
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->unique();
            $table->string('label');
            $table->string('group', 64);
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->unique();
            $table->string('label');
            $table->text('description')->nullable();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value');
            $table->timestamps();
        });

        Schema::create('financial_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 16)->default('open')->index();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['starts_on', 'ends_on']);
        });

        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('name');
            $table->string('type', 16)->index();
            $table->string('normal_balance', 8);
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->boolean('is_postable')->default(true);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cash_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->bigInteger('opening_balance')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['unit_id', 'is_active']);
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->string('bank_name');
            $table->string('account_name');
            $table->text('account_number');
            $table->bigInteger('opening_balance')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['unit_id', 'is_active']);
        });

        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subject', 32)->unique();
            $table->timestamps();
        });

        Schema::create('approval_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_workflow_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->string('action', 24);
            $table->string('permission', 64);
            $table->unique(['approval_workflow_id', 'sequence']);
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('period_id')->constrained('financial_periods')->restrictOnDelete();
            $table->string('type', 16)->index();
            $table->string('status', 24)->index();
            $table->date('transacted_on');
            $table->date('posting_date');
            $table->foreignId('category_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignId('cash_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('destination_cash_account_id')->nullable()->constrained('cash_accounts')->nullOnDelete();
            $table->foreignId('destination_bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->string('payment_method', 16);
            $table->bigInteger('amount');
            $table->string('effect', 16)->nullable();
            $table->string('description');
            $table->string('reference')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->foreignId('original_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->string('idempotency_key', 80)->unique();
            $table->unsignedInteger('approval_cycle')->default(1);
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->index(['unit_id', 'status', 'transacted_on']);
            $table->index(['period_id', 'status']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('period_id')->constrained('financial_periods')->restrictOnDelete();
            $table->date('entry_date');
            $table->string('description');
            $table->foreignId('posted_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['unit_id', 'entry_date']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->bigInteger('debit')->default(0);
            $table->bigInteger('credit')->default(0);
            $table->string('memo')->nullable();
            $table->index(['account_id', 'unit_id']);
            $table->index('cash_account_id');
            $table->index('bank_account_id');
        });

        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('financial_periods')->cascadeOnDelete();
            $table->string('name');
            $table->string('status', 16)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['unit_id', 'period_id']);
        });

        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->bigInteger('planned');
            $table->unique(['budget_id', 'account_id']);
        });

        Schema::create('budget_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_line_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('threshold');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['budget_line_id', 'threshold']);
        });

        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('cycle')->default(1);
            $table->string('action', 24);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['transaction_id', 'created_at']);
        });

        Schema::create('bank_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('period_id')->nullable()->constrained('financial_periods')->nullOnDelete();
            $table->date('statement_date');
            $table->bigInteger('opening_balance');
            $table->bigInteger('closing_balance');
            $table->string('status', 16)->default('open');
            $table->foreignId('imported_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_statement_id')->constrained()->cascadeOnDelete();
            $table->date('line_date');
            $table->string('description');
            $table->bigInteger('amount');
            $table->string('reference')->nullable();
            $table->string('match_status', 16)->default('unmatched')->index();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 16)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 128);
            $table->unsignedInteger('size');
            $table->string('kind', 24);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role')->nullable();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 64)->index();
            $table->string('entity_type', 64);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::create('domain_events', function (Blueprint $table) {
            $table->id();
            $table->string('type', 64)->index();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('report_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('report', 64);
            $table->json('filters');
            $table->string('status', 16)->default('preparing')->index();
            $table->string('format', 8);
            $table->string('path')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('report_runs');
        Schema::dropIfExists('domain_events');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('bank_statements');
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('budget_alerts');
        Schema::dropIfExists('budget_lines');
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('approval_workflow_steps');
        Schema::dropIfExists('approval_workflows');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('cash_accounts');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('financial_periods');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_id');
            $table->dropColumn('is_active');
        });

        Schema::dropIfExists('units');
    }
};
