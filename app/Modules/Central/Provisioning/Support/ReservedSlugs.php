<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Support;

class ReservedSlugs
{
    private static array $staticList = [
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

    /**
     * Full list of reserved slugs, including central domains from config.
     * Esto evita que un tenant registre un slug como 'localhost' que
     * generaria un dominio 'localhost.localhost'.
     */
    public static function all(): array
    {
        $domains = (array) config('tenancy.central_domains', []);

        $domainSlugs = array_map(function (string $domain): string {
            return strtolower(explode('.', $domain)[0]);
        }, $domains);

        return array_unique(array_merge(self::$staticList, $domainSlugs));
    }

    public static function isReserved(string $slug): bool
    {
        return in_array(strtolower($slug), self::all());
    }

    public static function regex(): string
    {
        return '/^('.implode('|', self::all()).')$/i';
    }
}
