<?php

declare(strict_types=1);

namespace App\Modules\Platform\Observability\Health;

use Illuminate\Support\Facades\DB;

class HealthChecker
{
    public static function checkDatabaseConnection(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
