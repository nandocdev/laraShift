<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Livewire;

use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Central\Support\Actions\CreateTicketAction;
use App\Modules\Central\Support\DTOs\CreateTicketData;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.central')]
class CreateTicket extends Component
{
    public string $tenantId = '';

    public string $subject = '';

    public string $description = '';

    public string $priority = 'medium';

    public string $assignedTo = '';

    public function save(CreateTicketAction $action): void
    {
        $this->validate([
            'tenantId' => 'required|exists:tenants,id',
            'subject' => 'required|string|max:255',
            'description' => 'required|string|min:10',
            'priority' => 'required|in:low,medium,high,critical',
            'assignedTo' => 'nullable|exists:central_users,id',
        ]);

        $data = new CreateTicketData(
            tenantId: $this->tenantId,
            subject: $this->subject,
            description: $this->description,
            priority: $this->priority,
            assignedTo: $this->assignedTo ?: null,
        );

        $ticket = $action->execute($data);

        session()->flash('status', __('Ticket created.'));

        $this->redirect(route('central.support.tickets.show', $ticket->id), navigate: true);
    }

    public function render(): View
    {
        return view('support::pages.create-ticket', [
            'tenants' => Tenant::whereNull('archived_at')->orderBy('name')->get(),
            'agents' => CentralUser::where('is_global_admin', true)->get(),
        ]);
    }
}
