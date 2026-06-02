<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Support;

class ReservedSlugs
{
    public static array $list = [
        'admin',
        'api',
        'root',
        'support',
        'www',
        'mail',
        'dev',
        'stage',
        'prod',
        'central',
        'billing',
        'help',
        'status',
        'assets',
        'static',
        'cdn',
        'legal',
        'terms',
        'privacy',
    ];

    public static function isReserved(string $slug): bool
    {
        return in_array(strtolower($slug), self::$list);
    }

    public static function regex(): string
    {
        return '/^(' . implode('|', self::$list) . ')$/i';
    }
}
