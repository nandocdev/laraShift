<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Models;

use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Broadcast extends Model
{
    use HasUuids;

    public function getConnectionName()
    {
        return config('tenancy.database.central_connection', 'central');
    }

    protected $fillable = [
        'id',
        'created_by',
        'title',
        'body',
        'filter_type',
        'filter_value',
        'channels',
        'sent_at',
        'scheduled_at',
        'is_draft',
        'recipient_count',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'sent_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'is_draft' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(CentralUser::class, 'created_by');
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'broadcast_tenant', 'broadcast_id', 'tenant_id');
    }

    /**
     * Tenants alcanzados por la audiencia del broadcast. Definición única:
     * la usan el conteo, el job de envío y (en inverso) el banner del tenant.
     */
    public function recipients(): Builder
    {
        $query = Tenant::query();

        if ($this->filter_type === 'status' && $this->filter_value) {
            $query->where('status', $this->filter_value);
        }

        if ($this->filter_type === 'plan' && $this->filter_value) {
            $query->where('plan_id', $this->filter_value);
        }

        if ($this->filter_type === 'selected') {
            $query->whereIn('id', function ($nested) {
                $nested->select('tenant_id')
                    ->from('broadcast_tenant')
                    ->where('broadcast_id', $this->id);
            });
        }

        return $query;
    }

    /**
     * Broadcasts visibles para un tenant (banners enviados, no descartados
     * se filtran en el componente).
     */
    public function scopeVisibleToTenant(Builder $query, Tenant $tenant): Builder
    {
        return $query->whereNotNull('sent_at')
            ->whereJsonContains('channels', 'banner')
            ->where(function ($nested) use ($tenant) {
                $nested->where('filter_type', 'all')
                    ->orWhere(function ($q) use ($tenant) {
                        $q->where('filter_type', 'status')->where('filter_value', $tenant->status);
                    })
                    ->orWhere(function ($q) use ($tenant) {
                        $q->where('filter_type', 'plan')->where('filter_value', $tenant->plan_id);
                    })
                    ->orWhere(function ($q) use ($tenant) {
                        $q->where('filter_type', 'selected')->whereHas('tenants', fn ($t) => $t->where('tenants.id', $tenant->id));
                    });
            });
    }
}
