<?php

declare(strict_types=1);

namespace App\Modules\Central\Infrastructure\Http\Controllers;

use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Queue;

class HealthCheckController extends Controller
{
    /**
     * GET /central/health
     * Monitors system dependencies.
     */
    public function __invoke(): JsonResponse
    {
        $status = 'healthy';
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'queue' => $this->checkQueue(),
        ];

        foreach ($checks as $check) {
            if ($check['status'] === 'fail') {
                $status = 'degraded';
                break;
            }
        }

        return response()->json([
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $status === 'healthy' ? 200 : 503);
    }

    protected function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'pass', 'message' => 'Connected'];
        } catch (\Exception $e) {
            return ['status' => 'fail', 'message' => $e->getMessage()];
        }
    }

    protected function checkRedis(): array
    {
        try {
            if (! class_exists('Redis')) {
                return ['status' => 'fail', 'message' => 'Redis extension not installed (php-redis)'];
            }

            Redis::connection()->ping();
            return ['status' => 'pass', 'message' => 'Pings'];
        } catch (\Exception $e) {
            return ['status' => 'fail', 'message' => $e->getMessage()];
        }
    }

    protected function checkQueue(): array
    {
        try {
            // Check default queue size as a health indicator
            $size = Queue::size();
            return [
                'status' => $size > 1000 ? 'warn' : 'pass', 
                'size' => $size,
                'message' => $size > 1000 ? 'Queue deep' : 'Healthy'
            ];
        } catch (\Exception $e) {
            return ['status' => 'fail', 'message' => $e->getMessage()];
        }
    }
}
