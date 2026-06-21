<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Money\Currency;
use Money\Money;

class MoneyCast implements CastsAttributes
{
    /**
     * The name of the currency column.
     */
    protected string $currencyColumn;

    public function __construct(string $currencyColumn = 'currency')
    {
        $this->currencyColumn = $currencyColumn;
    }

    /**
     * Cast the given value.
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        $currency = $attributes[$this->currencyColumn] ?? 'USD';

        // The Money library requires an integer or a string representing an integer (minor units/cents).
        // If the database accidentally has a decimal (e.g. 29.99), this would fail.
        // We ensure we pass a clean string to the constructor.
        if (is_float($value) || (is_string($value) && str_contains($value, '.'))) {
            // Safety: if it looks like a decimal, we convert to cents to prevent hard failure,
            // but this indicates a schema/model mismatch.
            $value = (string) (int) round(((float) $value) * 100);
        }

        return new Money((string) $value, new Currency($currency));
    }

    /**
     * Prepare the given value for storage.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Money) {
            return $value->getAmount();
        }

        return $value;
    }
}
