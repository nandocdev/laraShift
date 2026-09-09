<?php

declare(strict_types=1);

namespace App\Modules\Platform\Tenancy\Interface\Listeners;

use Illuminate\Support\Facades\Context;
use Stancl\Tenancy\Events\TenancyInitialized;

class SetTenantLogContext
{
    /**
     * Handle the event.
     */
    public function handle(TenancyInitialized $event): void
    {
        Context::add('tenant_id', (string) $event->tenant->getTenantKey());
        Context::add('tenant_slug', $event->tenant->slug ?? 'unknown');
    }
}
