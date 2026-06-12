<?php

declare(strict_types=1);

namespace App\Modules\Shared\Contracts;

interface CentralUserContract
{
    public function getId(): string|int;
    public function getName(): string;
    public function getEmail(): string;
}
