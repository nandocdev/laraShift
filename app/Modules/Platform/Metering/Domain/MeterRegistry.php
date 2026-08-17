<?php

declare(strict_types=1);

namespace App\Modules\Platform\Metering\Domain;

use App\Modules\Platform\Metering\Domain\Exceptions\MeterNotFoundException;

/**
 * Registry of every meter defined in config/metering.php.
 */
final readonly class MeterRegistry
{
    /** @var array<string, Meter> */
    public array $meters;

    /**
     * @param  array<string, array<string, mixed>>  $meters
     */
    public function __construct(array $meters)
    {
        $built = [];

        foreach ($meters as $key => $config) {
            $built[(string) $key] = Meter::fromConfig((string) $key, $config);
        }

        $this->meters = $built;
    }

    /**
     * @return array<string, Meter>
     */
    public function all(): array
    {
        return $this->meters;
    }

    public function get(string $key): Meter
    {
        return $this->meters[$key]
            ?? throw new MeterNotFoundException($key);
    }

    public function has(string $key): bool
    {
        return isset($this->meters[$key]);
    }
}
