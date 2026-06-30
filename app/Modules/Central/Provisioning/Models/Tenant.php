<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Models;

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Features\Models\Concerns\HasFeatures;
use App\Modules\Central\Features\Models\Concerns\HasQuotas;
use App\Modules\Shared\Contracts\TenantContract;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantContract, TenantWithDatabase
{
    use Billable, HasDatabase, HasDomains, HasFeatures, HasQuotas, HasUuids, Notifiable, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $casts = [
        'maintenance_mode' => 'boolean',
        'read_only' => 'boolean',
        'archived_at' => 'datetime',
        'suspended_at' => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id', 'slug');
    }

    public function getId(): string|int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name ?? 'Unknown';
    }

    public function getDomain(): string
    {
        return $this->domains->first()?->domain ?? '';
    }

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'slug',
            'name',
            'email',
            'plan_id',
            'status',
            'suspended_at',
            'maintenance_mode',
            'read_only',
            'provisioning_status',
            'archived_at',
            'data',
        ];
    }
}
