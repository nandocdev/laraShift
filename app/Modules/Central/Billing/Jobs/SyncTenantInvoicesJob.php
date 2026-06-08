<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Jobs;

use App\Modules\Central\Billing\Actions\SyncInvoicesAction;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncTenantInvoicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant
    ) {}

    public function handle(SyncInvoicesAction $action): void
    {
        try {
            $action->execute($this->tenant);
        } catch (\Exception $e) {
            Log::error("Failed to sync invoices for tenant {$this->tenant->id}: " . $e->getMessage());
            throw $e;
        }
    }
}
