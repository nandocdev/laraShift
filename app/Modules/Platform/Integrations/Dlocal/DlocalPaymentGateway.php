<?php

declare(strict_types=1);

namespace App\Modules\Platform\Integrations\Dlocal;

use App\Modules\Platform\Integrations\Dlocal\Client\DlocalHttpClient;
use App\Modules\Platform\Integrations\Dlocal\Contracts\PaymentGatewayContract;
use App\Modules\Platform\Integrations\Dlocal\DTOs\PaymentRequestData;
use App\Modules\Platform\Integrations\Dlocal\DTOs\PaymentResponseData;
use App\Modules\Platform\Integrations\Dlocal\DTOs\RefundRequestData;
use App\Modules\Platform\Integrations\Dlocal\DTOs\RefundResponseData;

final class DlocalPaymentGateway implements PaymentGatewayContract
{
    public function __construct(
        private readonly DlocalHttpClient $client,
    ) {}

    public function createPayment(PaymentRequestData $data): PaymentResponseData
    {
        $payload = array_filter([
            'order_id' => $data->orderId,
            'amount' => $this->toDecimal($data->amountInCents),
            'currency' => $data->currency,
            'country' => $data->country,
            'payment_method_id' => $data->paymentMethodId,
            'payment_method_flow' => $data->flow->value,
            'payer' => $data->payer->toArray(),
            'description' => $data->description,
            'notification_url' => $data->notificationUrl,
            'callback_url' => $data->callbackUrl,
        ], static fn ($v) => $v !== null);

        $cardData = array_filter([
            'holder_name' => $data->payer->name !== '' ? $data->payer->name : null,
            'token' => $data->token,
            'card_id' => $data->cardId,
            'save' => $data->save,
            'stored_credential_type' => $data->storedCredentialType,
            'stored_credential_usage' => $data->storedCredentialUsage,
        ], static fn ($v) => $v !== null);

        // Only send the card object if we actually have a card token, ID, or encrypted data.
        // Otherwise, setting holder_name alone causes an "Invalid parameter: card" rejection.
        if (isset($cardData['token']) || isset($cardData['card_id']) || isset($cardData['encrypted_data'])) {
            $payload['card'] = $cardData;
        }

        if ($data->metadata !== []) {
            $payload['metadata'] = $data->metadata;
        }

        $response = $this->client->post('/payments', $payload);

        return PaymentResponseData::fromApiResponse($response);
    }

    public function retrievePayment(string $paymentId): PaymentResponseData
    {
        $response = $this->client->get("/payments/{$paymentId}");

        return PaymentResponseData::fromApiResponse($response);
    }

    public function refund(RefundRequestData $data): RefundResponseData
    {
        $response = $this->client->post('/refunds', array_filter([
            'payment_id' => $data->paymentId,
            'amount' => $data->amountInCents !== null ? $this->toDecimal($data->amountInCents) : null,
            'notification_url' => $data->notificationUrl,
        ], static fn ($v) => $v !== null));

        return RefundResponseData::fromApiResponse($response);
    }

    private function toDecimal(int $amountInCents): string
    {
        return number_format($amountInCents / 100, 2, '.', '');
    }
}
