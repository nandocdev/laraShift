<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Exceptions;

final class SlugAlreadyExistsException extends ProvisioningException
{
    public function __construct(string $slug)
    {
        parent::__construct("Slug '{$slug}' is already taken.", 409);
    }
}
