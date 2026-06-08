<?php

declare(strict_types=1);

namespace App\Modules\Shared\Contracts;

interface Exportable
{
    /**
     * Get the data to be exported by this module.
     */
    public function getExportData(): array;
}
