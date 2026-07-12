<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Access\Application\Actions;

use App\Modules\Platform\Events\TenantApiKeyCreated;
use App\Modules\Platform\Security\ApiKeys\ApiKeyHasher;
use App\Modules\Tenant\Access\Domain\Models\ApiKey;
use App\Modules\Tenant\Access\Domain\Models\User;
use App\Modules\Tenant\Compliance\Application\Actions\RecordAuditLogAction;
use App\Modules\Tenant\Compliance\Domain\DTOs\AuditLogData;
use App\Modules\Tenant\Compliance\Domain\Enums\AuditAction;
use Illuminate\Support\Str;

final readonly class GenerateApiKey
{
    public function __construct(
        private ApiKeyHasher $hasher,
    ) {}

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
        ?User $creator = null,
    ): array {
        $plainKey = $this->hasher->generate();

        $apiKey = ApiKey::create([
            'id' => Str::uuid()->toString(),
            'tenant_id' => tenant('id'),
            'name' => $name,
            'key_hash' => $this->hasher->hash($plainKey),
            'scopes' => $scopes,
            'created_by' => $creator?->id,
        ]);

        app(RecordAuditLogAction::class)->execute(
            new AuditLogData(
                action: AuditAction::API_KEY_CREATED,
                resource: 'api_key',
                resourceId: $apiKey->id,
                metadata: ['name' => $name, 'scopes' => $scopes],
                userId: $creator?->id,
            )
        );

        activity('identity')
            ->performedOn($apiKey)
            ->withProperties(['name' => $name, 'scopes' => $scopes])
            ->log('api_key_generated');

        event(new TenantApiKeyCreated((string) $apiKey->id, $apiKey->name, (string) $apiKey->tenant_id, $apiKey->scopes));

        return [
            'key' => $plainKey,
            'model' => $apiKey,
        ];
    }
}
