<?php

declare(strict_types=1);

namespace App\Modules\Central\Settings\Support;

use App\Modules\Central\Settings\Models\CentralSetting;
use Illuminate\Support\Facades\Cache;

class CentralBranding
{
    private const int CACHE_TTL = 86400;

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("central_setting_{$key}", self::CACHE_TTL, function () use ($key, $default) {
            $setting = CentralSetting::find($key);

            return $setting ? self::castValue($setting->value, $setting->type) : $default;
        });
    }

    public static function set(string $key, mixed $value, string $type = 'string'): void
    {
        CentralSetting::updateOrCreate(
            ['key' => $key],
            ['value' => (string) $value, 'type' => $type]
        );
        Cache::forget("central_setting_{$key}");
    }

    public static function platformName(): string
    {
        return self::get('platform_name', config('app.name', 'LaraShift'));
    }

    public static function primaryColor(): string
    {
        return self::get('primary_color', '#000000');
    }

    public static function logoUrl(): ?string
    {
        return self::get('logo_url');
    }

    protected static function castValue(string $value, string $type): mixed
    {
        return match ($type) {
            'bool', 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'int', 'integer' => (int) $value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }
}
