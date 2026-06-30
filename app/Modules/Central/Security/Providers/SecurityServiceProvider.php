<?php

declare(strict_types=1);

namespace App\Modules\Central\Security\Providers;

use App\Modules\Central\Security\Livewire\SecurityPolicies;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class SecurityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../UI', 'security');

        $this->app->booted(function () {
            Route::middleware(['web', 'auth:central'])
                ->group(function () {
                    Route::get('/central/security/policies', SecurityPolicies::class)
                        ->name('central.security.policies');
                });
        });

        Livewire::component('security-policies', SecurityPolicies::class);
    }
}
