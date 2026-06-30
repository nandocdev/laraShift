<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Providers;

use App\Modules\Central\Support\Livewire\BroadcastCenter;
use App\Modules\Central\Support\Livewire\CreateTicket;
use App\Modules\Central\Support\Livewire\GlobalAnnouncements;
use App\Modules\Central\Support\Livewire\ManageTicket;
use App\Modules\Central\Support\Livewire\TenantSupportBitacora;
use App\Modules\Central\Support\Livewire\TicketList;
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
                    Route::get('/central/support/tickets', TicketList::class)
                        ->name('central.support.tickets');
                    Route::get('/central/support/tickets/create', CreateTicket::class)
                        ->name('central.support.tickets.create');
                    Route::get('/central/support/tickets/{ticket}', ManageTicket::class)
                        ->name('central.support.tickets.show');
                });
        });

        Livewire::component('support-broadcast-center', BroadcastCenter::class);
        Livewire::component('tenant-support-bitacora', TenantSupportBitacora::class);
        Livewire::component('global-announcements', GlobalAnnouncements::class);
        Livewire::component('ticket-list', TicketList::class);
        Livewire::component('manage-ticket', ManageTicket::class);
        Livewire::component('create-ticket', CreateTicket::class);
    }
}
