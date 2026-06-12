<?php

use App\Modules\Shared\Infrastructure\Jobs\ReconcileResourcesJob;
use App\Modules\Shared\Infrastructure\Jobs\SnapshotQuotasJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new SnapshotQuotasJob)->daily();
Schedule::job(new ReconcileResourcesJob)->daily();

Schedule::command('billing:reconcile')->dailyAt('03:00');
