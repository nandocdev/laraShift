<?php

declare(strict_types=1);

namespace App\Modules\Shared\Contracts;

interface TenantService
{
    public function findById(string $id): ?TenantContract;

    public function findBySlug(string $slug): ?TenantContract;

    public function findByDomain(string $domain): ?TenantContract;

    public function isActive(TenantContract $tenant): bool;

    public function isSuspended(TenantContract $tenant): bool;

    public function hasFeature(TenantContract $tenant, string $feature): bool;

    public function getPlan(string $tenantId): ?string;
}
