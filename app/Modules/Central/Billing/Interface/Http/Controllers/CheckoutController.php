<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Interface\Http\Controllers;

use App\Modules\Central\Billing\Application\Actions\CreateCheckoutSessionAction;
use App\Modules\Central\Catalog\Application\Services\PlanManager;
use App\Modules\Platform\Contracts\Billing\PlanRef;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class CheckoutController extends Controller
{
    public function initiate(Request $request, CreateCheckoutSessionAction $checkouts, PlanManager $plans): JsonResponse
    {
        $data = $request->validate([
            'plan' => ['required', 'string'],
            'display_id' => ['required', 'string', 'max:64'],
        ]);

        $tenant = tenant();
        $plan = $plans->find($data['plan']);

        $session = $checkouts->execute($tenant, new PlanRef(
            slug: $plan->slug,
            amountCents: $plan->price_monthly,
            currency: $plan->currency,
            gatewayIds: $plan->gatewayIds(),
        ), $data['display_id']);

        return response()->json(['checkout_url' => $session->url, 'display_id' => $session->id]);
    }
}
