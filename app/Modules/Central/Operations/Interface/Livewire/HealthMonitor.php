<?php

declare(strict_types=1);

namespace App\Modules\Central\Operations\Interface\Livewire;

use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Central\Operations\Infrastructure\Horizon\HorizonQueueResolver;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.central')]
class HealthMonitor extends Component
{
    /**
     * Acknowledge es efímero (solo oculta en esta vista, no persiste).
     * Sin tabla de incidentes no hay dónde guardar estado: YAGNI.
     *
     * @var list<string>
     */
    public array $acknowledged = [];

    public function refresh(): void
    {
        // Sin cuerpo: la acción fuerza re-render con datos frescos.
    }

    public function acknowledge(string $id): void
    {
        if (! in_array($id, $this->acknowledged, true)) {
            $this->acknowledged[] = $id;
        }
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function platform(): array
    {
        $dbOk = $this->checkDatabase();
        $redisOk = $this->checkRedis();
        $queueSize = $this->queueSize();
        $failedJobs = $this->failedJobsCount();
        $queueOk = $queueSize <= 1000 && $failedJobs <= 100;

        return [
            'status' => $dbOk && $redisOk && $queueOk ? 'healthy' : 'degraded',
            'services' => [
                ['name' => 'API', 'ok' => $dbOk],
                ['name' => 'Database', 'ok' => $dbOk],
                ['name' => 'Queue', 'ok' => $queueOk],
                ['name' => 'Redis', 'ok' => $redisOk],
            ],
            'queue_size' => $queueSize,
            'failed_jobs' => $failedJobs,
        ];
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function tenantHealth(): array
    {
        try {
            $counts = Tenant::selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->all();
        } catch (\Throwable) {
            return ['healthy' => 0, 'warning' => 0, 'critical' => 0];
        }

        $healthy = (int) ($counts['active'] ?? 0);
        $warning = (int) ($counts['provisioning'] ?? 0)
            + (int) ($counts['pending_payment'] ?? 0)
            + (int) ($counts['suspended'] ?? 0);
        $critical = (int) ($counts['past_due'] ?? 0)
            + (int) ($counts['quarantine'] ?? 0)
            + (int) ($counts['archived'] ?? 0)
            + (int) ($counts['failed'] ?? 0)
            + (int) ($counts['expired'] ?? 0);

        return ['healthy' => $healthy, 'warning' => $warning, 'critical' => $critical];
    }

    /**
     * Incidentes derivados del estado real. Sin fuentes nuevas.
     *
     * @return array<int, array{id: string, severity: string, title: string, detail: string, link: string|null}>
     */
    #[Computed]
    public function incidents(): array
    {
        $incidents = [];

        $queueSize = $this->queueSize();
        if ($queueSize > 1000) {
            $incidents[] = [
                'id' => 'queue-latency',
                'severity' => 'warning',
                'title' => 'Queue latency',
                'detail' => number_format($queueSize).' jobs en cola (umbral 1,000).',
                'link' => null,
            ];
        }

        $failedJobs = $this->failedJobsCount();
        if ($failedJobs > 0) {
            $incidents[] = [
                'id' => 'failed-jobs',
                'severity' => $failedJobs > 100 ? 'critical' : 'warning',
                'title' => 'Failed jobs',
                'detail' => number_format($failedJobs).' jobs fallidos pendientes de revisión.',
                'link' => null,
            ];
        }

        $pastDue = $this->countWhere(Subscription::class, 'past_due');
        if ($pastDue > 0) {
            $incidents[] = [
                'id' => 'billing-past-due',
                'severity' => 'warning',
                'title' => 'Payment webhook failures',
                'detail' => $pastDue === 1 ? '1 suscripción en mora.' : "{$pastDue} suscripciones en mora.",
                'link' => route('central.billing.subscriptions'),
            ];
        }

        $quarantined = $this->countWhere(Tenant::class, 'quarantine');
        if ($quarantined > 0) {
            $incidents[] = [
                'id' => 'tenants-quarantine',
                'severity' => 'critical',
                'title' => 'Tenants en cuarentena',
                'detail' => $quarantined === 1 ? '1 tenant aislado por antifraude.' : "{$quarantined} tenants aislados por antifraude.",
                'link' => route('central.provisioning.index'),
            ];
        }

        $failed = $this->countWhere(Tenant::class, 'failed');
        if ($failed > 0) {
            $incidents[] = [
                'id' => 'provisioning-failed',
                'severity' => 'critical',
                'title' => 'Provisioning fallido',
                'detail' => $failed === 1 ? '1 tenant con provisioning fallido.' : "{$failed} tenants con provisioning fallido.",
                'link' => route('central.provisioning.index'),
            ];
        }

        return array_values(array_filter(
            $incidents,
            fn (array $i) => ! in_array($i['id'], $this->acknowledged, true)
        ));
    }

    public function render(): View
    {
        return view('operations::pages.health-monitor');
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkRedis(): bool
    {
        try {
            if (! class_exists('Redis') && config('database.redis.client') === 'phpredis') {
                return false;
            }

            Redis::connection()->ping();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function queueSize(): int
    {
        try {
            return collect(HorizonQueueResolver::resolve())
                ->sum(fn (string $queue) => Queue::size($queue));
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

    private function countWhere(string $model, string $status): int
    {
        try {
            return $model::where('status', $status)->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
