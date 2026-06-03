<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Actions\CreateTenantAction;
use App\Modules\Central\Provisioning\DTOs\CreateTenantData;
use App\Modules\Central\Provisioning\Notifications\WelcomeTenantNotification;
use App\Modules\Tenant\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('sends a welcome email to the initial admin user', function () {
    Notification::fake();

    $action = app(CreateTenantAction::class);
    $data = new CreateTenantData(
        name: 'Welcome Corp',
        slug: 'welcome',
        email: 'admin@welcome.com',
        plan_id: 'free',
    );

    $tenant = $action->execute($data);

    // Initial admin is created in tenant context
    $tenant->run(function () {
        $user = User::where('email', 'admin@welcome.com')->first();
        expect($user)->not->toBeNull();
        
        Notification::assertSentTo(
            $user, 
            WelcomeTenantNotification::class
        );
    });
});
