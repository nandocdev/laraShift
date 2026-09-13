<?php

declare(strict_types=1);

namespace App\Modules\Central\Auth\Livewire;

use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Platform\Observability\Audit\Activity;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.central')]
class Dashboard extends Component
{
    /**
     * @return array<string, array<string, mixed>>
     */
    #[Computed]
    public function stats(): array
    {
        $tenantCount = Tenant::count();
        $tenantsThisMonth = Tenant::where('created_at', '>=', now()->startOfMonth())->count();

        $activeCount = Tenant::where('status', 'active')->count();
        $suspendedCount = Tenant::where('status', 'suspended')->count();
        $quarantinedCount = Tenant::where('status', 'quarantine')->count();

        $userCount = $this->tableCount('users');
        $usersThisMonth = $this->tableCountSince('users', now()->startOfMonth());

        $activePercentage = $tenantCount > 0
            ? round(($activeCount / $tenantCount) * 100, 1)
            : 0.0;

        $alerts = $this->alerts();

        $critical = count(array_filter($alerts, fn (array $a) => ($a['type'] ?? '') === 'critical'));

        return [
            'organizations' => [
                'total' => $tenantCount,
                'growth' => "+{$tenantsThisMonth} este mes",
            ],
            'users' => [
                'total' => $userCount,
                'growth' => "+{$usersThisMonth} este mes",
            ],
            'active' => [
                'total' => $activeCount,
                'percentage' => "{$activePercentage}%",
            ],
            'breakdown' => [
                'total' => $tenantCount,
                'active' => $activeCount,
                'suspended' => $suspendedCount,
                'quarantined' => $quarantinedCount,
            ],
            'alerts' => [
                'total' => count($alerts),
                'critical' => $critical,
                'critical_label' => $critical === 1 ? '1 crítica' : "{$critical} críticas",
            ],
        ];
    }

