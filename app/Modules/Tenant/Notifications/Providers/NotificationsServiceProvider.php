<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Notifications\Providers;

use App\Modules\Tenant\Notifications\Livewire\ManageNotificationTemplates;
use App\Modules\Tenant\Notifications\Livewire\NotificationPreferences;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class NotificationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../UI', 'notifications');

        Livewire::component('tenant-manage-notification-templates', ManageNotificationTemplates::class);
        Livewire::component('tenant-notification-preferences', NotificationPreferences::class);
    }
}
