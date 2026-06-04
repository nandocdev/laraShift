<?php

declare(strict_types=1);

namespace App\Modules\Central\Landings\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Central\Landings\Models\Landing;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class ServeTenantLandingController extends Controller
{
    /**
     * Serves the published landing page for the current tenant.
     */
    public function __invoke(): Response|string|View
    {
        $tenantId = tenant('id');

        // Look for a published landing with the slug 'saas-landing' (default root landing)
        // or the first published landing available.
        $landing = Landing::where('tenant_id', $tenantId)
            ->where('status', 'published')
            ->orderByRaw("slug = 'saas-landing' DESC")
            ->first();

        if ($landing && $landing->published_html) {
            return response($landing->published_html)
                ->header('Content-Type', 'text/html');
        }

        // Fallback: Show a default landing/welcome if nothing is published
        return view('welcome', [
            'tenant' => tenant()
        ]);
    }
}
