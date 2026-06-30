<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Actions;

use App\Modules\Central\Support\Models\SupportTicket;

final readonly class AssignTicketAction
{
    public function execute(SupportTicket $ticket, string $assigneeId): SupportTicket
    {
        $ticket->update([
            'assigned_to' => $assigneeId,
            'assigned_at' => now(),
            'status' => $ticket->status === 'open' ? 'in_progress' : $ticket->status,
        ]);

        activity('support')
            ->performedOn($ticket)
            ->withProperties([
                'assigned_to' => $assigneeId,
                'actor' => auth('central')->id(),
            ])
            ->log('ticket_assigned');

        return $ticket->fresh();
    }
}
