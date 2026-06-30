<?php

declare(strict_types=1);

namespace App\Modules\Central\Analytics\Exceptions;

class ExportFailedException extends \RuntimeException
{
    public static function storageFailure(string $disk, string $fileName): self
    {
        return new self("Failed to write export to disk [{$disk}]: {$fileName}");
    }
}
