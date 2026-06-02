<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Providers;

use App\Modules\Shared\Events\TenantProvisioned;
use App\Modules\Tenant\Identity\Listeners\CreateInitialAdminUser;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(
            TenantProvisioned::class,
            CreateInitialAdminUser::class
        );
    }
}
