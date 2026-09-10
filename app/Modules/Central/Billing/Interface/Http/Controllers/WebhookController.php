<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Interface\Http\Controllers;

use App\Modules\Central\Billing\Application\Jobs\ProcessPaymentWebhookJob;
use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Central\Billing\Infrastructure\Gateways\ClaveGateway;
use App\Modules\Central\Billing\Infrastructure\Gateways\DlocalGateway;
use App\Modules\Platform\Integrations\Dlocal\Models\PaymentReference;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

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
        $rawPayload = $request->getContent();

        $signature = match ($gateway) {
            'clave' => $request->header('X-Clave-Signature', ''),
            'dlocal' => $request->header('X-Signature', ''),
            default => '',
        };

        $webhookSecret = config("payments.{$gateway}.webhook_secret");

        // Verify signature synchronously to prevent DoS via queue exhaustion
        $gatewayService = match ($gateway) {
            'dlocal' => app(DlocalGateway::class),
            default => app(ClaveGateway::class),
        };

        if (! $gatewayService->verifyWebhook($rawPayload, $signature, $webhookSecret)) {
            Log::warning("{$gateway} Webhook: signature mismatch. Rejecting.", [
                'ip' => $request->ip(),
            ]);
            abort(401, 'Invalid webhook signature');
        }

        $tenantId = $this->resolveTenantId($request);

        // B003: Validate tenantId against payment record before enqueuing to avoid poisoning queue/logs
        $payloadData = json_decode($rawPayload, true) ?? $request->all();
        $displayId = $payloadData['display_id'] ?? $payloadData['displayId'] ?? $payloadData['order_id'] ?? $payloadData['PARM_2'] ?? null;

        if ($displayId) {
            $paymentTenantId = Payment::withoutGlobalScopes()->where('display_id', $displayId)->value('tenant_id');

            if ($paymentTenantId && (string) $paymentTenantId !== (string) $tenantId) {
                Log::warning("{$gateway} Webhook: tenant mismatch — payload tenant does not own payment", [
                    'payload_tenant' => $tenantId,
                    'payment_tenant' => $paymentTenantId,
                    'display_id' => $displayId,
                ]);
                abort(422, 'Tenant mismatch for payment');
            }
        }

        ProcessPaymentWebhookJob::dispatch(
            tenantId: $tenantId,
            rawPayload: $rawPayload,
            signature: $signature,
            webhookSecret: $webhookSecret,
        );

        // Always 200. Gateway must not retry due to our processing latency.
        return response()->noContent();
    }

    private function resolveGateway(Request $request): string
    {
        if ($request->is('*/clave')) {
            return 'clave';
        }
        if ($request->is('*/dlocal')) {
            return 'dlocal';
        }

        return 'clave';
    }

    /**
     * DB-first tenant resolution: the payload only proposes, the DB disposes.
     * display_id → payments.tenant_id wins over any PARM_1/metadata hint,
     * then gateway reference → payment_references/payments, and only as a
     * last resort the explicit tenant hint from the payload.
     */
    private function resolveTenantId(Request $request): string
    {
        $payload = json_decode($request->getContent(), true) ?? $request->all();

        $displayId = $payload['display_id']
            ?? $payload['displayId']
            ?? $payload['order_id']
            ?? $payload['PARM_2']
            ?? ($payload['metadata']['displayId'] ?? null);

        if (is_string($displayId) && $displayId !== '') {
            $owner = Payment::withoutGlobalScopes()->where('display_id', $displayId)->value('tenant_id');

            if ($owner) {
                return (string) $owner;
            }
        }

        $gatewayReference = $payload['payment_id'] ?? $payload['id'] ?? $payload['gateway_reference'] ?? null;

        if (is_string($gatewayReference) && $gatewayReference !== '') {
            $refTenant = PaymentReference::where('external_reference', $gatewayReference)->value('tenant_id')
                ?? Payment::withoutGlobalScopes()->where('gateway_reference', $gatewayReference)->value('tenant_id');

            if ($refTenant) {
                return (string) $refTenant;
            }
        }

        // Fallback: explicit tenant hint (PagueloFacil PARM_1 / metadata).
        $tenantId = $payload['tenant_id']
            ?? $payload['tenantId']
            ?? $payload['merchantId']
            ?? $payload['PARM_1']
            ?? ($payload['metadata']['tenant_id'] ?? null);

        if (empty($tenantId)) {
            Log::warning('Webhook received without tenant identifier');
            abort(400, 'Missing tenant identifier');
        }

        return (string) $tenantId;
    }
}
