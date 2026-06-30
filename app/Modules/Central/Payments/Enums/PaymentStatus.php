<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Failed = 'failed';
    case PartialPayment = 'partial_payment';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Approved, self::Declined, self::Cancelled, self::Refunded, self::Failed, self::PartialPayment], true);
    }

    public function isSuccessful(): bool
    {
        return $this === self::Approved;
    }
}
