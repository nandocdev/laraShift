<?php

declare(strict_types=1);

namespace App\Modules\Platform\UI;

class DesignSystem
{
    public static function getThemeColors(): array
    {
        return [
            'primary' => 'indigo',
            'secondary' => 'zinc',
            'danger' => 'red',
            'success' => 'emerald',
        ];
    }
}
