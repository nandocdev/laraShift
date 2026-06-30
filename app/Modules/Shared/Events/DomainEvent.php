<?php

declare(strict_types=1);

namespace App\Modules\Shared\Events;

use App\Modules\Shared\ValueObjects\Timestamped;
use App\Modules\Shared\ValueObjects\Uuid;
use DateTimeImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

abstract class DomainEvent
{
    use Dispatchable, SerializesModels;

    public readonly string $eventId;

    public readonly string $eventType;

    public readonly int $version;

    public readonly string $occurredAt;

    public ?string $tenantId = null;

    public ?string $correlationId = null;

    public ?string $causerId = null;

    public ?string $causerType = null;

    /**
     * @param int $version Event schema version (semantic: v1, v2, etc.)
     */
    public function __construct(
        int $version = 1,
        ?string $eventId = null,
        ?string $occurredAt = null,
    ) {
        $this->eventId = $eventId ?? Uuid::generate()->value();
        $this->eventType = static::eventType();
        $this->version = $version;
        $this->occurredAt = $occurredAt ?? Timestamped::now()->format();
    }

    /**
     * The unique event type identifier (e.g. "tenant.provisioned").
     * Override in concrete events for semantic naming.
     */
    public static function eventType(): string
    {
        $class = static::class;

        return Str::snake(class_basename($class));
    }

    /**
     * The event version string for documentation (e.g. "v1").
     */
    public function versionString(): string
    {
        return "v{$this->version}";
    }

    /**
     * Serialize event to array for persistence.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        $data = [];

        foreach (get_object_vars($this) as $key => $value) {
            if (in_array($key, ['eventId', 'eventType', 'version', 'occurredAt', 'tenantId', 'correlationId', 'causerId', 'causerType'], true)) {
                continue;
            }

            $data[$key] = $value;
        }

        return $data;
    }

    /**
     * Create envelope array for the outbox.
     *
     * @return array<string, mixed>
     */
    public function toEnvelope(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_type' => $this->eventType,
            'version' => $this->version,
            'tenant_id' => $this->tenantId,
            'correlation_id' => $this->correlationId,
            'causer_id' => $this->causerId,
            'causer_type' => $this->causerType,
            'occurred_at' => $this->occurredAt,
            'payload' => $this->toPayload(),
        ];
    }
}
