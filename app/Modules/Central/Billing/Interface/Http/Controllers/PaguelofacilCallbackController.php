<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Interface\Http\Controllers;

use App\Modules\Central\Billing\Application\DTO\PaymentResultData;
use App\Modules\Central\Billing\Domain\Enums\PaymentStatus;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Platform\Foundation\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaguelofacilCallbackController extends Controller
{
    /**
     * Handle the return redirect from PagueloFacil Hosted Checkout.
     *
     * UX-only: never mutates subscriptions or tenants. The source of truth
     * is the server-to-server webhook verified in PaymentVerifier.
     */
    public function handleReturn(Request $request)
    {
        Log::info('PagueloFacil Callback received', $request->all());

        $result = PaymentResultData::fromClavePayload($request->all());
        $tenantId = $request->input('PARM_1');

        // Protocol and Port logic for environment-safe redirects
        $protocol = $request->secure() ? 'https://' : 'http://';
        $appUrlHost = parse_url(config('app.url'), PHP_URL_HOST);
        $appUrlPort = parse_url(config('app.url'), PHP_URL_PORT);
        $portSuffix = $appUrlPort ? ":{$appUrlPort}" : '';

        $resolveRedirectDomain = function (?string $tid) use ($appUrlHost): ?string {
            if (! $tid) {
                return null;
            }

            $tenant = Tenant::find($tid);

            if (! $tenant) {
                return null;
            }

            return $tenant->domains()->first()?->domain ?? $tenant->slug.'.'.$appUrlHost;
        };

        if ($result->status !== PaymentStatus::Approved || $result->amount <= 0) {
            Log::warning('PagueloFacil Payment failed or denied', [
                'status' => $result->status->value,
                'error' => $result->errorMessage,
            ]);

            $domain = $resolveRedirectDomain($tenantId);

            if ($domain) {
                return redirect()->away($protocol.$domain.$portSuffix.'/billing/cancel');
            }

            return redirect()->route('home')->with('error', __('Payment was denied or cancelled.'));
        }

        // Approved on the browser query string is NOT trusted for fulfillment.
        // Webhook (PaymentVerifier) is the only path that creates Subscription / updates plan.
        Log::info('PagueloFacil Callback approved (ux-only, awaiting webhook)', [
            'tenant_id' => $tenantId,
            'gateway_reference' => $result->gatewayReference,
        ]);

        $domain = $resolveRedirectDomain($tenantId);

        if ($domain) {
            return redirect()->away($protocol.$domain.$portSuffix.'/billing/success');
        }

        return redirect()->route('home')->with('status', __('Payment received. Your subscription will be activated shortly.'));
    }
}
