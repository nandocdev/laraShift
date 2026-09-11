<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Experience\Providers;

use App\Modules\Platform\Contracts\TenantBrandResolverContract;
use App\Modules\Tenant\Experience\Application\Services\TenantBrandResolver;
use App\Modules\Tenant\Experience\Domain\Models\Landing;
use App\Modules\Tenant\Experience\Interface\Livewire\BrandingSettings;
use App\Modules\Tenant\Experience\Interface\Livewire\LandingBuilder;
use App\Modules\Tenant\Experience\Interface\Livewire\LocalizationSettings;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class ExperienceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TenantBrandResolverContract::class, TenantBrandResolver::class);
    }

    public function boot(): void
    {
        // Share view namespace 'settings-tenant' to preserve view resolution compatibility
        $this->loadViewsFrom(__DIR__.'/../Interface/Views', 'settings-tenant');
        $this->loadViewsFrom(__DIR__.'/../Interface/Views/landings', 'landings');

        // Provide tenant-owned data to the Platform sidebar without coupling Platform to this module
        View::composer(['ui::layouts.app.sidebar', 'layouts.app.sidebar'], function ($view): void {
            $entry = null;

            if (tenancy()->initialized) {
                $landing = Landing::query()
                    ->where('tenant_id', tenant('id'))
                    ->where('slug', 'saas-landing')
                    ->first();

                $entry = $landing ? [
                    'url' => route('tenant.landings.builder', $landing),
                    'label' => __('Landing Page'),
                ] : null;
            }

            $view->with('sidebarLandingEntry', $entry);
        });

        Blade::component('landings::layouts.landing', 'landing-layout');
        Livewire::component('landing-builder', LandingBuilder::class);

        Livewire::component('tenant-branding-settings', BrandingSettings::class);
        Livewire::component('tenant-localization-settings', LocalizationSettings::class);
    }
}
