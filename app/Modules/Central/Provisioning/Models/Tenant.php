<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Models;

use App\Modules\Platform\Contracts\TenantContract;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantContract, TenantWithDatabase
{
    use HasDatabase, HasDomains, HasUuids, Notifiable, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $casts = [
        'maintenance_mode' => 'boolean',
        'read_only' => 'boolean',
        'archived_at' => 'datetime',
        'suspended_at' => 'datetime',
    ];

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

    public function getQuotaLimit(string $metric): int
    {
        // No plans: no limits enforced. QuotaManager counters still track usage.
        return -1;
    }

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'slug',
            'name',
            'email', // Tenant owner email
            'status',
            'suspended_at',
            'maintenance_mode',
            'read_only',
            'archived_at',
        ];
    }
}
