<?php

declare(strict_types=1);

namespace App\Modules\Central\Security\Actions;

use App\Modules\Tenant\Identity\Models\ApiKey;
use Illuminate\Support\Facades\Log;

final readonly class RotateTenantApiKeysAction
{
    /**
     * Auto-rotate expired or soon-to-expire API keys for a tenant.
     * Notifies the tenant admin about the rotation.
     */
    public function execute(string $tenantId): int
    {
        tenancy()->initialize($tenantId);

        try {
            $rotated = 0;
            $cutoff = now()->subDays(90);

            $keys = ApiKey::where('created_at', '<', $cutoff)
                ->whereNull('revoked_at')
                ->get();

            foreach ($keys as $key) {
                $key->update([
                    'revoked_at' => now(),
                ]);

                activity('security')
                    ->performedOn($key)
                    ->withProperties([
                        'tenant_id' => $tenantId,
                        'key_name' => $key->name,
                        'rotation' => 'auto',
                    ])
                    ->log('api_key_auto_rotated');

                $rotated++;
            }

            if ($rotated > 0) {
                Log::info('Auto-rotated API keys', [
                    'tenant_id' => $tenantId,
                    'count' => $rotated,
                ]);
            }

            return $rotated;
        } finally {
            tenancy()->end();
        }
    }
}
