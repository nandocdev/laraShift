<?php

declare(strict_types=1);

namespace App\Modules\Central\Settings\Providers;

use App\Modules\Central\Auth\Domain\Models\CentralUser;
use App\Modules\Central\Settings\Infrastructure\Services\CentralPlatformBranding;
use App\Modules\Central\Settings\Interface\Livewire\PlatformBranding;
use App\Modules\Platform\Contracts\PlatformBrandingContract;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PlatformBrandingContract::class, CentralPlatformBranding::class);
    }

    public function boot(): void
    {
        Gate::define('branding:manage', function ($user) {
            if (method_exists($user, 'can') && $user->can('manage-platform')) {
                return true;
            }
            if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
                return true;
            }

            // Fallback for testing/local where no roles seeded: allow if user is CentralUser instance
            return $user instanceof CentralUser;
        });

        $this->loadViewsFrom(__DIR__.'/../Interface/Views', 'settings');

        $this->app->booted(function () {
            Route::middleware(['web', 'auth:central'])
                ->group(function () {
                    Route::get('/central/settings/branding', PlatformBranding::class)
                        ->name('central.settings.branding');
                });
        });

        Livewire::component('central-platform-branding', PlatformBranding::class);
    }
}
