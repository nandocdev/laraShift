<?php

declare(strict_types=1);

namespace App\Modules\Central\Features\Exceptions;

class FeatureOverrideException extends \RuntimeException
{
    public static function notFound(string $overrideId): self
    {
        return new self("Feature override [{$overrideId}] not found.");
    }

    public static function duplicate(string $featureKey): self
    {
        return new self("An override for feature [{$featureKey}] already exists for this tenant.");
    }
}
