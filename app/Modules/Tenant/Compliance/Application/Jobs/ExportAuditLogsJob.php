<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Compliance\Application\Jobs;

use App\Modules\Platform\Contracts\TenantAware;
use App\Modules\Platform\Tenancy\Infrastructure\Jobs\Concerns\RehydratesTenantContext;
use App\Modules\Tenant\Compliance\Application\Contracts\UserResolverContract;
use App\Modules\Tenant\Compliance\Application\Queries\GetAuditLogsForExport;
use App\Modules\Tenant\Compliance\Domain\Models\AuditLog;
use App\Modules\Tenant\Compliance\Infrastructure\Notifications\AuditLogExportNotification;
use BackedEnum;
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

    public function handle(
        UserResolverContract $userResolver,
        GetAuditLogsForExport $queryBuilder
    ): void {
        $user = $userResolver->resolve($this->userId);

        if (! $user) {
            return;
        }

        $start = Carbon::parse($this->dateFrom)->startOfDay();
        $end = Carbon::parse($this->dateTo)->endOfDay();

        if ($start->gt($end) || $start->diffInDays($end) > 90) {
            Log::error('ExportAuditLogsJob: Range invalid or exceeded security policy.', [
                'tenant_id' => $this->tenantId,
                'user_id' => $this->userId,
                'from' => $this->dateFrom,
                'to' => $this->dateTo,
            ]);

            return;
        }

        $query = $queryBuilder->execute(
            dateFrom: $this->dateFrom,
            dateTo: $this->dateTo
        );

        $tmpPath = tempnam(sys_get_temp_dir(), 'audit_export');
        if ($tmpPath === false) {
            Log::error('ExportAuditLogsJob: Failed to create temporary file.');

            return;
        }

        $handle = fopen($tmpPath, 'w');
        if ($handle === false) {
            Log::error('ExportAuditLogsJob: Failed to open temporary file stream.');
            @unlink($tmpPath);

            return;
        }

        fputcsv($handle, ['ID', 'Date', 'Action', 'Member', 'Resource', 'Resource ID', 'IP', 'Metadata']);

        /** @var AuditLog $log */
        foreach ($query->cursor() as $log) {
            $actionValue = $log->action instanceof BackedEnum ? $log->action->value : (string) $log->action;

            fputcsv($handle, [
                $log->id,
                $log->created_at?->toDateTimeString() ?? '',
                $actionValue,
                $log->user?->name ?? 'System',
                $log->resource,
                $log->resource_id,
                $log->ip,
                json_encode($log->metadata ?? []),
            ]);
        }
        fclose($handle);

        $fileName = "exports/audit/audit_log_{$this->tenantId}_".Str::random(8).'.csv';
        Storage::disk('private')->putFileAs('', new File($tmpPath), $fileName);
        @unlink($tmpPath);

        if (method_exists($user, 'notify')) {
            $user->notify(new AuditLogExportNotification($fileName));
        }
    }
}
