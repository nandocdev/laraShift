<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Access\Application\Actions;

use App\Modules\Tenant\Access\Domain\Models\ApiKey;

final readonly class RevokeApiKey
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

        event(new \App\Modules\Platform\Events\TenantApiKeyRevoked((string) $apiKey->id, (string) $apiKey->tenant_id));
    }
}
