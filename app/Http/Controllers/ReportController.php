<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\GenerateReportJob;
use App\Models\Account;
use App\Models\FinancialPeriod;
use App\Models\ReportRun;
use App\Models\Unit;
use App\Services\ReportBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, ReportBuilder $builder): Response
    {
        $report = $request->string('report')->toString();
        $preview = null;

        if ($report !== '') {
            $preview = $builder->build($request->user(), $report, [
                'period_id' => $request->input('period_id'),
                'unit_id' => $request->input('unit_id'),
                'account_id' => $request->input('account_id'),
            ]);
            $preview['rows'] = array_slice($preview['rows'], 0, 80);
        }

        return Inertia::render('Reports/Index', [
            'catalog' => $builder->catalog(),
            'periods' => FinancialPeriod::query()->orderByDesc('starts_on')->get(['id', 'name']),
            'units' => Unit::query()->when($request->user()->unit_id, fn ($query) => $query->whereKey($request->user()->unit_id))->orderBy('code')->get(['id', 'code', 'name']),
            'accounts' => Account::query()->where('is_postable', true)->orderBy('code')->get(['id', 'code', 'name']),
            'filters' => $request->only(['report', 'period_id', 'unit_id', 'account_id']),
            'preview' => $preview,
            'runs' => ReportRun::query()->where('user_id', $request->user()->id)->latest()->limit(8)->get(['id', 'report', 'status', 'format', 'created_at', 'error']),
            'can_export' => $request->user()->hasPermission('REPORT_EXPORT'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('REPORT_EXPORT'), 403);
        $data = $request->validate([
            'report' => ['required', 'string'],
            'format' => ['required', 'in:xlsx,pdf'],
            'period_id' => ['nullable', 'integer'],
            'unit_id' => ['nullable', 'integer'],
            'account_id' => ['nullable', 'integer'],
        ]);

        $run = ReportRun::query()->create([
            'user_id' => $request->user()->id,
            'report' => $data['report'],
            'filters' => [
                'period_id' => $data['period_id'] ?? null,
                'unit_id' => $data['unit_id'] ?? null,
                'account_id' => $data['account_id'] ?? null,
            ],
            'status' => 'preparing',
            'format' => $data['format'],
        ]);

        GenerateReportJob::dispatch($run->id);

        return back()->with('success', 'Laporan sedang disiapkan.');
    }

    public function download(Request $request, ReportRun $run): StreamedResponse
    {
        abort_unless((int) $run->user_id === (int) $request->user()->id, 403);
        abort_unless($run->status === 'ready' && $run->path, 404);

        return Storage::disk('local')->download($run->path);
    }
}
