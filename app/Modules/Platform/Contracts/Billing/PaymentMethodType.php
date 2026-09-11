<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts\Billing;

enum PaymentMethodType: string
{
    case Card = 'card';
    case Cash = 'cash';
    case Wallet = 'wallet';
    case BankTransfer = 'bank_transfer';
}
