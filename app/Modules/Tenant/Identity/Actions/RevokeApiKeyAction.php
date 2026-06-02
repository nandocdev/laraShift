<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Actions;

use App\Modules\Tenant\Identity\Models\ApiKey;

final readonly class RevokeApiKeyAction
{
    /**
     * Immediately revokes an API Key.
     */
    public function execute(ApiKey $apiKey): void
    {
        $apiKey->update(['revoked_at' => now()]);

        activity('identity')
            ->performedOn($apiKey)
            ->log('api_key_revoked');
    }
}
