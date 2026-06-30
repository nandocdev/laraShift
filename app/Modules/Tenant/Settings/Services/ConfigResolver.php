<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Settings\Services;

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Settings\Models\TenantSetting;

/**
 * Resolves configuration values using hierarchy:
 * Platform default → Plan default → Tenant override
 */
final readonly class ConfigResolver
{
    /**
     * Get a resolved config value for a tenant.
     */
    public function get(Tenant $tenant, string $key, mixed $default = null): mixed
    {
        return $this->resolve($tenant, $key) ?? $default;
    }

    /**
     * Resolve all config values for a tenant.
     *
     * @return array<string, mixed>
     */
    public function getAll(Tenant $tenant): array
    {
        $settings = $this->getSettings($tenant);
        $plan = $tenant->plan;

        return [
            'name' => $settings?->name ?? $tenant->name,
            'timezone' => $this->resolve($tenant, 'timezone', 'UTC'),
            'locale' => $this->resolve($tenant, 'locale', config('app.locale', 'en')),
            'currency' => $this->resolve($tenant, 'currency', 'USD'),
            'primary_color' => $this->resolve($tenant, 'primary_color', '#2563eb'),
            'logo_path' => $settings?->logo_path,
            'mfa_required' => (bool) $this->resolve($tenant, 'mfa_required', false),
            'smtp_configured' => $settings?->smtp_host !== null,
            'plan_name' => $plan?->name ?? 'Free',
            'plan_features' => $plan?->features ?? [],
        ];
    }

    /**
     * Resolve a single key: TenantSetting → Plan features → Global config.
     */
    private function resolve(Tenant $tenant, string $key, mixed $default = null): mixed
    {
        $settings = $this->getSettings($tenant);

        if ($settings && ! is_null($settings->{$key} ?? null)) {
            return $settings->{$key};
        }

        $plan = $tenant->plan;
        if ($plan && isset($plan->features['settings'][$key])) {
            return $plan->features['settings'][$key];
        }

        $globalDefaults = [
            'timezone' => 'UTC',
            'locale' => 'en',
            'currency' => 'USD',
            'primary_color' => '#2563eb',
        ];

        return $globalDefaults[$key] ?? $default;
    }

    private function getSettings(Tenant $tenant): ?TenantSetting
    {
        return TenantSetting::where('tenant_id', $tenant->id)->first();
    }
}
