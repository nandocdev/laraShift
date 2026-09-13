<?php

declare(strict_types=1);

namespace App\Modules\Central\Operations\Application\Actions;

use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Critical platform incidents (RF8.2). Single definition consumed by
 * the HealthMonitor UI and the operations:alert-incidents command.
 * Warnings (queue latency, past_due) stay view-only: paging on
 * warnings would spam admins every hour.
 *
 * @return array<int, array{id: string, severity: string, title: string, detail: string, link: string|null}>
 */
final readonly class DetectCriticalIncidents
{
    public function execute(): array
    {
        $incidents = [];

        $failed = $this->countTenants('failed');
        if ($failed > 0) {
            $incidents[] = [
                'id' => 'provisioning-failed',
                'severity' => 'critical',
                'title' => 'Provisioning fallido',
                'detail' => $failed === 1 ? '1 tenant con provisioning fallido.' : "{$failed} tenants con provisioning fallido.",
                'link' => route('central.provisioning.index'),
            ];
        }

        $quarantined = $this->countTenants('quarantine');
        if ($quarantined > 0) {
            $incidents[] = [
                'id' => 'tenants-quarantine',
                'severity' => 'critical',
                'title' => 'Tenants en cuarentena',
                'detail' => $quarantined === 1 ? '1 tenant aislado por antifraude.' : "{$quarantined} tenants aislados por antifraude.",
                'link' => route('central.provisioning.index'),
            ];
        }

        $failedJobs = $this->failedJobsCount();
        if ($failedJobs > 100) {
            $incidents[] = [
                'id' => 'failed-jobs',
                'severity' => 'critical',
                'title' => 'Failed jobs',
                'detail' => number_format($failedJobs).' jobs fallidos pendientes de revisión.',
                'link' => null,
            ];
        }

        return $incidents;
    }

    private function countTenants(string $status): int
    {
        try {
            return Tenant::where('status', $status)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function failedJobsCount(): int
    {
        try {
            return DB::table('failed_jobs')->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
