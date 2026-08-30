<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Compliance\Infrastructure\Resolvers;

use App\Modules\Tenant\Compliance\Application\Contracts\UserResolverContract;
use App\Modules\Tenant\Access\Domain\Models\User;

final class AccessUserResolver implements UserResolverContract
{
    /**
     * {@inheritDoc}
     */
    public function resolve(string $userId): ?\Illuminate\Database\Eloquent\Model
    {
        return User::find($userId);
    }
}
