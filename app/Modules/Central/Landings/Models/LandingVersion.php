<?php

declare(strict_types=1);

namespace App\Modules\Central\Landings\Models;

use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Shared\Tenancy\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandingVersion extends Model
{
    use HasUuids, BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'landing_id',
        'tenant_id',
        'blocks_snapshot',
        'theme_snapshot',
        'published_by',
        'created_at',
    ];

    protected $casts = [
        'blocks_snapshot' => 'array',
        'theme_snapshot' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * The parent landing page.
     */
    public function landing(): BelongsTo
    {
        return $this->belongsTo(Landing::class);
    }

    /**
     * The central user who published this version.
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(CentralUser::class, 'published_by');
    }
}
