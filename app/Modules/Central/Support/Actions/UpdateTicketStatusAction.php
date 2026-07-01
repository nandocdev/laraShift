<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Actions;

use App\Modules\Central\Support\Models\SupportTicket;

final readonly class UpdateTicketStatusAction
{
    public function execute(SupportTicket $ticket, string $status, ?string $note = null): SupportTicket
    {
        $data = ['status' => $status];

        if ($status === 'resolved') {
            $data['resolved_at'] = now();
        }

        if ($status === 'closed') {
            $data['closed_at'] = now();
        }

        $ticket->update($data);

        activity('support')
            ->performedOn($ticket)
            ->withProperties([
                'status' => $status,
                'note' => $note,
                'actor' => auth('central')->id(),
            ])
            ->log("ticket_status_{$status}");

        return $ticket->fresh();
    }
}
