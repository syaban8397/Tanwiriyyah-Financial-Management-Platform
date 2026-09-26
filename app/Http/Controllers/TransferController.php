<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\TransactionService;
use App\Support\FormCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransferController extends Controller
{
    public function create(Request $request, FormCatalog $catalog): Response
    {
        abort_unless($request->user()->hasPermission('FINANCE_CREATE'), 403);

        return Inertia::render('Transfers/Create', [
            'catalog' => $catalog->for($request->user()),
        ]);
    }

    public function store(Request $request, TransactionService $transactions): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('FINANCE_CREATE'), 403);
        $data = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
            'unit_id' => ['required', 'exists:units,id'],
            'transacted_on' => ['required', 'date'],
            'posting_date' => ['required', 'date'],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:100'],
            'source_type' => ['required', 'in:cash,bank'],
            'source_id' => ['required', 'integer'],
            'destination_type' => ['required', 'in:cash,bank'],
            'destination_id' => ['required', 'integer'],
        ]);

        $payload = [
            'idempotency_key' => $data['idempotency_key'],
            'unit_id' => $data['unit_id'],
            'type' => 'transfer',
            'transacted_on' => $data['transacted_on'],
            'posting_date' => $data['posting_date'],
            'payment_method' => $data['source_type'],
            'amount' => $data['amount'],
            'description' => $data['description'],
            'reference' => $data['reference'] ?? null,
            'cash_account_id' => $data['source_type'] === 'cash' ? $data['source_id'] : null,
            'bank_account_id' => $data['source_type'] === 'bank' ? $data['source_id'] : null,
            'destination_cash_account_id' => $data['destination_type'] === 'cash' ? $data['destination_id'] : null,
            'destination_bank_account_id' => $data['destination_type'] === 'bank' ? $data['destination_id'] : null,
        ];

        $transaction = $transactions->create($request->user(), $payload);

        return redirect()->route('transactions.show', $transaction)->with('success', 'Transfer disimpan sebagai draf. Ajukan untuk dipindahkan di buku.');
    }
}
