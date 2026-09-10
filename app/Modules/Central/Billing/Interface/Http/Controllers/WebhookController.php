<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Interface\Http\Controllers;

use App\Modules\Central\Billing\Application\Actions\ResolveWebhookTenant;
use App\Modules\Central\Billing\Application\Jobs\ProcessPaymentWebhookJob;
use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Central\Billing\Domain\Models\PaymentGatewayEvent;
use App\Modules\Central\Billing\Infrastructure\Gateways\ClaveGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

final class WebhookController extends Controller
{
    public function handle(Request $request, ClaveGateway $clave, ResolveWebhookTenant $resolver): Response
    {
        $rawPayload = $request->getContent();
        $signature = $request->header('X-Clave-Signature', '');

        // Sync verify: 401 touches no DB.
        if (! $clave->verify($rawPayload, (string) $signature)) {
            Log::warning('billing.webhook_bad_signature', ['ip' => $request->ip()]);

            abort(401, 'Invalid webhook signature.');
        }

        $payload = json_decode($rawPayload, true) ?? $request->all();

        try {
            $tenantId = $resolver->execute($payload);
        } catch (\RuntimeException) {
            // Fase 0, regla 4: persist raw, ack 200, alert for manual review.
            PaymentGatewayEvent::firstOrCreate(
                ['gateway' => 'clave', 'gateway_event_id' => 'unresolved_'.sha1($rawPayload)],
                ['event_type' => 'unresolved.tenant', 'payload' => $payload]
            );

            Log::warning('billing.webhook_unresolved_tenant');

            return response()->noContent();
        }

        // Strict tenant match before dispatching.
        $displayId = $payload['PARM_2'] ?? $payload['PARM_1'] ?? $payload['displayId'] ?? null;

        if (is_string($displayId) && $displayId !== '') {
            $owner = Payment::withoutGlobalScopes()->where('display_id', $displayId)->value('tenant_id');

            if ($owner && (string) $owner !== (string) $tenantId) {
                Log::warning('billing.webhook_tenant_mismatch', ['display_id' => $displayId]);

                abort(422, 'Tenant mismatch for payment.');
            }
        }

        ProcessPaymentWebhookJob::dispatch(
            tenantId: $tenantId,
            gateway: 'clave',
            rawPayload: $rawPayload,
            signature: (string) $signature,
        );

        return response()->noContent();
    }
}
