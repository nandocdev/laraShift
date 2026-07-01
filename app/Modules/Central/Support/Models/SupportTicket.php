<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Models;

use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    use HasUuids;

    public function getConnectionName()
    {
        return config('tenancy.database.central_connection', 'central');
    }

    protected $fillable = [
        'id',
        'tenant_id',
        'subject',
        'description',
        'status',
        'priority',
        'assigned_to',
        'assigned_at',
        'resolved_at',
        'closed_at',
        'sla_breach_at',
        'created_by',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'sla_breach_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(CentralUser::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(CentralUser::class, 'created_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class, 'ticket_id');
    }

    public function isOverdueSla(): bool
    {
        return $this->sla_breach_at && $this->sla_breach_at->isPast();
    }
}
