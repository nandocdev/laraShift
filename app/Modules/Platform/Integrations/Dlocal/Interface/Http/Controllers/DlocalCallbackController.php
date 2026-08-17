<?php

declare(strict_types=1);

namespace App\Modules\Platform\Integrations\Dlocal\Interface\Http\Controllers;

use App\Modules\Platform\Contracts\TenantDomainResolverContract;
use App\Modules\Platform\Integrations\Dlocal\Models\PaymentReference;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser callback for the dLocal REDIRECT flow.
 *
 * dLocal returns the customer to the callback_url after the payment flow with
 * `paymentId` and `status` (APPROVED / REJECTED / PENDING / COMPLETED). This
 * endpoint redirects the customer to the tenant's billing success/cancel page.
 *
 * The authoritative status update happens server-side via the webhook
 * (DlocalWebhookController -> PaymentVerifier); this callback is UX only.
 */
final class DlocalCallbackController extends Controller
{
    public function handle(Request $request): Response
    {
        $paymentId = (string) ($request->input('paymentId') ?? $request->query('paymentId', ''));
        $status = strtoupper((string) ($request->input('status') ?? $request->query('status', '')));

        $reference = PaymentReference::where('external_reference', $paymentId)->first();

        if (! $reference || ! $reference->tenant_id) {
            Log::warning('dLocal callback: no payment reference found', [
                'payment_id' => $paymentId,
                'status' => $status,
            ]);

            return response()->noContent(Response::HTTP_NOT_FOUND);
        }

        $domain = app(TenantDomainResolverContract::class)->resolveDomain($reference->tenant_id)
            ?? $reference->tenant_id.'.'.config('tenancy.central_domain');

        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?? 'https';
        $port = parse_url((string) config('app.url'), PHP_URL_PORT);
        $portSuffix = $port ? ":{$port}" : '';
        $baseUrl = "{$scheme}://{$domain}{$portSuffix}";

        $approved = in_array($status, ['APPROVED', 'COMPLETED'], true);

        return redirect()->away($baseUrl.($approved ? '/billing/success' : '/billing/cancel'));
    }
}
