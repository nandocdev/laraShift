<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Access\Interface\Http\Middleware;

use App\Modules\Tenant\Access\Domain\Models\TenantSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class ValidateTenantSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $tracked = TenantSession::where('session_id', Session::getId())->first();

            if ($tracked && $tracked->revoked_at !== null) {
                auth()->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login');
            }
        }

        return $next($request);
    }
}
