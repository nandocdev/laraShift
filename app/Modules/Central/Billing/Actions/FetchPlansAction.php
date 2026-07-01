<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Actions;

use App\Modules\Central\Billing\Support\PlanManager;

final readonly class FetchPlansAction
{
    public function execute(): array
    {
        return [
            'data' => PlanManager::all(),
        ];
    }
}
