<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Interface\Http\Controllers;

use App\Modules\Central\Billing\Application\Actions\ResolveWebhookTenant;
use App\Modules\Central\Billing\Application\Jobs\ProcessPaymentWebhookJob;
use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Central\Billing\Domain\Models\PaymentGatewayEvent;
use App\Modules\Central\Billing\Infrastructure\Gateways\DlocalGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

final class DlocalWebhookController extends Controller
{
    public function handle(Request $request, DlocalGateway $dlocal, ResolveWebhookTenant $resolver): Response
    {
        $rawPayload = $request->getContent();
        $signature = $request->header('X-Signature', '');

        // Sync verify: 401 touches no DB.
        if (! $dlocal->verify($rawPayload, (string) $signature)) {
            Log::warning('billing.dlocal_webhook_bad_signature', ['ip' => $request->ip()]);

            abort(401, 'Invalid webhook signature.');
        }

        $payload = json_decode($rawPayload, true) ?? $request->all();

        try {
            $tenantId = $resolver->execute($payload);
        } catch (\RuntimeException) {
            PaymentGatewayEvent::firstOrCreate(
                ['gateway' => 'dlocal', 'gateway_event_id' => 'unresolved_'.sha1($rawPayload)],
                ['event_type' => 'unresolved.tenant', 'payload' => $payload]
            );

            Log::warning('billing.dlocal_webhook_unresolved_tenant');

            return response()->noContent();
        }

        $displayId = $payload['order_id'] ?? $payload['displayId'] ?? null;

        if (is_string($displayId) && $displayId !== '') {
            $owner = Payment::withoutGlobalScopes()->where('display_id', $displayId)->value('tenant_id');

            if ($owner && (string) $owner !== (string) $tenantId) {
                Log::warning('billing.dlocal_webhook_tenant_mismatch', ['display_id' => $displayId]);

                abort(422, 'Tenant mismatch for payment.');
            }
        }

        ProcessPaymentWebhookJob::dispatch(
            tenantId: $tenantId,
            gateway: 'dlocal',
            rawPayload: $rawPayload,
            signature: (string) $signature,
        );

        return response()->noContent();
    }
}
