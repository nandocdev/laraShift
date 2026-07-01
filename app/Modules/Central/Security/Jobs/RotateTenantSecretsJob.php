<?php

declare(strict_types=1);

namespace App\Modules\Central\Security\Jobs;

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Central\Security\Actions\ResolveTenantEncryptionPolicyAction;
use App\Modules\Central\Security\Actions\RotateEncryptionKeyAction;
use App\Modules\Central\Security\Actions\RotateTenantApiKeysAction;
use App\Modules\Central\Security\Models\TenantEncryptionKey;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RotateTenantSecretsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Tenant::whereNull('archived_at')->chunk(100, function ($tenants) {
            foreach ($tenants as $tenant) {
                try {
                    $policy = app(ResolveTenantEncryptionPolicyAction::class)->execute($tenant);
                    $rotationDays = $policy['key_rotation_days'];

                    $activeKey = TenantEncryptionKey::where('tenant_id', $tenant->id)
                        ->where('purpose', 'at_rest')
                        ->where('is_active', true)
                        ->first();

                    if (! $activeKey || $activeKey->created_at->diffInDays(now()) >= $rotationDays) {
                        app(RotateEncryptionKeyAction::class)->execute($tenant, 'at_rest');
                    }

                    $apiRotated = app(RotateTenantApiKeysAction::class)->execute($tenant->id);

                    if ($apiRotated > 0 || ($activeKey && $activeKey->created_at->diffInDays(now()) >= $rotationDays)) {
                        Log::info('Secrets rotated for tenant', [
                            'tenant_id' => $tenant->id,
                            'encryption_rotated' => ! $activeKey || $activeKey->created_at->diffInDays(now()) >= $rotationDays,
                            'api_keys_rotated' => $apiRotated,
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::error('Failed to rotate secrets for tenant', [
                        'tenant_id' => $tenant->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });
    }
}
