<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Compliance\Infrastructure\Resolvers;

use App\Modules\Tenant\Access\Domain\Models\User;
use App\Modules\Tenant\Compliance\Application\Contracts\UserResolverContract;
use Illuminate\Database\Eloquent\Model;

final readonly class ConfiguredUserResolver implements UserResolverContract
{
    public function resolve(string $userId): ?Model
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = config('compliance.user_model', config('auth.providers.users.model', User::class));

        return $modelClass::query()->find($userId);
    }
}
