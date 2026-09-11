<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Compliance\Application\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Contrato para resolver usuarios desde el módulo Compliance sin acoplamiento directo.
 */
interface UserResolverContract
{
    /**
     * Resuelve un usuario por su ID.
     */
    public function resolve(string $userId): ?Model;
}
