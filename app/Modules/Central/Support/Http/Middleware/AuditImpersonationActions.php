<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Spatie\Activitylog\Facades\LogBatch;
use Symfony\Component\HttpFoundation\Response;

class AuditImpersonationActions
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Session::has('impersonated_by')) {
            LogBatch::setInBatch(function () {
                activity()
                    ->tap(function ($activity) {
                        $activity->properties = $activity->properties->merge([
                            'impersonated_by' => Session::get('impersonated_by'),
                            'support_session_id' => Session::get('impersonation_session_id'),
                        ]);
                    });
            });
        }

        return $next($request);
    }
}
