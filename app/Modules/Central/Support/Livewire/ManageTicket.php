<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Livewire;

use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Central\Support\Actions\AddTicketMessageAction;
use App\Modules\Central\Support\Actions\AssignTicketAction;
use App\Modules\Central\Support\Actions\QueryTenantAuditLogsAction;
use App\Modules\Central\Support\Actions\UpdateTicketStatusAction;
use App\Modules\Central\Support\DTOs\AddTicketMessageData;
use App\Modules\Central\Support\Models\SupportTicket;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.central')]
class ManageTicket extends Component
{
    public SupportTicket $ticket;

    public string $newMessage = '';

    public bool $isInternal = true;

    public function mount(SupportTicket $ticket): void
    {
        $this->ticket = $ticket->load(['messages.author', 'tenant', 'assignedTo', 'creator']);
    }

    public function addMessage(AddTicketMessageAction $action): void
    {
        $this->validate(['newMessage' => 'required|string|min:1']);

        $data = new AddTicketMessageData(
            content: $this->newMessage,
            isInternal: $this->isInternal,
        );

        $action->execute($this->ticket, $data);

        $this->reset('newMessage');
        $this->ticket->refresh();
        $this->ticket->load('messages.author');
    }

    public function changeStatus(string $status, UpdateTicketStatusAction $action): void
    {
        $action->execute($this->ticket, $status);
        $this->ticket->refresh();
    }

    public function assign(string $assigneeId, AssignTicketAction $action): void
    {
        $action->execute($this->ticket, $assigneeId);
        $this->ticket->refresh();
    }

    public function render(): View
    {
        $tenant = Tenant::find($this->ticket->tenant_id);

        $auditLogs = [];
        if ($tenant) {
            $auditLogs = app(QueryTenantAuditLogsAction::class)->query($tenant, ['limit' => 20]);
        }

        return view('support::pages.manage-ticket', [
            'ticket' => $this->ticket,
            'auditLogs' => $auditLogs,
            'agents' => CentralUser::where('is_global_admin', true)->get(),
            'statuses' => ['open', 'in_progress', 'escalated', 'resolved', 'closed'],
        ]);
    }
}
