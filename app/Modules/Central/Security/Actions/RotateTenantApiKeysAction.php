<?php

declare(strict_types=1);

namespace App\Modules\Central\Security\Actions;

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Identity\Models\ApiKey;
use Illuminate\Support\Facades\Log;

final readonly class RotateTenantApiKeysAction
{
    /**
     * Auto-rotate expired or soon-to-expire API keys for a tenant.
     * Expects tenancy to be initialized before calling.
     */
    public function execute(Tenant $tenant): int
    {
        $rotated = 0;
        $cutoff = now()->subDays(90);

        $keys = ApiKey::where('tenant_id', $tenant->id)
            ->where('created_at', '<', $cutoff)
            ->whereNull('revoked_at')
            ->get();

        foreach ($keys as $key) {
            $key->update(['revoked_at' => now()]);

            activity('security')
                ->performedOn($key)
                ->withProperties([
                    'tenant_id' => $tenant->id,
                    'key_name' => $key->name,
                    'rotation' => 'auto',
                ])
                ->log('api_key_auto_rotated');

            $rotated++;
        }

        if ($rotated > 0) {
            Log::info('Auto-rotated API keys', [
                'tenant_id' => $tenant->id,
                'count' => $rotated,
            ]);
        }

        return $rotated;
    }
}
