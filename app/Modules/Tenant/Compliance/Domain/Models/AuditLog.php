<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Compliance\Domain\Models;

use App\Modules\Platform\Tenancy\Domain\Concerns\BelongsToTenant;
use App\Modules\Tenant\Access\Domain\Models\User;
use App\Modules\Tenant\Compliance\Domain\Enums\AuditAction;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'tenant_audit_logs';

    protected $fillable = [
        'id',
        'tenant_id',
        'user_id',
        'action',
        'resource',
        'resource_id',
        'metadata',
        'ip',
    ];

    protected $casts = [
        'metadata' => 'array',
        'action' => AuditAction::class,
    ];

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        /** @var class-string<Model> $userModel */
        $userModel = config('compliance.user_model', config('auth.providers.users.model', User::class));

        return $this->belongsTo($userModel, 'user_id');
    }
}
