<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use App\Modules\Central\Payments\Jobs\ProcessPaymentWebhookJob;

/**
 * Webhook endpoint for PagueLo Fácil / Clave.
 *
 * Contract:
 *   - Always return 200 immediately (gateway retries on non-2xx)
 *   - Verification and processing happen async in the job
 *   - Tenant is resolved from the payload's displayId or a dedicated URL param
 */
final class WebhookController extends Controller {
    public function handle(Request $request): Response {
        $gateway = $this->resolveGateway($request);
        $rawPayload = $request->getContent();

        $signature = match ($gateway) {
            'clave' => $request->header('X-Clave-Signature', ''),
            'dlocal' => $request->header('X-Signature', ''),
            default => '',
        };

        $webhookSecret = config("payments.{$gateway}.webhook_secret");

        // Verify signature synchronously to prevent DoS via queue exhaustion
        $verifier = app(\App\Modules\Central\Payments\Contracts\PaymentGateway::class);
        // We temporarily swap the implementation to the correct gateway for verification
        $gatewayService = match ($gateway) {
            'dlocal' => app(\App\Modules\Central\Payments\Services\Gateways\DlocalGateway::class),
            default => app(\App\Modules\Central\Payments\Services\Gateways\ClaveGateway::class),
        };

        if (!$gatewayService->verifyWebhook($rawPayload, $signature, $webhookSecret)) {
            \Illuminate\Support\Facades\Log::warning("{$gateway} Webhook: signature mismatch. Rejecting.", [
                'ip' => $request->ip()
            ]);
            abort(401, 'Invalid webhook signature');
        }

        $tenantId = $this->resolveTenantId($request);

        ProcessPaymentWebhookJob::dispatch(
            tenantId: $tenantId,
            rawPayload: $rawPayload,
            signature: $signature,
            webhookSecret: $webhookSecret,
        );

        // Always 200. Gateway must not retry due to our processing latency.
        return response()->noContent();
    }

    private function resolveGateway(Request $request): string {
        if ($request->is('*/clave'))
            return 'clave';
        if ($request->is('*/dlocal'))
            return 'dlocal';

        return 'clave';
    }

    /**
     * Tenant can be encoded in the webhook URL as a query param
     * or derived from the payload. Adjust to match the gateway's behavior.
     */
    private function resolveTenantId(Request $request): string {
        $payload = json_decode($request->getContent(), true);

        // Security: Prioritize payload data over untrusted query params
        $tenantId = $payload['tenantId'] ?? $payload['merchantId'] ?? $request->query('tenant');

        if (empty($tenantId)) {
            Log::warning('Webhook received without tenant identifier');
            abort(400, 'Missing tenant identifier');
        }

        return (string) $tenantId;
    }
}
