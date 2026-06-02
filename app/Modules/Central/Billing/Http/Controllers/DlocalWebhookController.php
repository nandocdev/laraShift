<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Http\Controllers;

use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DlocalWebhookController extends Controller
{
    /**
     * Handle a dLocal webhook.
     */
    public function handleWebhook(Request $request): Response
    {
        // TODO: Implement dLocal webhook logic
        // - Validate signature
        // - Record event in payment_gateway_events
        // - Dispatch domain events
        
        return new Response('Webhook Handled (Not Implemented)', 200);
    }
}
