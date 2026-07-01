<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Actions;

use App\Modules\Central\Support\DTOs\CreateTicketData;
use App\Modules\Central\Support\Models\SupportTicket;
use Illuminate\Support\Str;

final readonly class CreateTicketAction
{
    public function execute(CreateTicketData $data): SupportTicket
    {
        $slaHours = match ($data->priority) {
            'critical' => 4,
            'high' => 8,
            'medium' => 24,
            default => 48,
        };

        return SupportTicket::create([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $data->tenantId,
            'subject' => $data->subject,
            'description' => $data->description,
            'status' => 'open',
            'priority' => $data->priority,
            'assigned_to' => $data->assignedTo,
            'assigned_at' => $data->assignedTo ? now() : null,
            'sla_breach_at' => now()->addHours($slaHours),
            'created_by' => auth('central')->id(),
        ]);
    }
}
