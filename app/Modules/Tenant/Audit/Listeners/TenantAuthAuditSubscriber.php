<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Audit\Listeners;

use App\Modules\Tenant\Audit\Actions\RecordAuditLogAction;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;

class TenantAuthAuditSubscriber
{
    public function __construct(
        private RecordAuditLogAction $recordAuditLog
    ) {}

    public function handleUserLogin(Login $event): void
    {
        // Only log if it's a tenant user (using web guard) and tenancy is initialized
        if ($event->guard === 'web' && tenancy()->initialized) {
            $this->recordAuditLog->execute(
                action: 'auth.login',
                resource: 'users',
                resourceId: (string) $event->user->id
            );
        }
    }

    public function handleUserLogout(Logout $event): void
    {
        if ($event->guard === 'web' && $event->user && tenancy()->initialized) {
            $this->recordAuditLog->execute(
                action: 'auth.logout',
                resource: 'users',
                resourceId: (string) $event->user->id
            );
        }
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleUserLogin',
            Logout::class => 'handleUserLogout',
        ];
    }
}
