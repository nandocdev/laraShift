<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Experience\Domain\Models;

use App\Modules\Platform\Contracts\CentralUserContract;
use App\Modules\Platform\Tenancy\Domain\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandingVersion extends Model
{
    use BelongsToTenant, HasUuids;

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
     *
     * Uses CentralUserContract to avoid direct dependency on Central\Auth.
     * The concrete model is resolved via the container binding.
     */
    public function publisher(): BelongsTo
    {
        $model = app(CentralUserContract::class);

        return $this->belongsTo(get_class($model), 'published_by');
    }
}
