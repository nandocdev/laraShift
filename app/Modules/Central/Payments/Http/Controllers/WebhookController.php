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
final class WebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $gateway = $this->resolveGateway($request);
        $tenantId = $this->resolveTenantId($request);
        $rawPayload = $request->getContent();
        
        $signature = match ($gateway) {
            'clave'  => $request->header('X-Clave-Signature', ''),
            'dlocal' => $request->header('X-Signature', ''),
            default  => '',
        };

        $webhookSecret = config("payments.{$gateway}.webhook_secret");

        ProcessPaymentWebhookJob::dispatch(
            tenantId:      $tenantId,
            rawPayload:    $rawPayload,
            signature:     $signature,
            webhookSecret: $webhookSecret,
        );

        // Always 200. Gateway must not retry due to our processing latency.
        return response()->noContent();
    }

    private function resolveGateway(Request $request): string
    {
        if ($request->is('*/clave')) return 'clave';
        if ($request->is('*/dlocal')) return 'dlocal';
        
        return 'clave';
    }

    /**
     * Tenant can be encoded in the webhook URL as a query param
     * or derived from the payload. Adjust to match the gateway's behavior.
     */
    private function resolveTenantId(Request $request): string
    {
        // Option A: URL query param ?tenant={id} (simplest, configure on gateway)
        if ($request->query('tenant')) {
            return (string) $request->query('tenant');
        }

        // Option B: Extract from JSON payload field
        $payload = json_decode($request->getContent(), true);

        return (string) ($payload['tenantId'] ?? $payload['merchantId'] ?? '');
    }
}
