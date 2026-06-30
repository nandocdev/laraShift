<?php

declare(strict_types=1);

namespace App\Modules\Shared\Contracts;

interface StoragePort
{
    public function put(string $path, string $contents, array $options = []): bool;

    public function get(string $path): ?string;

    public function exists(string $path): bool;

    public function delete(string $path): bool;

    public function url(string $path): string;

    public function temporaryUrl(string $path, \DateTimeInterface $expiration): string;
}
