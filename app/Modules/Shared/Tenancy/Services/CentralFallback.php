<?php

declare(strict_types=1);

namespace App\Modules\Shared\Tenancy\Services;

use Illuminate\Http\Request;

final readonly class CentralFallback
{
    private const string CENTRAL_PREFIX = 'central.';

    /**
     * Determine if the current request should resolve to central mode.
     */
    public function isCentralRequest(Request $request): bool
    {
        $host = $request->getHost();
        $centralDomains = config('tenancy.central_domains', []);

        if (in_array($host, $centralDomains, true)) {
            return true;
        }

        if ($request->route() && str_starts_with($request->route()->getName() ?? '', self::CENTRAL_PREFIX)) {
            return true;
        }

        if (str_starts_with($request->path(), 'central/')) {
            return true;
        }

        return false;
    }

    /**
     * Assert that tenancy is NOT initialized. Used in central-only routes.
     */
    public function ensureCentral(): void
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            abort(404);
        }
    }

    /**
     * Assert that tenancy IS initialized. Used in tenant-only routes.
     */
    public function ensureTenant(): void
    {
        if (! function_exists('tenant') || ! tenant()) {
            abort(404);
        }
    }

    /**
     * Get the central domain URL.
     */
    public function centralUrl(string $path = ''): string
    {
        $domain = config('tenancy.central_domain', 'localhost');
        $protocol = app()->isProduction() ? 'https' : 'http';

        return "{$protocol}://{$domain}/{$path}";
    }
}
