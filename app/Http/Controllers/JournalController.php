<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JournalController extends Controller
{
    public function index(Request $request): Response
    {
        $entries = JournalEntry::query()
            ->with(['unit:id,code', 'transaction:id,number', 'lines'])
            ->inContext($request->user())
            ->when($request->input('q'), function ($query, $term) {
                $query->where(function ($inner) use ($term) {
                    $inner->where('description', 'like', '%'.$term.'%')
                        ->orWhereHas('transaction', fn ($nested) => $nested->where('number', 'like', '%'.$term.'%'));
                });
            })
            ->latest('entry_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (JournalEntry $entry) => [
                'id' => $entry->id,
                'date' => $entry->entry_date?->toDateString(),
                'number' => $entry->transaction?->number,
                'transaction_id' => $entry->transaction_id,
                'unit' => $entry->unit?->code,
                'description' => $entry->description,
                'debit' => (int) $entry->lines->sum('debit'),
                'credit' => (int) $entry->lines->sum('credit'),
            ]);

        return Inertia::render('Journal/Index', [
            'entries' => $entries,
            'filters' => $request->only(['q']),
        ]);
    }

    public function show(Request $request, JournalEntry $journal): Response
    {
        abort_unless($request->user()->canAccessUnit((int) $journal->unit_id), 403);
        $journal->load(['lines.account', 'unit', 'transaction', 'poster']);

        return Inertia::render('Journal/Show', [
            'entry' => [
                'id' => $journal->id,
                'date' => $journal->entry_date?->toDateString(),
                'description' => $journal->description,
                'unit' => $journal->unit?->name,
                'number' => $journal->transaction?->number,
                'transaction_id' => $journal->transaction_id,
                'poster' => $journal->poster?->name,
                'debit' => (int) $journal->lines->sum('debit'),
                'credit' => (int) $journal->lines->sum('credit'),
                'lines' => $journal->lines->map(fn ($line) => [
                    'account' => $line->account?->code.' '.$line->account?->name,
                    'memo' => $line->memo,
                    'debit' => (int) $line->debit,
                    'credit' => (int) $line->credit,
                ]),
            ],
        ]);
    }
}
