<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Http\Controllers;

use App\Modules\Central\Payments\Actions\InitiateCheckoutAction;
use App\Modules\Central\Payments\DTOs\PaymentData;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CheckoutController extends Controller
{
    public function __construct(
        private readonly InitiateCheckoutAction $initiateAction
    ) {}

    /**
     * Inicia una sesión de pago desde una petición HTTP estándar.
     */
    public function initiate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount'      => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'max:150'],
            'display_id'  => ['required', 'string'],
            'email'       => ['required', 'email'],
            'taxAmount'   => ['nullable', 'numeric', 'min:0'],
            'discount'    => ['nullable', 'numeric', 'min:0'],
            'lang'        => ['nullable', 'string', 'in:es,en'],
        ]);

        try {
            $session = $this->initiateAction->execute(
                data: new PaymentData(
                    amount:      (float) $data['amount'],
                    description: $data['description'],
                    displayId:   $data['display_id'],
                    email:       $data['email'],
                    taxAmount:   (float) ($data['taxAmount'] ?? 0),
                    discount:    (float) ($data['discount'] ?? 0),
                    lang:        $data['lang'] ?? 'es',
                ),
                tenantId: tenant('id'),
                apiKey: config('payments.clave.api_key'),
            );

            return response()->json([
                'checkout_url' => $session->checkoutUrl,
                'slug'         => $session->slug,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
