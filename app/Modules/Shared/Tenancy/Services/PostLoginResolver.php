<?php

declare(strict_types=1);

namespace App\Modules\Shared\Tenancy\Services;

use App\Modules\Central\Provisioning\Models\Tenant;

final readonly class PostLoginResolver
{
    public function resolve(Tenant $tenant): string
    {
        $centralDomain = config('tenancy.central_domain', 'localhost');
        $protocol = app()->isProduction() ? 'https' : 'http';
        $slug = $tenant->slug;

        $domain = $tenant->domains()->first();

        if ($domain && $domain->domain) {
            if (str_contains($domain->domain, '.')) {
                return "{$protocol}://{$domain->domain}";
            }

            return "{$protocol}://{$domain->domain}.{$centralDomain}";
        }

        return "{$protocol}://{$slug}.{$centralDomain}";
    }

    public function resolveFromSlug(string $slug): ?string
    {
        $tenant = app(TenantResolver::class)->findBySlug($slug);

        if (! $tenant) {
            return null;
        }

        return $this->resolve($tenant);
    }

    public function isTenantUrl(string $url): bool
    {
        $centralDomains = config('tenancy.central_domains', []);

        foreach ($centralDomains as $domain) {
            if (str_contains($url, ".{$domain}")) {
                return true;
            }
        }

        return false;
    }
}
