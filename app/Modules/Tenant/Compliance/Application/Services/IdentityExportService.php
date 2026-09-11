<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Compliance\Application\Services;

use App\Modules\Platform\Contracts\Exportable;
use App\Modules\Tenant\Access\Domain\Models\User;

class IdentityExportService implements Exportable
{
    public function exportToStream($handle): void
    {
        fwrite($handle, '"users":[');
        $first = true;
        foreach (User::cursor() as $user) {
            if (! $first) {
                fwrite($handle, ',');
            }
            fwrite($handle, json_encode($user));
            $first = false;
        }
        fwrite($handle, ']');
    }
}
