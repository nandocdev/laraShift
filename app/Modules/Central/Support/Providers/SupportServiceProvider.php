<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Providers;

use App\Modules\Central\Support\Livewire\BroadcastCenter;
use App\Modules\Central\Support\Livewire\GlobalAnnouncements;
use App\Modules\Central\Support\Livewire\TenantSupportBitacora;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class SupportServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../UI', 'support');

        $this->app->booted(function () {
            Route::middleware(['web', 'auth:central'])
                ->group(function () {
                    Route::get('/central/support/broadcasts', BroadcastCenter::class)
                        ->name('central.support.broadcasts');
                });
        });

        Livewire::component('support-broadcast-center', BroadcastCenter::class);
        Livewire::component('tenant-support-bitacora', TenantSupportBitacora::class);
        Livewire::component('global-announcements', GlobalAnnouncements::class);
    }
}
