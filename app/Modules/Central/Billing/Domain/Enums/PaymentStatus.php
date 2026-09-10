<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Domain\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';
    case Refunded = 'refunded';
    case Expired = 'expired';
}
