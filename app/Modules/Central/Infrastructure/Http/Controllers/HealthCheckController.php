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
    public function __invoke(\Illuminate\Http\Request $request): JsonResponse
    {
        // IP Restriction if configured
        $allowedIps = config('infrastructure.health.allowed_ips', []);
        if (!empty($allowedIps) && !in_array($request->ip(), $allowedIps, true)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

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
            // Check if the Redis class or the phpredis extension is actually available
            // to avoid fatal "Class Redis not found" errors
            if (! class_exists('Redis') && config('database.redis.client') === 'phpredis') {
                return [
                    'status' => 'fail', 
                    'message' => 'PHP Extension "phpredis" is missing. Install it or switch to "predis".'
                ];
            }

            Redis::connection()->ping();
            return ['status' => 'pass', 'message' => 'Connected'];
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
