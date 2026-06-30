<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Http;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

final class HttpClient
{
    private const int DEFAULT_TIMEOUT = 30;
    private const int DEFAULT_MAX_RETRIES = 3;
    private const int DEFAULT_RETRY_DELAY_MS = 100;

    public function __construct(
        private readonly int $timeout = self::DEFAULT_TIMEOUT,
        private readonly int $maxRetries = self::DEFAULT_MAX_RETRIES,
        private readonly int $retryDelayMs = self::DEFAULT_RETRY_DELAY_MS,
    ) {}

    public function get(string $url, array $headers = []): Response
    {
        return $this->request()->get($url);
    }

    public function post(string $url, array $data = [], array $headers = []): Response
    {
        return $this->request()->post($url, $data);
    }

    public function put(string $url, array $data = [], array $headers = []): Response
    {
        return $this->request()->put($url, $data);
    }

    public function patch(string $url, array $data = [], array $headers = []): Response
    {
        return $this->request()->patch($url, $data);
    }

    public function delete(string $url, array $headers = []): Response
    {
        return $this->request()->delete($url);
    }

    private function request(): PendingRequest
    {
        return Http::timeout($this->timeout)
            ->withUserAgent('LaraShift/1.0')
            ->retry($this->maxRetries, $this->retryDelayMs, function (Throwable $e, PendingRequest $request) {
                if ($e instanceof \Illuminate\Http\Client\ConnectionException) {
                    return true;
                }

                if ($e instanceof \Illuminate\Http\Client\RequestException) {
                    return in_array($e->response->status(), [429, 500, 502, 503, 504], true);
                }

                return false;
            });
    }
}
