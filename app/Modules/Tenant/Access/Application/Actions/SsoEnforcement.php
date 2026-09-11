<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Access\Application\Actions;

use App\Modules\Tenant\Access\Domain\Models\SsoSetting;

final readonly class SsoEnforcement
{
    public function isEnforcedFor(string $email): bool
    {
        if (! function_exists('tenancy') || ! tenancy()->initialized) {
            return false;
        }

        $domain = substr(strrchr($email, '@') ?: '', 1);
        if ($domain === '') {
            return false;
        }

        $setting = SsoSetting::first();

        return $setting
            && $setting->is_forced
            && is_array($setting->enforced_domains)
            && in_array($domain, $setting->enforced_domains, true);
    }
}
