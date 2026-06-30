<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Http\Controllers;

use App\Modules\Central\Billing\Actions\CancelSubscriptionAction;
use App\Modules\Central\Billing\Actions\CreateCheckoutSessionAction;
use App\Modules\Central\Billing\Actions\FetchInvoicesAction;
use App\Modules\Central\Billing\Actions\FetchPlansAction;
use App\Modules\Central\Billing\Actions\FetchSubscriptionStatusAction;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingApiController extends Controller
{
    public function __construct(
        private FetchPlansAction $fetchPlans,
    ) {}

    /**
     * GET /central/plans
     */
    public function listPlans(): JsonResponse
    {
        return response()->json($this->fetchPlans->execute());
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
    public function subscriptionStatus(string $tenantId, FetchSubscriptionStatusAction $action): JsonResponse
    {
        return response()->json($action->execute($tenantId));
    }

    /**
     * POST /central/billing/subscriptions/{id}/cancel
     */
    public function cancelSubscription(Request $request, string $id, CancelSubscriptionAction $action): JsonResponse
    {
        $tenant = Tenant::whereHas('subscriptions', fn ($q) => $q->where('id', $id)->orWhere('stripe_id', $id))->firstOrFail();

        $action->execute($tenant, $id, $request->boolean('immediately', false));

        return response()->json(['message' => 'Subscription cancellation processed.']);
    }

    /**
     * GET /central/billing/invoices
     */
    public function listInvoices(Request $request, FetchInvoicesAction $action): JsonResponse
    {
        return response()->json(
            $action->execute($request->tenant_id)
        );
    }
}
