<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Experience\Domain\Models;

use App\Modules\Platform\Tenancy\Domain\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Landing extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'id',
        'tenant_id',
        'slug',
        'title',
        'theme',
        'blocks',
        'status',
        'published_html',
        'published_at',
    ];

    protected $casts = [
        'theme' => 'array',
        'blocks' => 'array',
        'published_at' => 'datetime',
    ];

    /**
     * Historical versions/snapshots of this landing.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(LandingVersion::class);
    }
}
