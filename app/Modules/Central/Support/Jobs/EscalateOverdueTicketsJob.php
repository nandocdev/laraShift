<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Jobs;

use App\Modules\Central\Support\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EscalateOverdueTicketsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $overdue = SupportTicket::whereIn('status', ['open', 'in_progress'])
            ->whereNotNull('sla_breach_at')
            ->where('sla_breach_at', '<', now())
            ->get();

        foreach ($overdue as $ticket) {
            $oldStatus = $ticket->status;

            $ticket->update(['status' => 'escalated']);

            activity('support')
                ->performedOn($ticket)
                ->withProperties([
                    'old_status' => $oldStatus,
                    'new_status' => 'escalated',
                    'sla_breach_at' => $ticket->sla_breach_at->toDateTimeString(),
                ])
                ->log('ticket_escalated_sla');

            Log::warning('Ticket escalated due to SLA breach', [
                'ticket_id' => $ticket->id,
                'tenant_id' => $ticket->tenant_id,
                'priority' => $ticket->priority,
                'sla_breach_at' => $ticket->sla_breach_at->toDateTimeString(),
            ]);
        }
    }
}
