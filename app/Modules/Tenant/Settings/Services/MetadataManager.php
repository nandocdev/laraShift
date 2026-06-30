<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Settings\Services;

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Events\TenantSettingsUpdated;
use Illuminate\Support\Facades\Log;

/**
 * Manages dynamic metadata and business rules stored in the tenant's data JSON column.
 */
final readonly class MetadataManager
{
    /**
     * Get a metadata value for the tenant.
     */
    public function get(Tenant $tenant, string $key, mixed $default = null): mixed
    {
        $data = $tenant->data ?? [];

        return $data[$key] ?? $default;
    }

    /**
     * Set a metadata value for the tenant.
     */
    public function set(Tenant $tenant, string $key, mixed $value): void
    {
        $data = (array) $tenant->getAttribute('data');
        $data[$key] = $value;

        $tenant->data = $data;
        $tenant->save();

        Log::info("Tenant metadata updated: {$key}", ['tenant_id' => $tenant->id]);
    }

    /**
     * Set multiple metadata values at once.
     *
     * @param array<string, mixed> $values
     */
    public function setMultiple(Tenant $tenant, array $values): void
    {
        $data = (array) $tenant->getAttribute('data');

        foreach ($values as $key => $value) {
            $data[$key] = $value;
        }

        $tenant->data = $data;
        $tenant->save();
    }

    /**
     * Remove a metadata key.
     */
    public function remove(Tenant $tenant, string $key): void
    {
        $data = (array) $tenant->getAttribute('data');

        if (array_key_exists($key, $data)) {
            unset($data[$key]);
            $tenant->data = $data;
            $tenant->save();
        }
    }

    /**
     * Get all metadata for the tenant.
     *
     * @return array<string, mixed>
     */
    public function getAll(Tenant $tenant): array
    {
        return (array) $tenant->getAttribute('data');
    }

    /**
     * Get a business rule value.
     */
    public function getRule(Tenant $tenant, string $rule, mixed $default = null): mixed
    {
        $rules = $this->get($tenant, 'business_rules', []);

        return $rules[$rule] ?? $default;
    }

    /**
     * Set a business rule.
     */
    public function setRule(Tenant $tenant, string $rule, mixed $value): void
    {
        $data = $tenant->data ?? [];
        $rules = $data['business_rules'] ?? [];
        $rules[$rule] = $value;
        $data['business_rules'] = $rules;

        $tenant->update(['data' => $data]);
    }
}
