<?php

use App\Modules\Central\Analytics\Jobs\RefreshPlatformMetricsJob;
use App\Modules\Shared\Events\Dlq\RetryDeadLetterJob;
use App\Modules\Shared\Events\Outbox\PublishOutboxEventsJob;
use App\Modules\Shared\Infrastructure\Jobs\ReconcileResourcesJob;
use App\Modules\Shared\Infrastructure\Jobs\SnapshotQuotasJob;
use App\Modules\Tenant\Audit\Jobs\PurgeExpiredAuditLogsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new SnapshotQuotasJob)->daily();
Schedule::job(new ReconcileResourcesJob)->daily();

Schedule::command('billing:reconcile')->dailyAt('03:00');

Schedule::job(new PublishOutboxEventsJob)
    ->everyMinute()
    ->withoutOverlapping();

Schedule::job(new RetryDeadLetterJob)
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::job(new PurgeExpiredAuditLogsJob)
    ->dailyAt('02:00')
    ->withoutOverlapping();

Schedule::job(new RefreshPlatformMetricsJob)
    ->hourly()
    ->withoutOverlapping();
