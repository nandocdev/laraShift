<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Actions;

use App\Modules\Central\Support\DTOs\AddTicketMessageData;
use App\Modules\Central\Support\Models\SupportTicket;
use App\Modules\Central\Support\Models\SupportTicketMessage;
use Illuminate\Support\Str;

final readonly class AddTicketMessageAction
{
    public function execute(SupportTicket $ticket, AddTicketMessageData $data): SupportTicketMessage
    {
        if ($ticket->status === 'closed') {
            throw new \RuntimeException(__('Cannot add messages to a closed ticket.'));
        }

        if ($ticket->status === 'open' || $ticket->status === 'escalated') {
            $ticket->update(['status' => 'in_progress']);
        }

        return SupportTicketMessage::create([
            'id' => Str::uuid()->toString(),
            'ticket_id' => $ticket->id,
            'author_id' => auth('central')->id(),
            'content' => $data->content,
            'is_internal' => $data->isInternal,
        ]);
    }
}
