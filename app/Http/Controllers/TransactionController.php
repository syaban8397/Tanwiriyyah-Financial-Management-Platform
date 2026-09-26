<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Models\Transaction;
use App\Services\ApprovalEngine;
use App\Services\TransactionService;
use App\Support\FinanceRules;
use App\Support\FormCatalog;
use App\Support\TransactionView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function index(Request $request, TransactionView $view): Response
    {
        $filters = $request->only(['q', 'status', 'type', 'unit_id', 'from', 'to']);

        $transactions = Transaction::query()
            ->with(['unit:id,code', 'category:id,name'])
            ->visibleTo($request->user())
            ->when($filters['q'] ?? null, function ($query, $term) {
                $query->where(function ($inner) use ($term) {
                    $inner->where('number', 'like', '%'.$term.'%')
                        ->orWhere('description', 'like', '%'.$term.'%')
                        ->orWhere('reference', 'like', '%'.$term.'%');
                });
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['unit_id'] ?? null, function ($query, $unitId) use ($request) {
                if ($request->user()->canAccessUnit((int) $unitId)) {
                    $query->where('unit_id', $unitId);
                }
            })
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('transacted_on', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('transacted_on', '<=', $to))
            ->latest('transacted_on')
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Transaction $transaction) => $view->listItem($transaction));

        return Inertia::render('Transactions/Index', [
            'transactions' => $transactions,
            'filters' => $filters,
            'can_create' => $request->user()->hasPermission('FINANCE_CREATE'),
        ]);
    }

    public function create(Request $request, FormCatalog $catalog): Response
    {
        return Inertia::render('Transactions/Form', [
            'mode' => 'create',
            'transaction' => null,
            'catalog' => $catalog->for($request->user()),
        ]);
    }

    public function store(StoreTransactionRequest $request, TransactionService $transactions): RedirectResponse
    {
        $created = $transactions->create(
            $request->user(),
            $request->safe()->except(['documents']),
            $request->file('documents', []) ?? [],
        );

        return redirect()->route('transactions.show', $created)->with('success', 'Transaksi '.$created->number.' tersimpan sebagai draf.');
    }

    public function show(Request $request, Transaction $transaction, TransactionView $view): Response
    {
        $this->guard($request, $transaction);

        return Inertia::render('Transactions/Show', [
            'transaction' => $view->detail($transaction, $request->user()),
        ]);
    }

    public function edit(Request $request, Transaction $transaction, FormCatalog $catalog, TransactionView $view): Response
    {
        $this->guard($request, $transaction);

        if (! $transaction->status->isEditable()) {
            FinanceRules::fail('status', 'Transaksi yang sudah diposting tidak dapat diubah. Buat penyesuaian.');
        }

        return Inertia::render('Transactions/Form', [
            'mode' => 'edit',
            'transaction' => $view->detail($transaction, $request->user()),
            'catalog' => $catalog->for($request->user()),
        ]);
    }

    public function update(StoreTransactionRequest $request, Transaction $transaction, TransactionService $transactions): RedirectResponse
    {
        $this->guard($request, $transaction);
        $transactions->update($request->user(), $transaction, $request->validated());

        return redirect()->route('transactions.show', $transaction)->with('success', 'Draf diperbarui.');
    }

    public function destroy(Request $request, Transaction $transaction, TransactionService $transactions): RedirectResponse
    {
        $this->guard($request, $transaction);
        $transactions->destroy($request->user(), $transaction);

        return redirect()->route('transactions.index')->with('success', 'Draf dihapus.');
    }

    public function submit(Request $request, Transaction $transaction, ApprovalEngine $engine): RedirectResponse
    {
        $this->guard($request, $transaction);
        $engine->submit($transaction, $request->user());

        return back()->with('success', 'Transaksi diajukan.');
    }

    public function verify(Request $request, Transaction $transaction, ApprovalEngine $engine): RedirectResponse
    {
        $this->guard($request, $transaction);
        $engine->act($transaction, $request->user(), 'verify', $request->string('comment')->toString() ?: null);

        return back()->with('success', 'Transaksi ditinjau.');
    }

    public function approve(Request $request, Transaction $transaction, ApprovalEngine $engine): RedirectResponse
    {
        $this->guard($request, $transaction);
        $engine->act($transaction, $request->user(), 'approve', $request->string('comment')->toString() ?: null);

        return back()->with('success', 'Transaksi disetujui.');
    }

    public function post(Request $request, Transaction $transaction, ApprovalEngine $engine): RedirectResponse
    {
        $this->guard($request, $transaction);
        $engine->act($transaction, $request->user(), 'post');

        return back()->with('success', 'Transaksi diposting ke jurnal.');
    }

    public function reject(Request $request, Transaction $transaction, ApprovalEngine $engine): RedirectResponse
    {
        $this->guard($request, $transaction);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $engine->reject($transaction, $request->user(), $data['reason']);

        return back()->with('success', 'Transaksi ditolak.');
    }

    public function revise(Request $request, Transaction $transaction, ApprovalEngine $engine): RedirectResponse
    {
        $this->guard($request, $transaction);
        $engine->revise($transaction, $request->user());

        return back()->with('success', 'Transaksi kembali menjadi draf.');
    }

    public function adjust(Request $request, Transaction $transaction, TransactionService $transactions): RedirectResponse
    {
        $this->guard($request, $transaction);
        $data = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
            'amount' => ['required', 'integer', 'min:1'],
            'transacted_on' => ['required', 'date'],
            'posting_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:500'],
        ]);
        $adjustment = $transactions->adjust($request->user(), $transaction, $data);

        return redirect()->route('transactions.show', $adjustment)->with('success', 'Penyesuaian dibuat sebagai draf. Ajukan agar masuk jurnal.');
    }

    public function export(Request $request)
    {
        abort_unless($request->user()->hasPermission('REPORT_EXPORT'), 403);

        $rows = Transaction::query()
            ->with(['unit:id,code', 'category:id,name'])
            ->visibleTo($request->user())
            ->when($request->input('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->input('type'), fn ($query, $type) => $query->where('type', $type))
            ->when($request->input('unit_id'), function ($query, $unitId) use ($request) {
                if ($request->user()->canAccessUnit((int) $unitId)) {
                    $query->where('unit_id', $unitId);
                }
            })
            ->latest('id')
            ->limit(2000)
            ->get();

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Nomor', 'Tanggal', 'Unit', 'Jenis', 'Status', 'Uraian', 'Jumlah']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->number,
                    $row->transacted_on?->toDateString(),
                    $row->unit?->code,
                    $row->type->label(),
                    $row->status->label(),
                    $row->description,
                    $row->amount,
                ]);
            }

            fclose($handle);
        }, 'transaksi.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function guard(Request $request, Transaction $transaction): void
    {
        abort_unless($request->user()->hasPermission('FINANCE_VIEW') || $request->user()->hasPermission('FINANCE_CREATE'), 403);
        abort_unless($request->user()->canAccessUnit((int) $transaction->unit_id), 403);
    }
}
