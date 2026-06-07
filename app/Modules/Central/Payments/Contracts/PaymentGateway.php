<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Contracts;

use App\Modules\Central\Payments\DTOs\MerchantData;
use App\Modules\Central\Payments\DTOs\PaymentData;
use App\Modules\Central\Payments\DTOs\PaymentResultData;

interface PaymentGateway
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
     * Unique gateway identifier (e.g. 'clave').
     */
    public function identifier(): string;
}
