<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Support\TransactionView;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalQueueController extends Controller
{
    public function index(Request $request, TransactionView $view): Response
    {
        $items = Transaction::query()
            ->with(['unit:id,code', 'category:id,name', 'creator:id,name'])
            ->visibleTo($request->user())
            ->whereIn('status', ['submitted', 'under_review', 'approved'])
            ->latest('submitted_at')
            ->paginate(20)
            ->through(fn (Transaction $transaction) => [
                ...$view->listItem($transaction),
                'creator' => $transaction->creator?->name,
            ]);

        return Inertia::render('Approvals/Index', ['transactions' => $items]);
    }
}
