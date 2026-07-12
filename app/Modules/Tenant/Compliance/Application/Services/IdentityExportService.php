<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Compliance\Application\Services;

use App\Modules\Platform\Contracts\Exportable;
use App\Modules\Tenant\Access\Domain\Models\User;

class IdentityExportService implements Exportable
{
    public function getExportData(): array
    {
        return [
            'users' => User::all()->toArray(),
        ];
    }
}
