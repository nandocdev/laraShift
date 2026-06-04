<?php

declare(strict_types=1);

namespace App\Modules\Central\Landings\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactSubmission extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'landing_id',
        'tenant_id',
        'source_block',
        'email',
        'name',
        'form_data',
        'ip_address',
        'user_agent',
        'read_at',
    ];

    protected $casts = [
        'form_data' => 'array',
        'read_at' => 'datetime',
    ];

    /**
     * The landing page where this submission originated.
     */
    public function landing(): BelongsTo
    {
        return $this->belongsTo(Landing::class);
    }
}
