<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Access\Application\Actions;

use App\Modules\Tenant\Access\Domain\Models\TenantSession;
use App\Modules\Tenant\Access\Domain\Models\User;
use Illuminate\Support\Facades\Session;

final readonly class RecordTenantSession
{
    public function execute(User $user, ?string $ip = null, ?string $userAgent = null): TenantSession
    {
        return TenantSession::updateOrCreate(
            ['session_id' => Session::getId()],
            [
                'tenant_id' => tenant('id'),
                'user_id' => $user->getKey(),
                'ip' => $ip,
                'user_agent' => $userAgent !== null ? substr($userAgent, 0, 500) : null,
                'revoked_at' => null,
            ]
        );
    }
}
