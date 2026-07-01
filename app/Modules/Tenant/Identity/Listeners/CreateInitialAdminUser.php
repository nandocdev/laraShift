<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Listeners;

use App\Modules\Central\Provisioning\Notifications\WelcomeTenantNotification;
use App\Modules\Shared\Events\TenantProvisioned;
use App\Modules\Tenant\Identity\Actions\EnsureTenantRolesExistAction;
use App\Modules\Tenant\Identity\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateInitialAdminUser
{
    public function handle(TenantProvisioned $event): void
    {
        $event->tenant->run(function () use ($event) {
            // 1. Ensure system roles exist for this tenant
            app(EnsureTenantRolesExistAction::class)->execute($event->tenant);

            // 2. Create the user
            $password = $event->password ?: (app()->environment(['local', 'testing']) ? 'password' : Str::random(16));

            $user = User::create([
                'tenant_id' => $event->tenant->id,
                'name' => $event->adminName,
                'email' => $event->adminEmail,
                'password' => Hash::make($password),
            ]);

            // 3. Assign owner role
            setPermissionsTeamId($event->tenant->id);
            $user->assignRole('Owner');

            // 4. Notify user as per PRD US-101
            $user->notify(new WelcomeTenantNotification(
                $event->tenant->name,
                $event->tenant->domains->first()?->domain ?? 'localhost'
            ));
        });
    }
}
