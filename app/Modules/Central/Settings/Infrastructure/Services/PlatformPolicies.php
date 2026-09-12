<?php

declare(strict_types=1);

namespace App\Modules\Central\Settings\Infrastructure\Services;

/**
 * Typed accessors over the central_settings store for platform
 * policies (RF5.3). Every value falls back to its config default,
 * so a fresh install behaves exactly like .env-only configuration.
 */
final class PlatformPolicies
{
    public const FRAUD_THRESHOLD = 'fraud.quarantine_threshold';

    public const SECOPS_EMAIL = 'fraud.secops_email';

    public const STALE_MINUTES = 'provisioning.stale_minutes';

    public static function fraudThreshold(): int
    {
        return max(1, (int) CentralBranding::get(self::FRAUD_THRESHOLD, config('fraud.quarantine_threshold', 80)));
    }

    public static function secopsEmail(): ?string
    {
        $value = CentralBranding::get(self::SECOPS_EMAIL, config('fraud.secops_email'));

        return is_string($value) && $value !== '' ? $value : null;
    }

    public static function staleProvisioningMinutes(): int
    {
        return max(1, (int) CentralBranding::get(self::STALE_MINUTES, config('provisioning.stale_provisioning_minutes', 30)));
    }

    public static function set(string $key, mixed $value, string $type = 'string'): void
    {
        CentralBranding::set($key, $value, $type);
    }
}
