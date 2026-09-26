<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $documents = Document::query()
            ->with('transaction:id,number,unit_id,description')
            ->whereHas('transaction', fn ($query) => $query->visibleTo($request->user()))
            ->latest('id')
            ->paginate(20)
            ->through(fn (Document $document) => [
                'id' => $document->id,
                'name' => $document->original_name,
                'kind_label' => $document->kind->label(),
                'number' => $document->transaction?->number,
                'transaction_id' => $document->transaction_id,
                'description' => $document->transaction?->description,
                'size' => $document->size,
            ]);

        return Inertia::render('Documents/Index', ['documents' => $documents]);
    }

    public function show(Request $request, Document $document): StreamedResponse
    {
        $document->load('transaction');
        abort_unless($request->user()->canAccessUnit((int) $document->transaction->unit_id), 403);
        abort_unless($request->user()->hasPermission('FINANCE_VIEW'), 403);

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }
}
