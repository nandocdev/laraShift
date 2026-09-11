<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Access\Application\Listeners;

use App\Modules\Tenant\Access\Application\Actions\RecordTenantSession;
use App\Modules\Tenant\Access\Domain\Models\User;
use Illuminate\Auth\Events\Login;

class TrackTenantSession
{
    public function handle(Login $event): void
    {
        if ($event->guard !== 'web') {
            return;
        }

        if (! function_exists('tenancy') || ! tenancy()->initialized) {
            return;
        }

        if (! $event->user instanceof User) {
            return;
        }

        app(RecordTenantSession::class)->execute(
            $event->user,
            request()->ip(),
            request()->userAgent(),
        );
    }
}
