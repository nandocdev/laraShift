<?php

declare(strict_types=1);

namespace App\Modules\Central\Operations\Infrastructure\Console;

use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Operations\Application\Actions\DetectCriticalIncidents;
use App\Modules\Central\Operations\Infrastructure\Notifications\OpsIncidentNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Hourly platform watch (RF8.2): mails global admins about NEW critical
 * incidents. Each incident alerts at most once per 24h (dedupe cache)
 * so a persistent incident does not spam on every run.
 */
class AlertCriticalIncidentsCommand extends Command
{
    protected $signature = 'operations:alert-incidents';

    protected $description = 'Mail global admins about new critical platform incidents';

    public function handle(DetectCriticalIncidents $detect): int
    {
        $fresh = collect($detect->execute())
            ->reject(fn (array $incident) => Cache::has($this->dedupeKey($incident['id'])));

        if ($fresh->isEmpty()) {
            $this->info('No new critical incidents.');

            return self::SUCCESS;
        }

        $admins = CentralUser::where('is_global_admin', true)->get();

        if ($admins->isEmpty()) {
            $this->warn('Critical incidents detected but no global admins to notify.');

            return self::SUCCESS;
        }

        Notification::send($admins, new OpsIncidentNotification($fresh->all()));

        foreach ($fresh as $incident) {
            Cache::put($this->dedupeKey($incident['id']), true, now()->addDay());
        }

        $this->info("Alerted {$admins->count()} admin(s) about {$fresh->count()} incident(s).");

        return self::SUCCESS;
    }

    private function dedupeKey(string $incidentId): string
    {
        return "ops:incident-alert:{$incidentId}";
    }
}
