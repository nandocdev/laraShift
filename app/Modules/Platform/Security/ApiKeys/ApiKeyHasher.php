<?php

declare(strict_types=1);

namespace App\Modules\Platform\Security\ApiKeys;

use App\Modules\Platform\Security\Hmac\HmacSigner;

class ApiKeyHasher
{
    /**
     * Generates a cryptographically secure API key with a tnt_ prefix.
     */
    public function generate(): string
    {
        return 'tnt_'.bin2hex(random_bytes(32));
    }

    /**
     * Hashes the plain key for secure storage using HMAC-SHA256.
     */
    public function hash(string $plainKey): string
    {
        return HmacSigner::hash($plainKey, (string) config('app.key'));
    }

    /**
     * Verifies a plain key against a stored hash.
     */
    public function verify(string $plainKey, string $hash): bool
    {
        return HmacSigner::verify($plainKey, (string) config('app.key'), $hash);
    }

    /**
     * Checks if a token has a valid API key format.
     */
    public function isValidFormat(string $token): bool
    {
        return str_starts_with($token, 'tnt_');
    }
}
