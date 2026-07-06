<?php

declare(strict_types=1);

namespace App\Modules\Platform\Security\Hmac;

class HmacSigner
{
    public static function hash(string $data, string $key): string
    {
        return hash_hmac('sha256', $data, $key);
    }

    public static function verify(string $data, string $key, string $signature): bool
    {
        return hash_equals(hash_hmac('sha256', $data, $key), $signature);
    }
}
