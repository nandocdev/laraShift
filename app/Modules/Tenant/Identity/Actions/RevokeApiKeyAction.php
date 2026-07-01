<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Actions;

use App\Modules\Shared\Events\TenantApiKeyRevoked;
use App\Modules\Tenant\Audit\Actions\RecordAuditLogAction;
use App\Modules\Tenant\Audit\DTOs\AuditLogData;
use App\Modules\Tenant\Audit\Enums\AuditAction;
use App\Modules\Tenant\Identity\Models\ApiKey;

final readonly class RevokeApiKeyAction
{
    /**
     * Immediately revokes an API Key.
     */
    public function execute(ApiKey $apiKey): void
    {
        $apiKey->update(['revoked_at' => now()]);

        app(RecordAuditLogAction::class)->execute(
            new AuditLogData(
                action: AuditAction::API_KEY_REVOKED,
                resource: 'api_key',
                resourceId: $apiKey->id,
                metadata: ['name' => $apiKey->name]
            )
        );

        activity('identity')
            ->performedOn($apiKey)
            ->log('api_key_revoked');

        event(new TenantApiKeyRevoked($apiKey));
    }
}
