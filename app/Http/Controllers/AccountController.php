<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Models\Account;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Accounts/Index', [
            'accounts' => Account::query()->with('parent:id,code')->orderBy('code')->get()->map(fn (Account $account) => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type->value,
                'type_label' => $account->type->label(),
                'parent' => $account->parent?->code,
                'is_postable' => $account->is_postable,
                'is_system' => $account->is_system,
                'is_active' => $account->is_active,
            ]),
            'parents' => Account::query()->where('is_postable', false)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('SYSTEM_SETTINGS_MANAGE'), 403);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:16', 'unique:accounts,code'],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'parent_id' => ['nullable', 'exists:accounts,id'],
        ]);
        $type = AccountType::from($data['type']);
        $account = Account::query()->create([
            ...$data,
            'type' => $type,
            'normal_balance' => $type->normalBalance(),
            'is_postable' => true,
            'is_system' => false,
            'is_active' => true,
        ]);
        $audit->log($request->user(), 'account.created', 'account', $account->id, null, null, ['code' => $account->code]);

        return back()->with('success', 'Akun '.$account->code.' ditambahkan.');
    }

    public function update(Request $request, Account $account, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('SYSTEM_SETTINGS_MANAGE'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($account->is_system && ! $data['is_active'] && in_array($account->code, ['1100', '1200'], true)) {
            return back()->with('error', 'Akun kas dan bank sistem tidak dapat dinonaktifkan.');
        }

        $old = ['name' => $account->name, 'is_active' => $account->is_active];
        $account->update($data);
        $audit->log($request->user(), 'account.updated', 'account', $account->id, null, $old, $data);

        return back()->with('success', 'Akun diperbarui.');
    }
}
