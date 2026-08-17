<?php

declare(strict_types=1);

namespace App\Modules\Platform\Tenancy\Domain\Concerns;

/**
 * Applies the TenantScope global scope to a model (defense-in-depth on top of
 * RLS). Unlike stancl's BelongsToTenant it adds no creating hook: the tenant_id
 * is always set explicitly by the writing Action.
 *
 * Usage:
 *
 * ```php
 * class Payment extends Model
 * {
 *     use HasUuids, ScopedToTenant;
 * }
 * ```
 */
trait ScopedToTenant
{
    protected static function bootScopedToTenant(): void
    {
        static::addGlobalScope(new TenantScope);
    }
}