    /**
     * MRR por moneda: suma de price_monthly de los planes con
     * suscripciones no canceladas. Mezclar monedas sería falso,
     * así que se agrupa y se destaca la moneda con más suscripciones.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function revenue(): array
    {
        try {
            $rows = DB::table('subscriptions as s')
                ->join('plans as p', function ($join): void {
                    $join->on('p.id', '=', 's.plan_id')->orOn('p.slug', '=', 's.plan_id');
                })
                ->whereNotIn('s.status', ['canceled'])
                ->selectRaw('p.currency as currency, sum(p.price_monthly) as total, count(*) as subs')
                ->groupBy('p.currency')
                ->get();
        } catch (\Throwable) {
            return $this->emptyMoney('mrr');
        }

        return $this->primaryMoney($rows, 'mrr');
    }

    /**
     * Churn 30d: canceladas / (base activa + canceladas). Ventana y
     * fórmula fijas para que el número sea comparable mes a mes.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function churn(): array
    {
        try {
            $since = now()->subDays(30);
            $canceled = DB::table('subscriptions')
                ->where('status', 'canceled')
                ->where('canceled_at', '>=', $since)
                ->count();
            $base = DB::table('subscriptions')->whereNotIn('status', ['canceled'])->count() + $canceled;
        } catch (\Throwable) {
            return ['canceled_30d' => 0, 'rate' => 0.0, 'rate_label' => '0%'];
        }

        $rate = $base > 0 ? round(($canceled / $base) * 100, 1) : 0.0;

        return [
            'canceled_30d' => $canceled,
            'rate' => $rate,
            'rate_label' => "{$rate}%",
        ];
    }

    /**
     * Transacciones: aprobadas últimos 30d por moneda + pendientes.
     * Reservas no aplica: este repo no tiene módulo de producto.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function transactions(): array
    {
        try {
            $rows = DB::table('payments')
                ->where('status', 'approved')
                ->where('created_at', '>=', now()->subDays(30))
                ->selectRaw('currency, sum(amount_cents) as total, count(*) as subs')
                ->groupBy('currency')
                ->get();
            $pending = (int) DB::table('payments')->where('status', 'pending')->count();
        } catch (\Throwable) {
            return array_merge($this->emptyMoney('volume_30d'), ['pending' => 0, 'count_30d' => 0]);
        }

        return array_merge($this->primaryMoney($rows, 'volume_30d'), [
            'pending' => $pending,
            'count_30d' => (int) $rows->sum('subs'),
        ]);
    }

    /**
     * Nuevos tenants y suscripciones por día, últimos 7 días.
     * SQLite + PG portable. Sin datos retorna ceros.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function activityChart(): array
    {
        $days = [];
        $tenants = $this->tenantsPerDay(7);
        $subs = $this->subscriptionsPerDay(7);

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $key = $date->format('Y-m-d');
            $value = (int) ($tenants[$key] ?? 0);
            $subValue = (int) ($subs[$key] ?? 0);
            $days[] = [
                'key' => $date->translatedFormat('D'),
                'label' => $date->translatedFormat('l'),
                'value' => $value,
                'users' => $value,
                'subs' => $subValue,
            ];
        }

        $max = max(array_merge(array_column($days, 'value'), array_column($days, 'subs')));

        return [
            'days' => $days,
            'max' => max($max, 1),
            'min' => 0,
            'subtitle' => 'Nuevos tenants · Últimos 7 días',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function systemHealth(): array
    {
        $dbOk = true;
        try {
            DB::connection()->getPdo();
        } catch (\Throwable) {
            $dbOk = false;
        }

        $redisOk = true;
        try {
            if (! class_exists('Redis') && config('database.redis.client') === 'phpredis') {
                $redisOk = false;
            } else {
                Redis::connection()->ping();
            }
        } catch (\Throwable) {
            $redisOk = false;
        }

        $queueSize = $this->queueSize();
        $queueOk = $queueSize <= 1000;

        $pastDue = $this->safeCount(Subscription::class, 'past_due');

        $billingOk = $pastDue === 0;

        $allOk = $dbOk && $redisOk && $queueOk && $billingOk;

        return [
            'status' => $allOk ? 'healthy' : 'degraded',
            'services' => [
                ['name' => 'API', 'status' => $dbOk ? 'operational' : 'degraded', 'status_label' => $dbOk ? 'Operativo' : 'Degradado'],
                ['name' => 'Base de datos', 'status' => $dbOk ? 'operational' : 'degraded', 'status_label' => $dbOk ? 'Operativo' : 'Degradado'],
                ['name' => 'Queue', 'status' => $queueOk ? 'operational' : 'degraded', 'status_label' => $queueOk ? 'Operativo' : 'Degradado'],
                ['name' => 'Billing', 'status' => $billingOk ? 'operational' : 'degraded', 'status_label' => $billingOk ? 'Operativo' : "{$pastDue} en mora"],
            ],
            'metrics' => [
                'queue_size' => $queueSize,
                'past_due' => $pastDue,
            ],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    #[Computed]
    public function recentActivities(): array
    {
        $activities = Activity::latest()->take(6)->get();

        return $activities->map(fn ($act) => [
            'title' => str($act->description)->replace('_', ' ')->title()->toString(),
            'detail' => ($act->causer?->name ?? 'Sistema').' · '.$act->created_at->diffForHumans(),
            'time' => $act->created_at->diffForHumans(),
        ])->toArray();
    }

    /**
     * Alertas derivadas de estado real: quarantine/suspend/past_due/cola.
     * Vacío cuando todo está sano — nunca alertas inventadas.
     *
     * @return array<int, array<string, string>>
     */
    #[Computed]
    public function alerts(): array
    {
        $alerts = [];

        $quarantined = Tenant::where('status', 'quarantine')->count();
        if ($quarantined > 0) {
            $alerts[] = [
                'type' => 'critical',
                'title' => $quarantined === 1 ? '1 tenant en cuarentena' : "{$quarantined} tenants en cuarentena",
                'time' => 'ahora',
            ];
        }

        $suspended = Tenant::where('status', 'suspended')->count();
        if ($suspended > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => $suspended === 1 ? '1 tenant suspendido' : "{$suspended} tenants suspendidos",
                'time' => 'ahora',
            ];
        }

