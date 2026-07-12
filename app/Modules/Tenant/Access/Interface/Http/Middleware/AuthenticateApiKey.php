<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Access\Interface\Http\Middleware;

use App\Modules\Platform\Security\ApiKeys\ApiKeyHasher;
use App\Modules\Tenant\Access\Domain\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    public function __construct(
        private ApiKeyHasher $hasher,
    ) {}

    /**
     * Handle an incoming request.
     *
     * Authenticates the request using the provided API Key.
     */
    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        $token = $request->bearerToken();

        if (! $token || ! $this->hasher->isValidFormat($token)) {
            return response()->json(['message' => 'Unauthorized. Invalid API Key format.'], 401);
        }

        $hash = $this->hasher->hash($token);

        $apiKey = ApiKey::where('key_hash', $hash)
            ->whereNull('revoked_at')
            ->first();

        if (! $apiKey) {
            return response()->json(['message' => 'Unauthorized. Invalid or revoked API Key.'], 401);
        }

        if (! empty($scopes)) {
            foreach ($scopes as $scope) {
                if (! in_array($scope, $apiKey->scopes)) {
                    return response()->json(['message' => "Forbidden. Missing scope: {$scope}"], 403);
                }
            }
        }

        if (! $apiKey->last_used_at || $apiKey->last_used_at->diffInMinutes(now()) >= 15) {
            $apiKey->update(['last_used_at' => now()]);
        }

        $request->attributes->set('api_key', $apiKey);
        $request->attributes->set('api_scopes', $apiKey->scopes);

        if ($apiKey->creator) {
            auth()->login($apiKey->creator);
        }

        return $next($request);
    }
}
