<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Infrastructure\Gateways;

use App\Modules\Central\Billing\Application\DTO\MerchantData;
use App\Modules\Central\Billing\Application\DTO\PaymentData;
use App\Modules\Central\Billing\Application\DTO\PaymentResultData;
use App\Modules\Central\Billing\Domain\Exceptions\RecurringBillingNotSupportedException;
use App\Modules\Central\Billing\Domain\Models\Subscription;

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

    /**
     * List historical transactions from the gateway.
     */
    public function listTransactions(string $apiKey, array $filters = []): array;

    /**
     * Execute an engine-managed recurring charge against a saved payment method.
     * Gateways that manage recurrence on their own side should throw
     * RecurringBillingNotSupportedException (see ClaveGateway).
     *
     * @throws RecurringBillingNotSupportedException
     */
    public function chargeSubscription(Subscription $subscription, int $amountInCents): PaymentResultData;
}
