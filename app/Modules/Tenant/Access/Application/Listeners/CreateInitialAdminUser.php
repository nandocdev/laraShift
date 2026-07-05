<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Access\Application\Listeners;

use App\Modules\Platform\Events\TenantProvisioned;
use App\Modules\Tenant\Access\Application\Actions\EnsureTenantRolesExist;
use App\Modules\Tenant\Access\Domain\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateInitialAdminUser
{
    public function handle(TenantProvisioned $event): void
    {
        $event->tenant->run(function () use ($event) {
            // 1. Ensure system roles exist for this tenant
            app(EnsureTenantRolesExist::class)->execute($event->tenant);

            // 2. Create the user
            $password = $event->password ?: (app()->environment(['local', 'testing']) ? 'password' : Str::random(16));
            
            $user = User::create([
                'tenant_id' => $event->tenant->id,
                'name' => $event->adminName,
                'email' => $event->adminEmail,
                'password' => Hash::make($password),
            ]);

            // 3. Assign admin role
            setPermissionsTeamId($event->tenant->id);
            $user->assignRole('admin');

            // 4. Notify user as per PRD US-101
            $user->notify(new \App\Modules\Central\Provisioning\Notifications\WelcomeTenantNotification(
                $event->tenant->name,
                $event->tenant->domains->first()?->domain ?? 'localhost'
            ));
        });
    }
}
