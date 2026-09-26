<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\Transaction;
use App\Services\ReconciliationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReconciliationController extends Controller
{
    public function index(Request $request): Response
    {
        $accounts = BankAccount::query()->with('unit:id,code')->visibleTo($request->user())->where('is_active', true)->get();
        $statements = BankStatement::query()
            ->with('bankAccount.unit')
            ->whereIn('bank_account_id', $accounts->pluck('id'))
            ->latest('statement_date')
            ->paginate(12)
            ->through(fn (BankStatement $statement) => [
                'id' => $statement->id,
                'date' => $statement->statement_date?->toDateString(),
                'bank' => $statement->bankAccount?->bank_name,
                'unit' => $statement->bankAccount?->unit?->code,
                'status' => $statement->status,
                'closing_balance' => (int) $statement->closing_balance,
            ]);

        return Inertia::render('Reconciliation/Index', [
            'statements' => $statements,
            'accounts' => $accounts->map(fn (BankAccount $account) => [
                'id' => $account->id,
                'label' => $account->unit?->code.' · '.$account->bank_name.' '.$account->masked_number,
            ]),
            'can_import' => $request->user()->hasPermission('RECONCILIATION_CREATE'),
        ]);
    }

    public function show(Request $request, BankStatement $statement, ReconciliationService $reconciliation): Response
    {
        $statement->load('bankAccount');
        abort_unless($request->user()->canAccessUnit((int) $statement->bankAccount->unit_id), 403);
        abort_unless($request->user()->hasPermission('RECONCILIATION_VIEW'), 403);

        $statement->load('lines.transaction');
        $candidates = Transaction::query()
            ->where('unit_id', $statement->bankAccount->unit_id)
            ->where('status', 'posted')
            ->where(function ($query) use ($statement) {
                $query->where('bank_account_id', $statement->bank_account_id)
                    ->orWhere('destination_bank_account_id', $statement->bank_account_id);
            })
            ->latest('id')
            ->limit(40)
            ->get(['id', 'number', 'amount', 'description', 'type', 'transacted_on']);

        return Inertia::render('Reconciliation/Show', [
            'statement' => [
                'id' => $statement->id,
                'date' => $statement->statement_date?->toDateString(),
                'status' => $statement->status,
                'bank' => $statement->bankAccount->bank_name.' '.$statement->bankAccount->masked_number,
                'opening_balance' => (int) $statement->opening_balance,
                'closing_balance' => (int) $statement->closing_balance,
                'lines' => $statement->lines->map(fn (BankStatementLine $line) => [
                    'id' => $line->id,
                    'date' => $line->line_date?->toDateString(),
                    'description' => $line->description,
                    'amount' => (int) $line->amount,
                    'reference' => $line->reference,
                    'match_status' => $line->match_status,
                    'transaction' => $line->transaction ? ['id' => $line->transaction->id, 'number' => $line->transaction->number] : null,
                ]),
            ],
            'snapshot' => $reconciliation->snapshot($statement),
            'candidates' => $candidates->map(fn (Transaction $transaction) => [
                'id' => $transaction->id,
                'number' => $transaction->number,
                'amount' => (int) $transaction->amount,
                'description' => $transaction->description,
                'type' => $transaction->type->value,
                'date' => $transaction->transacted_on?->toDateString(),
            ]),
            'can_match' => $request->user()->hasPermission('RECONCILIATION_CREATE'),
        ]);
    }

    public function import(Request $request, ReconciliationService $reconciliation): RedirectResponse
    {
        $data = $request->validate([
            'bank_account_id' => ['required', 'exists:bank_accounts,id'],
            'statement_date' => ['required', 'date'],
            'opening_balance' => ['required', 'integer'],
            'closing_balance' => ['required', 'integer'],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);
        $account = BankAccount::query()->findOrFail($data['bank_account_id']);
        $statement = $reconciliation->import($request->user(), $account, $request->file('file'), $data);

        return redirect()->route('reconciliation.show', $statement)->with('success', 'Rekening koran diimpor.');
    }

    public function match(Request $request, BankStatementLine $line, ReconciliationService $reconciliation): RedirectResponse
    {
        $data = $request->validate(['transaction_id' => ['required', 'exists:transactions,id']]);
        $reconciliation->match($request->user(), $line, Transaction::query()->findOrFail($data['transaction_id']));

        return back()->with('success', 'Baris dicocokkan.');
    }

    public function resolve(Request $request, BankStatementLine $line, ReconciliationService $reconciliation): RedirectResponse
    {
        $data = $request->validate(['comment' => ['required', 'string', 'min:3', 'max:300']]);
        $reconciliation->resolve($request->user(), $line, $data['comment']);

        return back()->with('success', 'Baris diselesaikan tanpa pencocokan.');
    }
}
