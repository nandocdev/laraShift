<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Interface\Http\Controllers;

use App\Modules\Central\Billing\Domain\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

final class PaguelofacilCallbackController extends Controller
{
    /**
     * Browser return from the hosted checkout. UX-ONLY: never mutates state.
     * Fulfillment happens exclusively via the server-side webhook.
     */
    public function handleReturn(Request $request): RedirectResponse
    {
        $displayId = (string) ($request->input('PARM_2') ?? $request->input('PARM_1') ?? '');
        $approved = ($request->input('Estado') === 'Aprobada') || ((int) $request->input('status') === 1);

        Log::info('billing.clave_return', ['display_id' => $displayId, 'approved' => $approved]);

        $domain = $this->resolveDomain($displayId);

        if (! $domain) {
            return redirect('/')
                ->with('status', __('Payment received. Your subscription will be activated shortly.'));
        }

        $path = $approved ? '/billing/success' : '/billing/cancel';

        return redirect()->away("https://{$domain}{$path}");
    }

    private function resolveDomain(string $displayId): ?string
    {
        if ($displayId === '') {
            return null;
        }

        $tenantId = Payment::withoutGlobalScopes()->where('display_id', $displayId)->value('tenant_id');

        if (! $tenantId) {
            return null;
        }

        // Resolved via config to avoid importing the Provisioning model.
        $model = config('tenancy.tenant_model');
        $tenant = $model::find($tenantId);

        if (! $tenant) {
            return null;
        }

        return $tenant->domains()->first()?->domain ?? $tenant->slug.'.'.config('tenancy.central_domain');
    }
}
