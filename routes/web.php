<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ApprovalQueueController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CashAccountController;
use App\Http\Controllers\ContextController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PeriodController;
use App\Http\Controllers\RealtimeController;
use App\Http\Controllers\ReconciliationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:login');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/context/unit', [ContextController::class, 'update'])->name('context.unit');
    Route::get('/search', SearchController::class)->name('search');
    Route::get('/realtime/stream', [RealtimeController::class, 'stream'])->name('realtime.stream');

    Route::middleware('permission:FINANCE_CREATE')->group(function () {
        Route::get('/transactions/create', [TransactionController::class, 'create'])->name('transactions.create');
        Route::get('/transfers/create', [TransferController::class, 'create'])->name('transfers.create');
    });

    Route::middleware('permission:BUDGET_CREATE')->group(function () {
        Route::get('/budgets/create', [BudgetController::class, 'create'])->name('budgets.create');
    });

    Route::middleware('permission:FINANCE_VIEW')->group(function () {
        Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
        Route::get('/transactions/export', [TransactionController::class, 'export'])->name('transactions.export');
        Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
        Route::get('/journal', [JournalController::class, 'index'])->name('journal.index');
        Route::get('/journal/{journal}', [JournalController::class, 'show'])->name('journal.show');
        Route::get('/ledger', [LedgerController::class, 'index'])->name('ledger.index');
        Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
        Route::get('/cash', [CashAccountController::class, 'index'])->name('cash.index');
        Route::get('/bank', [BankAccountController::class, 'index'])->name('bank.index');
        Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
        Route::get('/analytics/expenses', [AnalyticsController::class, 'expenses'])->name('analytics.expenses');
        Route::get('/inquiry', [InquiryController::class, 'index'])->name('inquiry.index');
        Route::get('/approvals', [ApprovalQueueController::class, 'index'])->name('approvals.index');
    });

    Route::middleware('permission:FINANCE_CREATE')->group(function () {
        Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
        Route::post('/transfers', [TransferController::class, 'store'])->name('transfers.store');
        Route::post('/cash', [CashAccountController::class, 'store'])->name('cash.store');
        Route::post('/bank', [BankAccountController::class, 'store'])->name('bank.store');
    });

    Route::middleware('permission:FINANCE_EDIT')->group(function () {
        Route::get('/transactions/{transaction}/edit', [TransactionController::class, 'edit'])->name('transactions.edit');
        Route::put('/transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
        Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');
        Route::post('/transactions/{transaction}/revise', [TransactionController::class, 'revise'])->name('transactions.revise');
    });

    Route::post('/transactions/{transaction}/submit', [TransactionController::class, 'submit'])->name('transactions.submit');
    Route::post('/transactions/{transaction}/verify', [TransactionController::class, 'verify'])->name('transactions.verify');
    Route::post('/transactions/{transaction}/approve', [TransactionController::class, 'approve'])->name('transactions.approve');
    Route::post('/transactions/{transaction}/post', [TransactionController::class, 'post'])->name('transactions.post');
    Route::post('/transactions/{transaction}/reject', [TransactionController::class, 'reject'])->name('transactions.reject');
    Route::post('/transactions/{transaction}/adjust', [TransactionController::class, 'adjust'])->name('transactions.adjust');

    Route::middleware('permission:RECONCILIATION_VIEW')->group(function () {
        Route::get('/reconciliation', [ReconciliationController::class, 'index'])->name('reconciliation.index');
        Route::get('/reconciliation/{statement}', [ReconciliationController::class, 'show'])->name('reconciliation.show');
    });
    Route::middleware('permission:RECONCILIATION_CREATE')->group(function () {
        Route::post('/reconciliation/import', [ReconciliationController::class, 'import'])->name('reconciliation.import');
        Route::post('/reconciliation/lines/{line}/match', [ReconciliationController::class, 'match'])->name('reconciliation.match');
        Route::post('/reconciliation/lines/{line}/resolve', [ReconciliationController::class, 'resolve'])->name('reconciliation.resolve');
    });

    Route::middleware('permission:BUDGET_VIEW')->group(function () {
        Route::get('/budgets', [BudgetController::class, 'index'])->name('budgets.index');
        Route::get('/budgets/{budget}', [BudgetController::class, 'show'])->name('budgets.show');
    });
    Route::post('/budgets', [BudgetController::class, 'store'])->middleware('permission:BUDGET_CREATE')->name('budgets.store');
    Route::post('/budgets/{budget}/approve', [BudgetController::class, 'approve'])->name('budgets.approve');

    Route::middleware('permission:REPORT_VIEW')->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    });
    Route::middleware('permission:REPORT_EXPORT')->group(function () {
        Route::post('/reports', [ReportController::class, 'store'])->name('reports.store');
        Route::get('/reports/runs/{run}/download', [ReportController::class, 'download'])->name('reports.download');
    });

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');

    Route::get('/audit', [AuditLogController::class, 'index'])->name('audit.index');
    Route::get('/periods', [PeriodController::class, 'index'])->name('periods.index');
    Route::post('/periods', [PeriodController::class, 'store'])->name('periods.store');
    Route::post('/periods/{period}/close', [PeriodController::class, 'close'])->name('periods.close');

    Route::middleware('permission:SYSTEM_SETTINGS_MANAGE')->group(function () {
        Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
        Route::put('/accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
        Route::get('/admin/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('/admin/settings', [SettingController::class, 'update'])->name('settings.update');
    });

    Route::middleware('permission:USER_MANAGE')->group(function () {
        Route::get('/admin/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/admin/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/admin/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::get('/admin/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::put('/admin/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    });

    Route::middleware('permission:UNIT_MANAGE')->group(function () {
        Route::get('/admin/units', [UnitController::class, 'index'])->name('units.index');
        Route::post('/admin/units', [UnitController::class, 'store'])->name('units.store');
        Route::put('/admin/units/{unit}', [UnitController::class, 'update'])->name('units.update');
    });

    Route::prefix('api')->group(function () {
        Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('api.transactions.show');
    });
});
