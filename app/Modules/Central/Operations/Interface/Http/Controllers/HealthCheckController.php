<?php

declare(strict_types=1);

namespace App\Modules\Central\Operations\Interface\Http\Controllers;

use App\Modules\Central\Operations\Infrastructure\Horizon\HorizonQueueResolver;
use App\Modules\Platform\Foundation\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class HealthCheckController extends Controller
{
    /**
     * GET /central/health
     * Monitors system dependencies.
     */
    public function __invoke(Request $request): JsonResponse
    {
        // IP Restriction if configured
        $allowedIps = config('infrastructure.health.allowed_ips', []);
        if (! empty($allowedIps) && ! in_array($request->ip(), $allowedIps, true)) {
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
            Log::warning('health.database.fail', ['ip' => request()->ip(), 'error' => $e->getMessage()]);

            return ['status' => 'fail', 'message' => 'Database unreachable'];
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
                    'message' => 'PHP Extension "phpredis" is missing. Install it or switch to "predis".',
                ];
            }

            Redis::connection()->ping();

            return ['status' => 'pass', 'message' => 'Connected'];
        } catch (\Exception $e) {
            Log::warning('health.redis.fail', ['ip' => request()->ip(), 'error' => $e->getMessage()]);

            return ['status' => 'fail', 'message' => 'Redis unreachable'];
        }
    }

    protected function checkQueue(): array
    {
        try {
            $queues = HorizonQueueResolver::resolve();
            $size = collect($queues)->sum(fn (string $queue) => Queue::size($queue));
            $failedCount = 0;
            try {
                $failedCount = DB::table('failed_jobs')->count();
            } catch (\Throwable $e) {
                // Table may not exist in testing
            }

            $status = 'pass';
            $message = 'Healthy';
            if ($size > 1000) {
                $status = 'warn';
                $message = 'Queue deep';
            }
            if ($failedCount > 100) {
                $status = 'warn';
                $message = $failedCount > 100 && $size > 1000 ? 'Queue deep + many failed jobs' : ($failedCount > 100 ? 'Many failed jobs' : $message);
            }

            return [
                'status' => $status,
                'size' => $size,
                'failed_jobs' => $failedCount,
                'message' => $message,
            ];
        } catch (\Exception $e) {
            Log::warning('health.queue.fail', ['ip' => request()->ip(), 'error' => $e->getMessage()]);

            return ['status' => 'fail', 'message' => 'Queue unreachable'];
        }
    }
}
