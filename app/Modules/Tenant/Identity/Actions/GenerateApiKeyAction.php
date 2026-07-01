<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Actions;

use App\Modules\Shared\Events\TenantApiKeyCreated;
use App\Modules\Tenant\Audit\Actions\RecordAuditLogAction;
use App\Modules\Tenant\Audit\DTOs\AuditLogData;
use App\Modules\Tenant\Audit\Enums\AuditAction;
use App\Modules\Tenant\Identity\Models\ApiKey;
use App\Modules\Tenant\Identity\Models\User;
use Illuminate\Support\Str;

final readonly class GenerateApiKeyAction
{
    /**
     * Generates a new secure API Key for the tenant.
     *
     * Returns an array with:
     * - 'key': The plain text key (only shown once)
     * - 'model': The saved ApiKey model
     */
    public function execute(
        string $name,
        array $scopes,
        ?User $creator = null
    ): array {
        // 1. Generate high-entropy key
        // PRD: tnt_{random_32_bytes_hex}
        $plainKey = 'tnt_'.bin2hex(random_bytes(32));

        // 2. Create the model
        $apiKey = ApiKey::create([
            'id' => Str::uuid()->toString(),
            'tenant_id' => tenant('id'),
            'name' => $name,
            'key_hash' => hash_hmac('sha256', $plainKey, config('app.key')),
            'scopes' => $scopes,
            'created_by' => $creator?->id,
        ]);

        app(RecordAuditLogAction::class)->execute(
            new AuditLogData(
                action: AuditAction::API_KEY_CREATED,
                resource: 'api_key',
                resourceId: $apiKey->id,
                metadata: ['name' => $name, 'scopes' => $scopes],
                userId: $creator?->id
            )
        );

        activity('identity')
            ->performedOn($apiKey)
            ->withProperties(['name' => $name, 'scopes' => $scopes])
            ->log('api_key_generated');

        event(new TenantApiKeyCreated($apiKey));

        return [
            'key' => $plainKey,
            'model' => $apiKey,
        ];
    }
}
