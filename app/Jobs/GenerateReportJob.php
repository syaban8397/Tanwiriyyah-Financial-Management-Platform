<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ReportRun;
use App\Services\DomainEvents;
use App\Services\ReportBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateReportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $reportRunId) {}

    public function handle(ReportBuilder $builder, DomainEvents $events): void
    {
        $run = ReportRun::query()->find($this->reportRunId);

        if (! $run) {
            return;
        }

        $run->update(['status' => 'generating']);

        try {
            $path = $builder->export($run->user, $run->report, $run->filters ?? [], $run->format);
            $run->update(['status' => 'ready', 'path' => $path, 'error' => null]);
            $events->publish('report.ready', $run->user?->unit_id, [
                'id' => $run->id,
                'report' => $run->report,
            ]);
        } catch (\Throwable $exception) {
            report($exception);
            $run->update([
                'status' => 'failed',
                'error' => 'Laporan tidak dapat dibuat. Periksa filter lalu coba lagi.',
            ]);
        }
    }
}
