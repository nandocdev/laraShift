<?php

declare(strict_types=1);

namespace App\Modules\Central\Features\Models;

use App\Modules\Central\Billing\Models\Plan;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Feature extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'id',
        'key',
        'name',
        'description',
        'module',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_features');
    }
}
