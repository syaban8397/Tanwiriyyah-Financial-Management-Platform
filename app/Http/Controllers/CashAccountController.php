<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CashAccount;
use App\Services\AuditLogger;
use App\Services\BalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashAccountController extends Controller
{
    public function index(Request $request, BalanceService $balances): Response
    {
        $accounts = CashAccount::query()->with('unit:id,code,name')->visibleTo($request->user())->orderBy('name')->get();
        $computed = $balances->cashBalances($accounts);

        return Inertia::render('Cash/Index', [
            'accounts' => $accounts->map(fn (CashAccount $account) => [
                'id' => $account->id,
                'name' => $account->name,
                'unit' => $account->unit?->code,
                'unit_name' => $account->unit?->name,
                'opening_balance' => (int) $account->opening_balance,
                'balance' => $computed[$account->id] ?? 0,
                'movement' => ($computed[$account->id] ?? 0) - (int) $account->opening_balance,
                'is_active' => $account->is_active,
            ]),
            'can_create' => $request->user()->hasPermission('UNIT_MANAGE') || $request->user()->unit_id !== null,
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasAnyPermission(['FINANCE_CREATE', 'UNIT_MANAGE']), 403);
        $data = $request->validate([
            'unit_id' => ['required', 'exists:units,id'],
            'name' => ['required', 'string', 'max:120'],
            'opening_balance' => ['required', 'integer', 'min:0'],
        ]);
        abort_unless($request->user()->canAccessUnit((int) $data['unit_id']), 403);
        $coa = Account::query()->where('code', '1100')->firstOrFail();
        $account = CashAccount::query()->create([
            ...$data,
            'account_id' => $coa->id,
            'is_active' => true,
        ]);
        $audit->log($request->user(), 'cash_account.created', 'cash_account', $account->id, (int) $account->unit_id, null, ['name' => $account->name]);

        return back()->with('success', 'Akun kas ditambahkan.');
    }
}
