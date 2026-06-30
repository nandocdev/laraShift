<?php

declare(strict_types=1);

namespace App\Modules\Shared\Tenancy\ValueObjects;

use App\Modules\Shared\ValueObjects\Uuid;

/**
 * Immutable value object that represents the tenant context
 * for propagation across async boundaries (queues, events).
 */
final readonly class TenantContext
{
    public function __construct(
        private string $tenantId,
        private ?string $tenantSlug = null,
    ) {
        Uuid::fromString($tenantId);
    }

    public static function fromCurrent(): ?self
    {
        if (! function_exists('tenancy') || ! tenancy()->initialized) {
            return null;
        }

        $tenant = tenancy()->tenant;

        return new self(
            tenantId: (string) $tenant->getTenantKey(),
            tenantSlug: method_exists($tenant, 'getSlug') ? $tenant->getSlug() : null,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            tenantId: $data['tenant_id'],
            tenantSlug: $data['tenant_slug'] ?? null,
        );
    }

    public function tenantId(): string
    {
        return $this->tenantId;
    }

    public function tenantSlug(): ?string
    {
        return $this->tenantSlug;
    }

    public function initialize(): void
    {
        if (! function_exists('tenancy')) {
            return;
        }

        $tenant = tenancy()->find($this->tenantId);

        if ($tenant) {
            tenancy()->initialize($tenant);
        }
    }

    public function toArray(): array
    {
        return array_filter([
            'tenant_id' => $this->tenantId,
            'tenant_slug' => $this->tenantSlug,
        ]);
    }

    public function equals(self $other): bool
    {
        return $this->tenantId === $other->tenantId;
    }

    public function __toString(): string
    {
        return $this->tenantId;
    }
}
