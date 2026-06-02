<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Http\Middleware;

use App\Modules\Tenant\Identity\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    /**
     * Handle an incoming request.
     * 
     * Authenticates the request using the provided API Key.
     */
    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        $token = $request->bearerToken();

        if (! $token || ! str_starts_with($token, 'tnt_')) {
            return response()->json(['message' => 'Unauthorized. Invalid API Key format.'], 401);
        }

        $hash = hash('sha256', $token);

        $apiKey = ApiKey::where('key_hash', $hash)
            ->whereNull('revoked_at')
            ->first();

        if (! $apiKey) {
            return response()->json(['message' => 'Unauthorized. Invalid or revoked API Key.'], 401);
        }

        // Verify if key belongs to the current tenant (TenantScope handles this via model)
        // If TenantScope didn't find it, $apiKey would be null.

        // Check Scopes (US-T104)
        if (! empty($scopes)) {
            foreach ($scopes as $scope) {
                if (! in_array($scope, $apiKey->scopes)) {
                    return response()->json(['message' => "Forbidden. Missing scope: {$scope}"], 403);
                }
            }
        }

        // Update last used timestamp (US-T104)
        $apiKey->update(['last_used_at' => now()]);

        // Attach the API Key model to the request for controller access
        $request->attributes->set('api_key', $apiKey);
        
        // Authenticate the user if the key is linked to one
        if ($apiKey->creator) {
            auth()->login($apiKey->creator);
            
            // Map API Key scopes to temporary permissions in the Gate (Integration)
            foreach ($apiKey->scopes as $scope) {
                \Illuminate\Support\Facades\Gate::define($scope, fn () => true);
            }
        }

        return $next($request);
    }
}
