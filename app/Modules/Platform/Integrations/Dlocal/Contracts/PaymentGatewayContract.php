<?php

declare(strict_types=1);

namespace App\Modules\Platform\Integrations\Dlocal\Contracts;

use App\Modules\Platform\Integrations\Dlocal\DTOs\PaymentRequestData;
use App\Modules\Platform\Integrations\Dlocal\DTOs\PaymentResponseData;
use App\Modules\Platform\Integrations\Dlocal\DTOs\RefundRequestData;
use App\Modules\Platform\Integrations\Dlocal\DTOs\RefundResponseData;
use App\Modules\Platform\Integrations\Dlocal\Exceptions\DlocalApiException;

interface PaymentGatewayContract
{
    /** @throws DlocalApiException */
    public function createPayment(PaymentRequestData $data): PaymentResponseData;

    /** @throws DlocalApiException */
    public function retrievePayment(string $paymentId): PaymentResponseData;

    /** @throws DlocalApiException */
    public function refund(RefundRequestData $data): RefundResponseData;
}
