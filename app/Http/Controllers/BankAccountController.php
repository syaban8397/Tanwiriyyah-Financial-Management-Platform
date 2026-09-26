<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\BankAccount;
use App\Services\AuditLogger;
use App\Services\BalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BankAccountController extends Controller
{
    public function index(Request $request, BalanceService $balances): Response
    {
        $accounts = BankAccount::query()->with('unit:id,code,name')->visibleTo($request->user())->orderBy('bank_name')->get();
        $computed = $balances->bankBalances($accounts);

        return Inertia::render('Bank/Index', [
            'accounts' => $accounts->map(fn (BankAccount $account) => [
                'id' => $account->id,
                'bank_name' => $account->bank_name,
                'account_name' => $account->account_name,
                'masked' => $account->masked_number,
                'number' => $request->user()->canAccessUnit((int) $account->unit_id) ? $account->account_number : $account->masked_number,
                'unit' => $account->unit?->code,
                'unit_id' => $account->unit_id,
                'opening_balance' => (int) $account->opening_balance,
                'balance' => $computed[$account->id] ?? 0,
                'is_active' => $account->is_active,
            ]),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasAnyPermission(['FINANCE_CREATE', 'UNIT_MANAGE']), 403);
        $data = $request->validate([
            'unit_id' => ['required', 'exists:units,id'],
            'bank_name' => ['required', 'string', 'max:120'],
            'account_name' => ['required', 'string', 'max:120'],
            'account_number' => ['required', 'string', 'max:32'],
            'opening_balance' => ['required', 'integer', 'min:0'],
        ]);
        abort_unless($request->user()->canAccessUnit((int) $data['unit_id']), 403);
        $coa = Account::query()->where('code', '1200')->firstOrFail();
        $account = BankAccount::query()->create([...$data, 'account_id' => $coa->id, 'is_active' => true]);
        $audit->log($request->user(), 'bank_account.created', 'bank_account', $account->id, (int) $account->unit_id, null, [
            'bank_name' => $account->bank_name,
            'masked' => $account->masked_number,
        ]);

        return back()->with('success', 'Rekening bank ditambahkan.');
    }
}
