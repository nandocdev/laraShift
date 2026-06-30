<?php

declare(strict_types=1);

namespace App\Modules\Shared\Tenancy\Http\Middleware;

use App\Modules\Tenant\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserQuota
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! function_exists('tenant') || ! tenant()) {
            return $next($request);
        }

        $tenant = tenant();

        if ($request->isMethod('post') && $request->routeIs('*.users.store')) {
            $limit = $this->resolveUserLimit($tenant);

            if ($limit > 0) {
                $current = User::count();

                if ($current >= $limit) {
                    if ($request->expectsJson()) {
                        return response()->json([
                            'message' => __('User limit reached for your plan. Please upgrade.'),
                            'code' => 'user_quota_exceeded',
                        ], 429);
                    }

                    return back()->with('error', __('User limit reached for your plan. Please upgrade.'));
                }
            }
        }

        return $next($request);
    }

    private function resolveUserLimit($tenant): int
    {
        try {
            $plan = $tenant->plan;

            return (int) ($plan->features['quotas']['max_users'] ?? -1);
        } catch (\Throwable) {
            return -1;
        }
    }
}
