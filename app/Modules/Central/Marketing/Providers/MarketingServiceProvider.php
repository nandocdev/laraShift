<?php

declare(strict_types=1);

namespace App\Modules\Central\Marketing\Providers;

use App\Modules\Central\Marketing\Livewire\LandingPage;
use App\Modules\Central\Marketing\Livewire\ManageLegalDocuments;
use App\Modules\Central\Marketing\Livewire\RegisterTenant;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class MarketingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../UI', 'marketing');

        $this->app->booted(function () {
            Route::middleware(['web', 'auth:central'])
                ->group(function () {
                    Route::get('/central/legal', ManageLegalDocuments::class)
                        ->name('central.legal.documents');
                });
        });

        Livewire::component('marketing-landing-page', LandingPage::class);
        Livewire::component('marketing-register-tenant', RegisterTenant::class);
        Livewire::component('manage-legal-documents', ManageLegalDocuments::class);
    }
}
