<?php

declare(strict_types=1);

use App\Modules\Platform\UI\DesignSystem;

test('returns theme colors', function () {
    $colors = DesignSystem::getThemeColors();

    expect($colors)->toHaveKeys(['primary', 'secondary', 'danger', 'success']);
});
