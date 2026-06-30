<?php

declare(strict_types=1);

namespace App\Modules\Shared\Events\Outbox;

use App\Modules\Shared\Models\BaseModel;

final class OutboxEvent extends BaseModel
{
    protected $table = 'outbox_events';

    protected $fillable = [
        'id',
        'event_type',
        'version',
        'tenant_id',
        'correlation_id',
        'causer_id',
        'causer_type',
        'payload',
        'metadata',
        'status',
        'retry_count',
        'available_at',
        'published_at',
        'last_error_at',
        'last_error',
    ];

    protected $casts = [
        'version' => 'integer',
        'retry_count' => 'integer',
        'payload' => 'array',
        'metadata' => 'array',
        'available_at' => 'datetime',
        'published_at' => 'datetime',
        'last_error_at' => 'datetime',
    ];

    public function markPublished(): void
    {
        $this->update([
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'retry_count' => $this->retry_count + 1,
            'last_error' => $error,
            'last_error_at' => now(),
        ]);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('available_at')
                    ->orWhere('available_at', '<=', now());
            });
    }
}
