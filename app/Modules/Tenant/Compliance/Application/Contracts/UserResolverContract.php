<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Compliance\Application\Contracts;

/**
 * Contrato para resolver usuarios desde el módulo Compliance sin acoplamiento directo.
 */
interface UserResolverContract
{
    /**
     * Resuelve un usuario por su ID.
     *
     * @param string $userId
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function resolve(string $userId): ?\Illuminate\Database\Eloquent\Model;
}
