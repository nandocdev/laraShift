<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class SupportServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../UI', 'support');

        $this->app->booted(function () {
            \Illuminate\Support\Facades\Route::middleware(['web', 'auth:central'])
                ->group(function () {
                    \Illuminate\Support\Facades\Route::get('/central/support/broadcasts', \App\Modules\Central\Support\Livewire\BroadcastCenter::class)
                        ->name('central.support.broadcasts');
                });
        });

        \Livewire\Livewire::component('support-broadcast-center', \App\Modules\Central\Support\Livewire\BroadcastCenter::class);
    }
}
