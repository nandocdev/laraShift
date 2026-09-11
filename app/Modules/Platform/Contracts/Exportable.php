<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts;

interface Exportable
{
    /**
     * Export data directly to the given file stream handle to prevent OOM errors.
     *
     * @param  resource  $handle
     */
    public function exportToStream($handle): void;
}
