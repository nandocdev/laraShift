<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Services;

use Money\Currencies\ISOCurrencies;
use Money\Formatter\IntlMoneyFormatter;
use Money\Money;
use NumberFormatter;

class PriceFormatter
{
    public static function format(Money $money, string $locale = 'en_US'): string
    {
        $currencies = new ISOCurrencies();
        $numberFormatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
        $moneyFormatter = new IntlMoneyFormatter($numberFormatter, $currencies);

        return $moneyFormatter->format($money);
    }
}
