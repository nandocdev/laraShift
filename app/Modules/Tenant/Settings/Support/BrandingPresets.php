<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Settings\Support;

class BrandingPresets
{
    public static function all(): array
    {
        return [
            'saas' => [
                'name' => 'SaaS Blue',
                'primary' => '#4f46e5',
                'secondary' => '#1e293b',
                'font_heading' => 'Inter',
                'font_body' => 'Inter',
            ],
            'corporate' => [
                'name' => 'Corporate Slate',
                'primary' => '#0f172a',
                'secondary' => '#475569',
                'font_heading' => 'Montserrat',
                'font_body' => 'Inter',
            ],
            'startup' => [
                'name' => 'Startup Emerald',
                'primary' => '#10b981',
                'secondary' => '#111827',
                'font_heading' => 'Plus Jakarta Sans',
                'font_body' => 'Inter',
            ],
            'creative' => [
                'name' => 'Creative Rose',
                'primary' => '#e11d48',
                'secondary' => '#171717',
                'font_heading' => 'Playfair Display',
                'font_body' => 'Lato',
            ],
            'custom' => [
                'name' => 'Custom Colors',
                'primary' => null,
                'secondary' => '#1e293b',
                'font_heading' => 'Inter',
                'font_body' => 'Inter',
            ],
        ];
    }

    public static function get(string $key): array
    {
        return self::all()[$key] ?? self::all()['saas'];
    }
}
