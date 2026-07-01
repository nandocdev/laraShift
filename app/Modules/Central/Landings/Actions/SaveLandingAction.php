<?php

declare(strict_types=1);

namespace App\Modules\Central\Landings\Actions;

use App\Modules\Central\Landings\Models\Landing;

final readonly class SaveLandingAction
{
    public function execute(Landing $landing, array $blocks, array $theme): Landing
    {
        $landing->update([
            'blocks' => $blocks,
            'theme' => $theme,
        ]);

        return $landing;
    }
}
