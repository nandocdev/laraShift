<?php

declare(strict_types=1);

namespace App\Modules\Shared\Tenancy\Services;

use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final readonly class TenantResolver
{
    private const CACHE_TTL = 3600;

    public function resolve(Request $request): ?Tenant
    {
        return $this->resolveBySubdomain($request)
            ?? $this->resolveByHeader($request)
            ?? $this->resolveBySession($request);
    }

    public function resolveBySubdomain(Request $request): ?Tenant
    {
        $host = $request->getHost();
        $centralDomains = config('tenancy.central_domains', []);
        $centralDomain = config('tenancy.central_domain');

        if (in_array($host, $centralDomains, true)) {
            return null;
        }

        $subdomain = null;
        foreach ($centralDomains as $domain) {
            if (str_ends_with($host, '.' . $domain)) {
                $subdomain = substr($host, 0, -(strlen($domain) + 1));
                break;
            }
        }

        if ($centralDomain && str_ends_with($host, '.' . $centralDomain)) {
            $subdomain = $subdomain ?? substr($host, 0, -(strlen($centralDomain) + 1));
        }

        if (! $subdomain || $subdomain === 'www') {
            return null;
        }

        return $this->findBySlug($subdomain);
    }

    public function resolveByHeader(Request $request): ?Tenant
    {
        $tenantId = $request->header('X-Tenant-Id')
            ?? $request->header('X-Tenant')
            ?? $request->header('Tenant');

        if (! $tenantId) {
            return null;
        }

        return $this->findById($tenantId)
            ?? $this->findBySlug($tenantId);
    }

    public function resolveBySession(Request $request): ?Tenant
    {
        if (! $request->hasSession()) {
            return null;
        }

        $tenantId = $request->session()->get('tenant_id');

        if (! $tenantId) {
            return null;
        }

        return $this->findById($tenantId);
    }

    public function findById(string $id): ?Tenant
    {
        return Cache::remember("tenant:id:{$id}", self::CACHE_TTL, function () use ($id) {
            return Tenant::find($id)?->id;
        }) ? Tenant::find($id) : null;
    }

    public function findBySlug(string $slug): ?Tenant
    {
        $tenantId = Cache::remember("tenant:slug:{$slug}", self::CACHE_TTL, function () use ($slug) {
            return Tenant::where('slug', $slug)->first()?->id;
        });

        return $tenantId ? $this->findById($tenantId) : null;
    }

    public function forgetCache(Tenant $tenant): void
    {
        Cache::forget("tenant:id:{$tenant->id}");
        Cache::forget("tenant:slug:{$tenant->slug}");
    }
}
