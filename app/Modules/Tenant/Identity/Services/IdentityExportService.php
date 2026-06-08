<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Services;

use App\Modules\Shared\Contracts\Exportable;
use App\Modules\Tenant\Identity\Models\User;

class IdentityExportService implements Exportable
{
    public function getExportData(): array
    {
        return [
            'users' => User::all()->toArray(),
        ];
    }
}
