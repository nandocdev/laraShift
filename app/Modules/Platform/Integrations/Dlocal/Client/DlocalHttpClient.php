<?php

declare(strict_types=1);

namespace App\Modules\Platform\Integrations\Dlocal\Client;

use App\Modules\Platform\Integrations\Dlocal\Exceptions\DlocalApiException;
use Illuminate\Support\Facades\Http;

final class DlocalHttpClient {
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $login,
        private readonly string $transKey,
        private readonly string $secretKey,
    ) {
    }

    public function post(string $path, array $payload): array {
        return $this->send('post', $path, $payload);
    }

    public function get(string $path): array {
        return $this->send('get', $path, []);
    }

    private function send(string $method, string $path, array $payload): array {
        $body = $method === 'post' ? json_encode($payload, JSON_THROW_ON_ERROR) : '';
        $date = now()->toIso8601String();

        $request = Http::withHeaders([
            'X-Date' => $date,
            'X-Login' => $this->login,
            'X-Trans-Key' => $this->transKey,
            'X-Version' => '2.1',
            'Content-Type' => 'application/json',
            'Authorization' => 'V2-HMAC-SHA256, Signature: ' . $this->signature($date, $body),
        ]);

        $response = $method === 'post'
            ? $request->post($this->baseUrl . $path, $payload)
            : $request->get($this->baseUrl . $path);

        if ($response->failed()) {
            $error = $response->json() ?? [];

            throw new DlocalApiException(
                message: $error['message'] ?? 'dLocal API error',
                dlocalCode: $error['code'] ?? null,
                context: $error,
            );
        }

        return $response->json();
    }

    private function signature(string $date, string $body): string {
        return hash_hmac('sha256', $this->login . $date . $body, $this->secretKey);
    }
}