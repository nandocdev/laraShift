<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Listeners;

use App\Modules\Shared\Events\TenantProvisioned;
use App\Modules\Tenant\Identity\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateInitialAdminUser
{
    public function handle(TenantProvisioned $event): void
    {
        $event->tenant->run(function () use ($event) {
            $user = User::create([
                'tenant_id' => $event->tenant->id,
                'name' => $event->adminName,
                'email' => $event->adminEmail,
                'password' => Hash::make(Str::random(16)), // Use random password for security
            ]);

            // Notify user as per PRD US-101
            $user->notify(new \App\Modules\Central\Provisioning\Notifications\WelcomeTenantNotification(
                $event->tenant->name,
                $event->tenant->domains->first()?->domain ?? 'localhost'
            ));
        });
    }
}
