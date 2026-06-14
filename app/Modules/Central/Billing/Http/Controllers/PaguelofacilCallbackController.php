<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Http\Controllers;

use App\Modules\Central\Billing\Models\Subscription;
use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaguelofacilCallbackController extends Controller
{
    /**
     * Handle the return redirect from PagueloFacil Hosted Checkout.
     */
    public function handleReturn(Request $request)
    {
        Log::info("PagueloFacil Callback received", $request->all());

        $status = $request->input('Estado');
        $totalPaid = $request->input('TotalPagado');
        $operationId = $request->input('Oper');
        $tenantId = $request->input('PARM_1');
        $planId = $request->input('PARM_2');

        // Protocol and Port logic for environment-safe redirects
        $protocol = $request->secure() ? 'https://' : 'http://';
        $appUrlPort = parse_url(config('app.url'), PHP_URL_PORT);
        $portSuffix = $appUrlPort ? ":{$appUrlPort}" : '';

        if ($status !== 'Aprobada' || (float) $totalPaid <= 0) {
            Log::warning("PagueloFacil Payment failed or denied", ['status' => $status, 'reason' => $request->input('Razon')]);
            
            // Try to redirect back to the tenant's cancel page if we have the tenant
            if ($tenantId && $tenant = Tenant::find($tenantId)) {
                $primaryDomain = $tenant->domains()->first()?->domain ?? $tenant->slug . '.' . config('tenancy.central_domain');
                
                return redirect()->away($protocol . $primaryDomain . $portSuffix . '/billing/cancel');
            }

            return redirect()->route('home')->with('error', __('Payment was denied or cancelled.'));
        }

        try {
            $tenant = Tenant::findOrFail($tenantId);
            $plan = Plan::findOrFail($planId);

            \Illuminate\Support\Facades\DB::transaction(function () use ($tenant, $plan, $operationId) {
                // Create or update subscription record
                Subscription::updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'provider_subscription_id' => $operationId,
                    ],
                    [
                        'plan_id' => $plan->id,
                        'status' => 'active',
                        'gateway' => 'paguelofacil',
                        'current_period_end' => now()->addMonth(), // Assuming monthly for this flow
                    ]
                );

                // Update tenant's current plan
                $tenant->update(['plan_id' => $plan->slug]);
            });

            Log::info("PagueloFacil Subscription activated", ['tenant' => $tenant->id, 'plan' => $plan->slug]);

            // Redirect to success page on tenant domain
            $primaryDomain = $tenant->domains()->first()?->domain ?? $tenant->slug . '.' . config('tenancy.central_domain');

            $successUrl = $protocol . $primaryDomain . $portSuffix . '/billing/success';

            return redirect()->away($successUrl);

        } catch (\Exception $e) {
            Log::error("Error processing PagueloFacil callback: " . $e->getMessage());
            return redirect()->route('home')->with('error', __('An error occurred while finalizing your subscription.'));
        }
    }
}
