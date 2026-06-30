<?php

declare(strict_types=1);

namespace App\Modules\Shared\Tenancy\Jobs;

use App\Modules\Shared\Tenancy\Services\TenantResolver;
use App\Modules\Shared\Tenancy\ValueObjects\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

abstract class AbstractTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public array $tenantContext;

    public string $tenantId;

    public string $tenantSlug;

    public int $tries = 3;

    public int $maxExceptions = 3;

    public int $priority = 0;

    abstract public function handle(): void;

    final public function __construct(TenantContext $context)
    {
        $this->tenantContext = $context->toArray();
        $this->tenantId = $context->tenantId();
        $this->tenantSlug = $context->tenantSlug() ?? '';
    }

    /**
     * Initialize tenancy before executing business logic.
     */
    final public function initializeTenancy(): ?TenantContext
    {
        if (! function_exists('tenancy')) {
            Log::warning('Tenancy not available in job', ['job' => static::class]);

            return null;
        }

        $context = TenantContext::fromArray($this->tenantContext);

        if (tenancy()->initialized) {
            $current = tenancy()->tenant->getTenantKey();

            if ((string) $current === $context->tenantId()) {
                return $context;
            }

            tenancy()->end();
        }

        $resolver = App::make(TenantResolver::class);
        $tenant = $resolver->findById($context->tenantId());

        if (! $tenant) {
            Log::error('Tenant not found in job', [
                'job' => static::class,
                'tenant_id' => $context->tenantId(),
            ]);

            return null;
        }

        tenancy()->initialize($tenant);

        return $context;
    }

    /**
     * The queue name for this job based on priority and tenant.
     */
    public function queueName(): string
    {
        $bucket = (crc32($this->tenantId) % 5) + 1;
        $priority = match (true) {
            $this->priority >= 5 => 'high',
            $this->priority <= 2 => 'low',
            default => 'default',
        };

        return "tenant.b{$bucket}.{$priority}";
    }

    /**
     * Called when the job fails after all retries.
     */
    public function failed(\Throwable $e): void
    {
        Log::error('Tenant job failed after all retries', [
            'job' => static::class,
            'tenant_id' => $this->tenantId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
