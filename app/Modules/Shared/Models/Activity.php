<?php

declare(strict_types=1);

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

class Activity extends SpatieActivity
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';
}
