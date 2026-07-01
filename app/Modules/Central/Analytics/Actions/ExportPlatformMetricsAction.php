<?php

declare(strict_types=1);

namespace App\Modules\Central\Analytics\Actions;

use App\Modules\Central\Analytics\Exceptions\ExportFailedException;
use App\Modules\Central\Analytics\Models\PlatformMetric;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final readonly class ExportPlatformMetricsAction
{
    public function execute(string $dateFrom, string $dateTo, ?string $disk = null): string
    {
        $metrics = PlatformMetric::whereBetween('captured_at', [$dateFrom, $dateTo])
            ->orderBy('captured_at')
            ->get();

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Metric', 'Group', 'Period', 'Value', 'Captured At']);

        foreach ($metrics as $m) {
            fputcsv($handle, [
                $m->metric,
                $m->group ?? '-',
                $m->period,
                $m->value,
                $m->captured_at->toDateTimeString(),
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        $fileName = 'exports/analytics/platform_metrics_'.Str::random(8).'.csv';
        $targetDisk = $disk ?? config('analytics.export_disk', 'private');

        if (Storage::disk($targetDisk)->put($fileName, $content) === false) {
            throw ExportFailedException::storageFailure($targetDisk, $fileName);
        }

        return $fileName;
    }
}
