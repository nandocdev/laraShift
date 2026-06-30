<?php

namespace App\Providers;

use App\Modules\Shared\Events\Catalog\DomainEventCatalog;
use App\Modules\Shared\Events\Contracts\EventPublisher;
use App\Modules\Shared\Events\Outbox\OutboxEventPublisher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(EventPublisher::class, OutboxEventPublisher::class);

        $this->mergeConfigFrom(__DIR__ . '/../../config/events.php', 'events');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        config([
            'events.map' => array_merge(
                config('events.map', []),
                DomainEventCatalog::toConfigMap(),
            ),
        ]);

        if (app()->isProduction()) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        \Illuminate\Support\Facades\Gate::policy(
            \App\Modules\Tenant\Settings\Models\TenantSetting::class,
            \App\Modules\Tenant\Settings\Policies\TenantSettingPolicy::class
        );

        $this->configureDefaults();

        \Illuminate\Database\Eloquent\Factories\Factory::guessFactoryNamesUsing(function (string $modelName) {
            if (str_starts_with($modelName, 'App\\Modules\\')) {
                return str_replace('\\Models\\', '\\Database\\Factories\\', $modelName) . 'Factory';
            }
            return 'Database\\Factories\\' . class_basename($modelName) . 'Factory';
        });

        \Illuminate\Support\Facades\Blade::anonymousComponentPath(resource_path('views/layouts'), 'layouts');

        \Livewire\Livewire::setUpdateRoute(function ($handle) {
            $path = class_exists(\Livewire\Mechanisms\HandleRequests\EndpointResolver::class) 
                ? \Livewire\Mechanisms\HandleRequests\EndpointResolver::updatePath() 
                : '/livewire/update';

            return \Illuminate\Support\Facades\Route::post($path, $handle)
                ->middleware([
                    'web',
                    'universal',
                    \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
                ]);
        });

        // Inject tenancy middleware into file preview route so it resolves the tenant disk.
        \Livewire\Features\SupportFileUploads\FilePreviewController::$middleware = [
            'web',
            'universal',
            \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
        ];
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
