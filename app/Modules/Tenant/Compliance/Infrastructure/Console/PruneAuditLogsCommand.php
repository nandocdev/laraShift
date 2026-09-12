<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Compliance\Infrastructure\Console;

use App\Modules\Platform\Observability\Audit\Activity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deletes audit rows older than the retention window (GDPR storage
 * limitation, RF7.1): tenant_audit_logs per tenant plus the central
 * activity_log. Tenant deletes run inside an explicit transaction
 * with SET LOCAL so PostgreSQL RLS is satisfied instead of
 * silently matching zero rows.
 */
class PruneAuditLogsCommand extends Command
{
    protected $signature = 'compliance:prune-audit-logs {--days= : Retention window in days (default: config compliance.audit_retention_days)}';

    protected $description = 'Delete audit log rows older than the retention window';

    public function handle(): int
    {
        $raw = $this->option('days');
        $days = $raw !== null && $raw !== '' ? (int) $raw : (int) config('compliance.audit_retention_days', 365);

        if ($days < 1) {
            $this->error('Retention window must be at least 1 day.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $pruned = 0;

        $tenantIds = DB::table('tenants')->whereNull('deleted_at')->pluck('id');

        foreach ($tenantIds as $tenantId) {
            $pruned += DB::transaction(function () use ($tenantId, $cutoff) {
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement("SELECT set_config('app.tenant_id', ?, true)", [$tenantId]);
                }

                return DB::table('tenant_audit_logs')
                    ->where('tenant_id', $tenantId)
                    ->where('created_at', '<', $cutoff)
                    ->delete();
            });
        }

        $pruned += Activity::where('created_at', '<', $cutoff)->delete();

        $this->info("Pruned {$pruned} audit row(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
