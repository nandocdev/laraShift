<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Infrastructure\Gateways\Dlocal;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class DlocalHttpClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $login,
        private readonly string $transKey,
        private readonly string $secretKey,
        private readonly int $retryTimes = 3,
        private readonly int $retrySleepMs = 100,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            baseUrl: config('dlocal.environment') === 'production'
                ? 'https://api.dlocal.com'
                : 'https://sandbox.dlocal.com',
            login: (string) config('dlocal.login'),
            transKey: (string) config('dlocal.trans_key'),
            secretKey: (string) config('dlocal.secret_key'),
        );
    }

    public function post(string $path, array $payload, ?string $idempotencyKey = null): array
    {
        return $this->send('post', $path, $payload, $idempotencyKey ?? (string) Str::uuid());
    }

    public function get(string $path): array
    {
        return $this->send('get', $path, []);
    }

    private function send(string $method, string $path, array $payload, ?string $idempotencyKey = null): array
    {
        // The body is signed: the exact same bytes must be transmitted.
        $body = $method === 'post' ? json_encode($payload, JSON_THROW_ON_ERROR) : '';

        // dLocal expects ISO-8601 UTC with milliseconds.
        $date = now()->utc()->format('Y-m-d\TH:i:s.v').'Z';

        $headers = [
            'X-Date' => $date,
            'X-Login' => $this->login,
            'X-Trans-Key' => $this->transKey,
            'X-Version' => '2.1',
            'Content-Type' => 'application/json',
            'Authorization' => 'V2-HMAC-SHA256, Signature: '.$this->signature($date, $body),
        ];

        if ($idempotencyKey !== null) {
            $headers['X-Idempotency-Key'] = $idempotencyKey;
        }

        $pending = Http::withHeaders($headers);

        if ($this->retryTimes > 1) {
            $pending->retry(
                times: $this->retryTimes,
                sleepMilliseconds: $this->retrySleepMs,
                when: static fn (Throwable $e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && (bool) $e->response?->serverError()),
                throw: false,
            );
        }

        $response = $method === 'post'
            ? $pending->withBody($body, 'application/json')->post($this->baseUrl.$path)
            : $pending->get($this->baseUrl.$path);

        if ($response->failed()) {
            $error = $response->json() ?? [];
            Log::warning('billing.dlocal_api_failure', ['path' => $path, 'http_status' => $response->status()]);

            throw new DlocalApiException(
                message: $error['message'] ?? 'dLocal API error',
                dlocalCode: isset($error['code']) ? (string) $error['code'] : null,
                context: $error,
            );
        }

        return $response->json() ?? [];
    }

    private function signature(string $date, string $body): string
    {
        return hash_hmac('sha256', $this->login.$date.$body, $this->secretKey);
    }
}
