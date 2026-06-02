<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Audit\Jobs;

use App\Modules\Tenant\Audit\Models\AuditLog;
use App\Modules\Tenant\Audit\Notifications\AuditLogExportNotification;
use App\Modules\Tenant\Identity\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExportAuditLogsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $tenantId,
        public string $userId,
        public string $dateFrom,
        public string $dateTo
    ) {}

    public function handle(): void
    {
        $user = User::withoutGlobalScope(\App\Modules\Shared\Tenancy\Models\Concerns\TenantScope::class)->find($this->userId);
        
        if (! $user) return;

        $logs = AuditLog::withoutGlobalScope(\App\Modules\Shared\Tenancy\Models\Concerns\TenantScope::class)
            ->with('user')
            ->where('tenant_id', $this->tenantId)
            ->whereDate('created_at', '>=', $this->dateFrom)
            ->whereDate('created_at', '<=', $this->dateTo)
            ->oldest()
            ->get();

        $fileName = "exports/audit/audit_log_{$this->tenantId}_" . Str::random(8) . ".csv";
        $handle = fopen('php://temp', 'r+');
        
        // CSV Headers
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

        // Notify User
        $user->notify(new AuditLogExportNotification($fileName));
    }
}
