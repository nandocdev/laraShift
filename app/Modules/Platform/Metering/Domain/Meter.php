<?php

declare(strict_types=1);

namespace App\Modules\Platform\Metering\Domain;

use App\Modules\Platform\Metering\Domain\Enums\AggregationType;
use Illuminate\Support\Str;

/**
 * Immutable description of a measurable meter.
 */
final readonly class Meter
{
    public function __construct(
        public string $key,
        public string $name,
        public string $unit,
        public AggregationType $aggregation,
        public bool $billable = false,
        public ?string $providerEventName = null,
    ) {}

    /**
     * Builds a Meter from a config/metering.php registry entry.
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(string $key, array $config): self
    {
        return new self(
            key: $key,
            name: (string) ($config['name'] ?? Str::headline($key)),
            unit: (string) ($config['unit'] ?? 'unit'),
            aggregation: AggregationType::from((string) ($config['aggregation'] ?? AggregationType::Sum->value)),
            billable: (bool) ($config['billable'] ?? false),
            providerEventName: isset($config['provider_event_name'])
                ? (string) $config['provider_event_name']
                : null,
        );
    }
}
