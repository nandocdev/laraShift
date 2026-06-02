<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use Billable, HasDatabase, HasDomains, Notifiable, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'maintenance_mode' => 'boolean',
        'read_only' => 'boolean',
        'archived_at' => 'datetime',
    ];

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'email', // Tenant owner email
            'plan_id',
            'status',
            'maintenance_mode',
            'read_only',
            'archived_at',
        ];
    }
}