        $pastDue = $this->safeCount(Subscription::class, 'past_due');
        if ($pastDue > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => $pastDue === 1 ? '1 suscripción en mora' : "{$pastDue} suscripciones en mora",
                'time' => 'ahora',
            ];
        }

        $pendingPayments = $this->safeCount(Payment::class, 'pending');
        if ($pendingPayments > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => $pendingPayments === 1 ? '1 pago pendiente' : "{$pendingPayments} pagos pendientes",
                'time' => 'ahora',
            ];
        }

        $failedJobs = $this->failedJobsCount();
        if ($failedJobs > 0) {
            $alerts[] = [
                'type' => $failedJobs > 10 ? 'critical' : 'warning',
                'title' => $failedJobs === 1 ? '1 job fallido en cola' : "{$failedJobs} jobs fallidos en cola",
                'time' => 'ahora',
            ];
        }

        return $alerts;
    }

    /**
     * Churn-risk tenants with explainable reasons (RF10.1, heuristics v1).
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function churnRisks(): array
    {
        try {
            return app(TenantRiskInsights::class)->churnRisks();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Upgrade candidates by quota saturation (RF10.2 + RF10.3).
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function upgradeCandidates(): array
    {
        try {
            return app(TenantRiskInsights::class)->upgradeCandidates();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function organizations(): array
    {
        $dbTenants = Tenant::with('domains')->latest()->take(5)->get();

        if ($dbTenants->isEmpty()) {
            return [];
        }

        $userCounts = collect();
        try {
            $userCounts = DB::table('users')
                ->whereIn('tenant_id', $dbTenants->pluck('id'))
                ->selectRaw('tenant_id, count(*) as total')
                ->groupBy('tenant_id')
                ->pluck('total', 'tenant_id');
        } catch (\Throwable) {
            // Tabla users ausente en algún contexto: conteos en 0.
        }

        $centralHost = parse_url((string) config('app.url'), PHP_URL_HOST) ?? 'localhost';

        return $dbTenants->map(function ($tenant) use ($userCounts, $centralHost) {
            return [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'domain' => $tenant->domains->first()?->domain ?? ($tenant->slug.'.'.$centralHost),
                'users_count' => (int) ($userCounts[$tenant->id] ?? 0),
                'status' => $tenant->status,
                'status_label' => $this->tenantStatusLabel($tenant->status),
            ];
        })->toArray();
    }

    public function render(): View
    {
        return view('central-auth::pages.dashboard');
    }

    private function tenantStatusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Activa',
            'suspended' => 'Suspendida',
            'quarantine' => 'Cuarentena',
            'past_due' => 'En mora',
            'pending_payment' => 'Pago pendiente',
            'provisioning' => 'Aprovisionando',
            'archived' => 'Archivada',
            'failed' => 'Fallida',
            'expired' => 'Expirada',
            default => ucfirst($status),
        };
    }

    private function tableCount(string $table): int
    {
        try {
            return DB::table($table)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function tableCountSince(string $table, \DateTimeInterface $since): int
    {
        try {
            return DB::table($table)->where('created_at', '>=', $since)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Conteo por status tolerante a tablas ausentes (tests SQLite parciales).
     */
    private function safeCount(string $model, string $status): int
    {
        try {
            return $model::where('status', $status)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyMoney(string $totalKey): array
    {
        return [
            'currencies' => [],
            $totalKey => 0,
            'primary_label' => '—',
            'primary_subs' => 0,
        ];
    }

    /**
     * @param  Collection<int, mixed>  $rows  currency/total/subs
     * @return array<string, mixed>
     */
    private function primaryMoney($rows, string $totalKey): array
    {
        $currencies = [];
        foreach ($rows as $row) {
            $currencies[] = [
                'currency' => (string) $row->currency,
                'total' => (int) $row->total,
                'subs' => (int) $row->subs,
                'label' => number_format(((int) $row->total) / 100, 2).' '.(string) $row->currency,
            ];
        }

        usort($currencies, fn ($a, $b) => $b['subs'] <=> $a['subs']);
        $primary = $currencies[0] ?? null;

        return [
            'currencies' => $currencies,
            $totalKey => $primary['total'] ?? 0,
            'primary_label' => $primary['label'] ?? '—',
            'primary_subs' => $primary['subs'] ?? 0,
        ];
    }

    /**
     * @return array<string, int> Y-m-d => total
     */
    private function tenantsPerDay(int $days): array
    {
        try {
            return Tenant::where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
                ->selectRaw('date(created_at) as day, count(*) as total')
                ->groupBy('day')
                ->pluck('total', 'day')
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, int> Y-m-d => total
     */
    private function subscriptionsPerDay(int $days): array
    {
        try {
            return Subscription::where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
                ->selectRaw('date(created_at) as day, count(*) as total')
                ->groupBy('day')
                ->pluck('total', 'day')
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function queueSize(): int
    {
        try {
            return Queue::size();
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
