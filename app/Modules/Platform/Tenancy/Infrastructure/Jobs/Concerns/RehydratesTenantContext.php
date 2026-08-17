<?php

declare(strict_types=1);

namespace App\Modules\Platform\Tenancy\Infrastructure\Jobs\Concerns;

use App\Modules\Platform\Tenancy\Infrastructure\Jobs\RehydrateTenantContext;

/**
 * Removes the TenantAware boilerplate from tenant-scoped jobs.
 *
 * Every job that touches tenant-aware data must implement TenantAware and
 * declare the RehydrateTenantContext middleware. This trait provides both:
 * tenantId() reads the `tenantId` property and middleware() wires the
 * context rehydration. Usage:
 *
 * ```php
 * class MyJob implements ShouldQueue, TenantAware
 * {
 *     use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, RehydratesTenantContext;
 *
 *     public function __construct(public string $tenantId) {}
 * }
 * ```
 */
trait RehydratesTenantContext
{
    public function tenantId(): string
    {
        return $this->tenantId;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RehydrateTenantContext];
    }
}
