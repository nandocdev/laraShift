<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Compliance\Application\Jobs;

use App\Modules\Platform\Contracts\TenantAware;
use App\Modules\Platform\Tenancy\Infrastructure\Jobs\Concerns\RehydratesTenantContext;
use App\Modules\Tenant\Access\Domain\Models\User;
use App\Modules\Tenant\Compliance\Domain\Models\AuditLog;
use App\Modules\Tenant\Compliance\Infrastructure\Notifications\AuditLogExportNotification;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
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
        $user = User::find($this->userId);

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

        $logs = AuditLog::with('user')
            ->whereDate('created_at', '>=', $this->dateFrom)
            ->whereDate('created_at', '<=', $this->dateTo)
            ->oldest()
            ->get();

        $fileName = "exports/audit/audit_log_{$this->tenantId}_".Str::random(8).'.csv';
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, ['ID', 'Date', 'Action', 'Member', 'Resource', 'Resource ID', 'IP', 'Metadata']);

        foreach ($logs as $log) {
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

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        Storage::disk('private')->put($fileName, $content);

        $user->notify(new AuditLogExportNotification($fileName));
    }
}
