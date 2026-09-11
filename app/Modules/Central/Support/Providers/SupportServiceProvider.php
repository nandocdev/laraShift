<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Providers;

use App\Modules\Central\Support\Infrastructure\Console\DispatchDueBroadcastsCommand;
use App\Modules\Central\Support\Livewire\BroadcastCenter;
use App\Modules\Central\Support\Livewire\CentralAuditLog;
use App\Modules\Central\Support\Livewire\GlobalAnnouncements;
use App\Modules\Central\Support\Livewire\TenantSupportBitacora;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class SupportServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('support:impersonate', function ($user) {
            if (method_exists($user, 'can') && $user->can('support-impersonate')) {
                return true;
            }
            if (method_exists($user, 'hasRole') && ($user->hasRole('admin') || $user->hasRole('support'))) {
                return true;
            }

            return false;
        });

        $this->loadViewsFrom(__DIR__.'/../UI', 'support');

        $this->app->booted(function () {
            Route::middleware(['web', 'auth:central'])
                ->group(function () {
                    Route::get('/central/support/broadcasts', BroadcastCenter::class)
                        ->name('central.support.broadcasts');
                    Route::get('/central/audit-log', CentralAuditLog::class)
                        ->name('central.audit.log');
                });
        });

        Livewire::component('support-broadcast-center', BroadcastCenter::class);
        Livewire::component('central-audit-log', CentralAuditLog::class);
        Livewire::component('tenant-support-bitacora', TenantSupportBitacora::class);
        Livewire::component('global-announcements', GlobalAnnouncements::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                DispatchDueBroadcastsCommand::class,
            ]);
        }
    }
}
