<?php

declare(strict_types=1);

namespace App\Modules\Central\Security\Actions;

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Central\Security\Models\TenantEncryptionKey;
use Illuminate\Support\Str;

final readonly class RotateEncryptionKeyAction
{
    public function execute(Tenant $tenant, string $purpose = 'at_rest', ?string $rotatedBy = null): TenantEncryptionKey
    {
        TenantEncryptionKey::where('tenant_id', $tenant->id)
            ->where('purpose', $purpose)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $key = TenantEncryptionKey::create([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $tenant->id,
            'key_identifier' => 'key_'.Str::random(16),
            'encrypted_key' => encrypt(bin2hex(random_bytes(32))),
            'purpose' => $purpose,
            'is_active' => true,
            'rotated_at' => now(),
            'expires_at' => now()->addDays(90),
            'rotated_by' => $rotatedBy,
        ]);

        activity('security')
            ->performedOn($tenant)
            ->withProperties([
                'key_id' => $key->id,
                'purpose' => $purpose,
                'key_identifier' => $key->key_identifier,
            ])
            ->log('encryption_key_rotated');

        $tenant->notify(new \App\Modules\Central\Security\Notifications\KeyRotatedNotification($purpose, $tenant->name ?? 'Unknown'));

        return $key;
    }
}
