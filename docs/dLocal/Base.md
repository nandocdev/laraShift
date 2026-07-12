# dLocal Payment Gateway Integration

> Module: `Modules/Platform/Integrations/Dlocal`
>
> Status: Design — not yet implemented
>
> Scope: Payment creation, retrieval and refund via dLocal API. `authorize`/`capture` flow excluded until a concrete use case requires it.

The contract is agnostic of Central/Tenant context. It speaks only the dLocal API language. Context resolution (Central billing vs Tenant customer payment) is handled upstream by the webhook resolver in `dLocalRD.md`.

---

## Module Structure

```
Modules/Platform/Integrations/Dlocal/
├── Contracts/PaymentGatewayContract.php
├── DTOs/
│   ├── PayerData.php
│   ├── PaymentRequestData.php
│   ├── PaymentResponseData.php
│   ├── RefundRequestData.php
│   └── RefundResponseData.php
├── Enums/
│   ├── DlocalPaymentStatus.php
│   └── PaymentMethodFlow.php
├── Exceptions/
│   ├── DlocalApiException.php
│   └── DlocalSignatureException.php
├── Client/DlocalHttpClient.php
└── DlocalPaymentGateway.php
```

---

## Enums

### `DlocalPaymentStatus`

```php
<?php

declare(strict_types=1);

namespace Modules\Platform\Integrations\Dlocal\Enums;

enum DlocalPaymentStatus: string
{
    case Pending = 'PENDING';
    case Paid = 'PAID';
    case Rejected = 'REJECTED';
    case Cancelled = 'CANCELLED';
    case Authorized = 'AUTHORIZED';
    case Verified = 'VERIFIED';

    public function isSuccessful(): bool
    {
        return $this === self::Paid;
    }

    public function isRejected(): bool
    {
        return $this === self::Rejected;
    }

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    /**
     * PENDING and AUTHORIZED are not final states.
     * Consumers must not mark the business operation as resolved yet.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Paid, self::Rejected, self::Cancelled], true);
    }
}
```

### `PaymentMethodFlow`

```php
<?php

declare(strict_types=1);

namespace Modules\Platform\Integrations\Dlocal\Enums;

enum PaymentMethodFlow: string
{
    case Direct = 'DIRECT';
    case Redirect = 'REDIRECT';
}
```

---

## Exceptions

### `DlocalApiException`

```php
<?php

declare(strict_types=1);

namespace Modules\Platform\Integrations\Dlocal\Exceptions;

use RuntimeException;
use Throwable;

final class DlocalApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context  Raw dLocal error payload for logging.
     *                                          Must NEVER include card data.
     */
    public function __construct(
        string $message,
        public readonly ?string $dlocalCode = null,
        public readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
```

### `DlocalSignatureException`

```php
<?php

declare(strict_types=1);

namespace Modules\Platform\Integrations\Dlocal\Exceptions;

use RuntimeException;

final class DlocalSignatureException extends RuntimeException
{
    //
}
```

---

## DTOs

### `PayerData`

```php
<?php

declare(strict_types=1);

namespace Modules\Platform\Integrations\Dlocal\DTOs;

final readonly class PayerData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $documentId,
        public ?string $documentType = null,
        public ?string $phone = null,
        /** Internal reference (tenant_id, subscription_id, etc). Opaque to dLocal. */
        public ?string $userReference = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'email' => $this->email,
            'document' => $this->documentId,
            'document_type' => $this->documentType,
            'phone' => $this->phone,
            'user_reference' => $this->userReference,
        ], static fn ($value) => $value !== null);
    }
}
```

### `PaymentRequestData`

```php
<?php

declare(strict_types=1);

namespace Modules\Platform\Integrations\Dlocal\DTOs;

use Modules\Platform\Integrations\Dlocal\Enums\PaymentMethodFlow;

final readonly class PaymentRequestData
{
    /**
     * @param  int  $amountInCents  Never use float for money. dLocal expects
     *                               a decimal ("10.00"); conversion happens only
     *                               at the boundary (DlocalPaymentGateway),
     *                               never in the consumer.
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $orderId,
        public int $amountInCents,
        public string $currency,
        public string $country,
        public PayerData $payer,
        public PaymentMethodFlow $flow,
        public ?string $token = null,
        public ?string $notificationUrl = null,
        public array $metadata = [],
    ) {}
}
```

### `PaymentResponseData`

```php
<?php

declare(strict_types=1);

namespace Modules\Platform\Integrations\Dlocal\DTOs;

use Modules\Platform\Integrations\Dlocal\Enums\DlocalPaymentStatus;

final readonly class PaymentResponseData
{
    public function __construct(
        public string $id,
        public string $orderId,
        public DlocalPaymentStatus $status,
        public ?string $statusDetail,
        public int $amountInCents,
        public string $currency,
        public ?string $redirectUrl = null,
    ) {}

    /**
     * Sole allowed construction point.
     * Do not instantiate from data not coming from the dLocal API.
     */
    public static function fromApiResponse(array $response): self
    {
        return new self(
            id: (string) $response['id'],
            orderId: (string) $response['order_id'],
            status: DlocalPaymentStatus::from($response['status']),
            statusDetail: $response['status_detail'] ?? null,
            amountInCents: (int) round(((float) $response['amount']) * 100),
            currency: $response['currency'],
            redirectUrl: $response['redirect_url'] ?? null,
        );
    }
}
```

### `RefundRequestData`

