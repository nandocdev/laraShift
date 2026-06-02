<?php

declare(strict_types=1);

namespace App\Modules\Shared\Events;

use App\Modules\Tenant\Identity\Models\ApiKey;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TenantApiKeyCreated
{
    use Dispatchable, SerializesModels;

    public string $tenantId;
    public string $keyId;
    public array $scopes;

    public function __construct(public ApiKey $apiKey) {
        $this->tenantId = (string) $apiKey->tenant_id;
        $this->keyId = (string) $apiKey->id;
        $this->scopes = $apiKey->scopes;
    }
}
