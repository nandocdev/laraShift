<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Spatie\Activitylog\Support\PendingActivityLog;
use Symfony\Component\HttpFoundation\Response;

class AuditImpersonationActions
{
    /**
     * Handle an incoming request.
     *
     * [SIDE-EFFECTS]
     * - Injects impersonation context into all activities logged during the request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Session::has('impersonated_by')) {
            $impersonatorId = Session::get('impersonated_by');
            $sessionId = Session::get('impersonation_session_id');

            // In Spatie Activitylog v5+, LogBatch was removed.
            // We use the beforeLogging hook to inject context globally for this request.
            PendingActivityLog::beforeLogging(function ($activity) use ($impersonatorId, $sessionId) {
                $properties = $activity->properties ? $activity->properties->toArray() : [];
                
                $activity->properties = collect(array_merge($properties, [
                    'impersonated_by' => $impersonatorId,
                    'support_session_id' => $sessionId,
                ]));
            });
        }

        try {
            return $next($request);
        } finally {
            // Security: Clear the static hook to prevent leakage in persistent environments like Octane.
            PendingActivityLog::beforeLogging(fn() => null);
        }
    }
}
