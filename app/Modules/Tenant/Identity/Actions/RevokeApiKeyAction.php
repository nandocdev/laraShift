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

        app(\App\Modules\Tenant\Audit\Actions\RecordAuditLogAction::class)->execute(
            new \App\Modules\Tenant\Audit\DTOs\AuditLogData(
                action: \App\Modules\Tenant\Audit\Enums\AuditAction::API_KEY_REVOKED,
                resource: 'api_key',
                resourceId: $apiKey->id,
                metadata: ['name' => $apiKey->name]
            )
        );

        activity('identity')
            ->performedOn($apiKey)
            ->log('api_key_revoked');

        event(new \App\Modules\Shared\Events\TenantApiKeyRevoked($apiKey));
    }
}
