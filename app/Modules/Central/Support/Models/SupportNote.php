<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Models;

use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportNote extends Model
{
    use HasUuids;

    protected $connection = 'central';

    protected $fillable = [
        'id',
        'tenant_id',
        'author_id',
        'content',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(CentralUser::class, 'author_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
