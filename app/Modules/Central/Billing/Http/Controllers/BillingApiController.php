<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Http\Controllers;

use App\Modules\Central\Billing\Actions\CancelSubscriptionAction;
use App\Modules\Central\Billing\Actions\CreateCheckoutSessionAction;
use App\Modules\Central\Billing\Models\Invoice;
use App\Modules\Central\Billing\Support\PlanManager;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingApiController extends Controller
{
    /**
     * GET /central/plans
     */
    public function listPlans(): JsonResponse
    {
        return response()->json([
            'data' => PlanManager::all(),
        ]);
    }

    /**
     * POST /central/billing/checkout
     */
    public function checkout(Request $request, CreateCheckoutSessionAction $action): JsonResponse
    {
        $request->validate([
            'tenant_id' => 'required|uuid|exists:tenants,id',
            'plan_id' => 'required|string',
        ]);

        $tenant = Tenant::findOrFail($request->tenant_id);

        try {
            $checkoutUrl = $action->execute($tenant, $request->plan_id);

            return response()->json([
                'checkout_url' => $checkoutUrl,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * GET /central/billing/subscriptions/{tenant_id}
     */
    public function subscriptionStatus(string $tenantId): JsonResponse
    {
        $tenant = Tenant::findOrFail($tenantId);
        $subscription = $tenant->subscription('default');

        return response()->json([
            'tenant_id' => $tenant->id,
            'plan_id' => $tenant->plan_id,
            'status' => $tenant->status,
            'subscription' => $subscription ? [
                'stripe_id' => $subscription->stripe_id,
                'stripe_status' => $subscription->stripe_status,
                'ends_at' => $subscription->ends_at,
                'on_grace_period' => $subscription->onGracePeriod(),
                'active' => $subscription->active(),
            ] : null,
        ]);
    }

    /**
     * POST /central/billing/subscriptions/{id}/cancel
     */
    public function cancelSubscription(Request $request, string $id, CancelSubscriptionAction $action): JsonResponse
    {
        // $id is the external_id/stripe_id here or local ID?
        // PRD says {id}, let's assume local ID but resolve to tenant
        $tenant = Tenant::whereHas('subscriptions', fn ($q) => $q->where('id', $id)->orWhere('stripe_id', $id))->firstOrFail();

        $action->execute($tenant, $id, $request->boolean('immediately', false));

        return response()->json(['message' => 'Subscription cancellation processed.']);
    }

    /**
     * GET /central/billing/invoices
     */
    public function listInvoices(Request $request): JsonResponse
    {
        $query = Invoice::query();

        if ($request->has('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        return response()->json($query->latest()->paginate(20));
    }
}
