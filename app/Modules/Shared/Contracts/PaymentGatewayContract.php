<?php

declare(strict_types=1);

namespace App\Modules\Shared\Contracts;

use App\Modules\Central\Payments\DTOs\MerchantData;
use App\Modules\Central\Payments\DTOs\PaymentData;
use App\Modules\Central\Payments\DTOs\PaymentResultData;
use App\Modules\Central\Payments\DTOs\PayoutData;
use App\Modules\Central\Payments\DTOs\PayoutResultData;

/**
 * Contrato compartido para pasarelas de pago.
 * Las implementaciones concretas viven en Central\Payments\Services\Gateways.
 *
 * Movido desde Central\Payments\Contracts\PaymentGateway a Shared
 * para permitir acceso desde múltiples contextos (Central + Tenant).
 */
interface PaymentGatewayContract
{
    /**
     * Validate API key and load merchant + services from gateway.
     * Throws if API key is invalid or CLAVE service not found.
     */
    public function loadMerchant(string $apiKey): MerchantData;

    /**
     * Generate a signed checkout session URL for the iframe.
     * Returns the URL to embed in the frontend widget.
     */
    public function buildCheckoutUrl(PaymentData $payment, string $apiKey): string;

    /**
     * Verify a webhook payload signature.
     * Returns true if signature is valid.
     */
    public function verifyWebhook(string $payload, string $signature, string $secret): bool;

    /**
     * Parse a raw webhook payload into a typed result.
     */
    public function parseWebhookPayload(array $payload): PaymentResultData;

    /**
     * Process a direct payment using a token or card details.
     */
    public function processDirectPayment(PaymentData $payment, string $apiKey, ?string $token = null): PaymentResultData;

    /**
     * Submit a payout request.
     */
    public function submitPayout(PayoutData $payout): PayoutResultData;

    /**
     * Get the status of a payout.
     */
    public function getPayoutStatus(string $payoutId): PayoutResultData;

    /**
     * Unique gateway identifier (e.g. 'clave').
     */
    public function identifier(): string;

    /**
     * List historical transactions from the gateway.
     */
    public function listTransactions(string $apiKey, array $filters = []): array;
}
