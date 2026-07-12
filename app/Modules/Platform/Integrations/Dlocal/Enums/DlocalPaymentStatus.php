<?php

declare(strict_types=1);

namespace App\Modules\Platform\Integrations\Dlocal\Enums;

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

    public function isFinal(): bool
    {
        return in_array($this, [self::Paid, self::Rejected, self::Cancelled], true);
    }
}