```php
<?php

declare(strict_types=1);

namespace Modules\Platform\Integrations\Dlocal\DTOs;

final readonly class RefundRequestData
{
    public function __construct(
        public string $paymentId,
        /** null = full refund */
        public ?int $amountInCents = null,
        public ?string $notificationUrl = null,
    ) {}
}
```

### `RefundResponseData`

```php
<?php

declare(strict_types=1);

namespace Modules\Platform\Integrations\Dlocal\DTOs;

final readonly class RefundResponseData
{
    public function __construct(
        public string $id,
        public string $paymentId,
        public string $status,
        public int $amountInCents,
    ) {}

    public static function fromApiResponse(array $response): self
    {
        return new self(
            id: (string) $response['id'],
            paymentId: (string) $response['payment_id'],
            status: $response['status'],
            amountInCents: (int) round(((float) $response['amount']) * 100),
        );
    }
}
```

---

## Contract

### `PaymentGatewayContract`

```php
<?php

declare(strict_types=1);

namespace Modules\Platform\Integrations\Dlocal\Contracts;

use Modules\Platform\Integrations\Dlocal\DTOs\PaymentRequestData;
use Modules\Platform\Integrations\Dlocal\DTOs\PaymentResponseData;
use Modules\Platform\Integrations\Dlocal\DTOs\RefundRequestData;
use Modules\Platform\Integrations\Dlocal\DTOs\RefundResponseData;
use Modules\Platform\Integrations\Dlocal\Exceptions\DlocalApiException;

interface PaymentGatewayContract
{
    /** @throws DlocalApiException */
    public function createPayment(PaymentRequestData $data): PaymentResponseData;

    /** @throws DlocalApiException */
    public function retrievePayment(string $paymentId): PaymentResponseData;

    /** @throws DlocalApiException */
    public function refund(RefundRequestData $data): RefundResponseData;
}
```

---

## Client

Encapsulates HMAC signing and HTTP transport. No Action, not even `DlocalPaymentGateway`, should generate the signature directly.

### `DlocalHttpClient`

```php
<?php

declare(strict_types=1);

namespace Modules\Platform\Integrations\Dlocal\Client;

use Illuminate\Support\Facades\Http;
use Modules\Platform\Integrations\Dlocal\Exceptions\DlocalApiException;

final class DlocalHttpClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $login,
        private readonly string $transKey,
        private readonly string $secretKey,
    ) {}

    public function post(string $path, array $payload): array
    {
        return $this->send('post', $path, json_encode($payload, JSON_THROW_ON_ERROR), $payload);
    }

    public function get(string $path): array
    {
        return $this->send('get', $path, '', []);
    }

    private function send(string $method, string $path, string $body, array $payload = []): array
    {
        $date = now()->toIso8601String();

        $request = Http::withHeaders([
            'X-Date' => $date,
            'X-Login' => $this->login,
            'X-Trans-Key' => $this->transKey,
            'X-Version' => '2.1',
            'Content-Type' => 'application/json',
            'Authorization' => 'V2-HMAC-SHA256, Signature: '.$this->signature($date, $body),
        ]);

        $response = $method === 'post'
            ? $request->post($this->baseUrl.$path, $payload)
            : $request->get($this->baseUrl.$path);

        if ($response->failed()) {
            $error = $response->json() ?? [];

            throw new DlocalApiException(
                message: $error['message'] ?? 'dLocal API error',
                dlocalCode: $error['code'] ?? null,
                context: $error,
            );
        }

        return $response->json();
    }

    private function signature(string $date, string $body): string
    {
        return hash_hmac('sha256', $this->login.$date.$body, $this->secretKey);
    }
}
```

---

## Gateway

### `DlocalPaymentGateway`

```php
<?php

declare(strict_types=1);

namespace Modules\Platform\Integrations\Dlocal;

use Modules\Platform\Integrations\Dlocal\Client\DlocalHttpClient;
use Modules\Platform\Integrations\Dlocal\Contracts\PaymentGatewayContract;
use Modules\Platform\Integrations\Dlocal\DTOs\PaymentRequestData;
use Modules\Platform\Integrations\Dlocal\DTOs\PaymentResponseData;
use Modules\Platform\Integrations\Dlocal\DTOs\RefundRequestData;
use Modules\Platform\Integrations\Dlocal\DTOs\RefundResponseData;

final class DlocalPaymentGateway implements PaymentGatewayContract
{
    public function __construct(
        private readonly DlocalHttpClient $client,
    ) {}

    public function createPayment(PaymentRequestData $data): PaymentResponseData
    {
        $response = $this->client->post('/payments', [
            'order_id' => $data->orderId,
            'amount' => $this->toDecimal($data->amountInCents),
            'currency' => $data->currency,
            'country' => $data->country,
            'payment_method_flow' => $data->flow->value,
            'payer' => $data->payer->toArray(),
            'token' => $data->token,
            'notification_url' => $data->notificationUrl,
        ]);

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
```

---

## Pending Items

These components are not included in this document yet and will be addressed separately:

- **Provider binding** (`PaymentGatewayContract::class → DlocalPaymentGateway::class`) — depends on the credential configuration (`config/dlocal.php`) which has not been defined yet.
- **Webhook signature verification** (`DlocalSignatureVerifier`) — a separate component; the Contract covers only outbound API calls.
- **`payment_references` table and resolution Job** — defined in the webhook routing design (see `dLocalRD.md`).
