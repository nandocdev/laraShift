<?php

use App\Modules\Platform\Tenancy\Application\Jobs\ReconcileResourcesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new ReconcileResourcesJob)->daily();

Schedule::command('billing:reconcile')->dailyAt('03:00');
Schedule::command('billing:process-recurring')->dailyAt('04:00');
Schedule::command('metering:aggregate')->dailyAt('00:05');
Schedule::command('provisioning:reconcile')->hourly();
