<?php

declare(strict_types=1);

namespace App\Modules\Shared\Events\Dlq;

use App\Modules\Shared\Models\BaseModel;

final class DeadLetterEvent extends BaseModel
{
    protected $table = 'dead_letter_events';

    protected $fillable = [
        'id',
        'outbox_event_id',
        'event_type',
        'version',
        'tenant_id',
        'correlation_id',
        'payload',
        'error_log',
        'retry_count',
        'max_retries',
        'status',
        'last_attempt_at',
        'next_retry_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'retry_count' => 'integer',
        'max_retries' => 'integer',
        'payload' => 'array',
        'error_log' => 'array',
        'last_attempt_at' => 'datetime',
        'next_retry_at' => 'datetime',
    ];

    public function markRetried(): void
    {
        $this->update([
            'retry_count' => $this->retry_count + 1,
            'last_attempt_at' => now(),
            'next_retry_at' => $this->calculateNextRetry(),
        ]);
    }

    public function markResolved(): void
    {
        $this->update(['status' => 'resolved']);
    }

    public function markFailed(string $error): void
    {
        $errors = $this->error_log ?? [];
        $errors[] = ['error' => $error, 'at' => now()->toIso8601String()];

        $this->update([
            'status' => 'failed',
            'retry_count' => $this->retry_count + 1,
            'error_log' => $errors,
            'last_attempt_at' => now(),
            'next_retry_at' => $this->retry_count >= $this->max_retries ? null : $this->calculateNextRetry(),
        ]);
    }

    public function isExhausted(): bool
    {
        return $this->retry_count >= $this->max_retries;
    }

    public function scopeRetryable($query)
    {
        return $query->where('status', 'failed')
            ->whereColumn('retry_count', '<', 'max_retries')
            ->where(function ($q) {
                $q->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            });
    }

    private function calculateNextRetry(): ?\DateTimeInterface
    {
        if ($this->isExhausted()) {
            return null;
        }

        $delay = match (true) {
            $this->retry_count < 3 => 60,        // 1 minute
            $this->retry_count < 5 => 300,       // 5 minutes
            default => 3600,                      // 1 hour
        };

        return now()->addSeconds($delay);
    }
}
