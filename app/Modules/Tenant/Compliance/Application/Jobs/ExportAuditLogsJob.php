<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Compliance\Application\Jobs;

use App\Modules\Platform\Contracts\TenantAware;
use App\Modules\Platform\Tenancy\Infrastructure\Jobs\Concerns\RehydratesTenantContext;
use App\Modules\Tenant\Compliance\Application\Queries\GetAuditLogsForExport;
use App\Modules\Tenant\Compliance\Domain\Models\AuditLog;
use App\Modules\Tenant\Compliance\Infrastructure\Notifications\AuditLogExportNotification;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\File;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExportAuditLogsJob implements ShouldQueue, TenantAware
{
    use Dispatchable, InteractsWithQueue, Queueable, RehydratesTenantContext, SerializesModels;

    public function __construct(
        public string $tenantId,
        public string $userId,
        public string $dateFrom,
        public string $dateTo
    ) {}

    public function handle(): void
    {
        // Resolver usuario via contrato para evitar acoplamiento directo con Access module
        $user = app('compliance.user-resolver')->resolve($this->userId);

        if (! $user) {
            return;
        }

        $diff = Carbon::parse($this->dateFrom)->diffInDays($this->dateTo);
        if ($diff > 90) {
            Log::error('ExportAuditLogsJob: Range exceeded security policy.', [
                'tenant_id' => $this->tenantId,
                'user_id' => $this->userId,
            ]);

            return;
        }

        // Usar Query object en lugar de consulta inline
        $query = app(GetAuditLogsForExport::class)->execute(
            tenantId: $this->tenantId,
            dateFrom: $this->dateFrom,
            dateTo: $this->dateTo
        );

        $tmpPath = tempnam(sys_get_temp_dir(), 'audit_export');
        $handle = fopen($tmpPath, 'w');

        fputcsv($handle, ['ID', 'Date', 'Action', 'Member', 'Resource', 'Resource ID', 'IP', 'Metadata']);

        foreach ($query->cursor() as $log) {
            fputcsv($handle, [
                $log->id,
                $log->created_at->toDateTimeString(),
                $log->action,
                $log->user?->name ?? 'System',
                $log->resource,
                $log->resource_id,
                $log->ip,
                json_encode($log->metadata),
            ]);
        }
        fclose($handle);

        $fileName = "exports/audit/audit_log_{$this->tenantId}_".Str::random(8).'.csv';
        Storage::disk('private')->putFileAs('', new File($tmpPath), $fileName);
        unlink($tmpPath);

        $user->notify(new AuditLogExportNotification($fileName));
    }
}
