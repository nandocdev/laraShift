<?php

declare(strict_types=1);

namespace App\Modules\Platform\Integrations\Dlocal\Interface\Http\Controllers;

use App\Modules\Platform\Integrations\Dlocal\Jobs\ResolveDlocalWebhookJob;
use App\Modules\Platform\Integrations\Dlocal\Models\PaymentReference;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

final class DlocalWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $rawPayload = $request->getContent();
        $signature = $request->header('X-Signature', '');
        $webhookSecret = (string) config('dlocal.webhook_secret', '');

        if (! $this->verifySignature($rawPayload, $signature, $webhookSecret)) {
            Log::warning('dLocal Webhook: signature mismatch', [
                'ip' => $request->ip(),
            ]);

            abort(401, 'Invalid webhook signature');
        }

        $payload = json_decode($rawPayload, true);

        if (! is_array($payload)) {
            Log::warning('dLocal Webhook: invalid payload');

            abort(400, 'Invalid payload');
        }

        $externalReference = $payload['payment_id'] ?? $payload['id'] ?? null;

        if ($externalReference === null) {
            Log::warning('dLocal Webhook: missing payment_id in payload');

            return response()->noContent();
        }

        $reference = PaymentReference::where('external_reference', $externalReference)->first();

        if ($reference === null) {
            Log::warning('dLocal Webhook: no payment reference found', [
                'external_reference' => $externalReference,
            ]);

            return response()->noContent();
        }

        ResolveDlocalWebhookJob::dispatch(
            externalReference: $externalReference,
            rawPayload: $payload,
            signature: $signature,
            webhookSecret: $webhookSecret,
        );

        return response()->noContent();
    }

    private function verifySignature(string $payload, string $signature, string $secret): bool
    {
        if ($secret === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }
}
