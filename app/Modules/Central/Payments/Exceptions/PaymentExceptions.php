<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Exceptions;

use RuntimeException;

class ClaveGatewayException extends RuntimeException {
}

final class InvalidMerchantException extends ClaveGatewayException {
}

final class ServiceNotFoundException extends ClaveGatewayException {
}

final class WebhookVerificationException extends ClaveGatewayException {
}
