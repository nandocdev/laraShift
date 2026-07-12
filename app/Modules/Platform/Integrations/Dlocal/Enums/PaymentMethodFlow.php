<?php

declare(strict_types=1);

namespace App\Modules\Platform\Integrations\Dlocal\Enums;

enum PaymentMethodFlow: string
{
    case Direct = 'DIRECT';
    case Redirect = 'REDIRECT';
}
