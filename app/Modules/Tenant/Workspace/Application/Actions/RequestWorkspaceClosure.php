<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Workspace\Application\Actions;

use App\Modules\Platform\Contracts\TenantContract;
use App\Modules\Platform\Events\TenantClosureRequested;
use App\Modules\Platform\Security\Mfa\MfaService;
use App\Modules\Tenant\Access\Domain\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final readonly class RequestWorkspaceClosure
{
    public function __construct(private MfaService $mfa) {}

    /**
     * Validates the owner identity (admin role + password + MFA when
     * enabled) and requests the workspace closure. Central-side effects
     * (archive, soft-delete, delayed purge) run in the event listener,
     * never through Central models from here.
     */
    public function execute(User $owner, TenantContract $tenant, string $password, ?string $code = null): void
    {
        if (! $owner->hasRole('admin')) {
            throw new AuthorizationException('Only workspace owners can close the workspace.');
        }

        if (! Hash::check($password, $owner->password)) {
            throw ValidationException::withMessages([
                'password' => __('The provided password is incorrect.'),
            ]);
        }

        if ($owner->mfa_enabled) {
            $secret = $owner->mfa?->secret;

            if (! is_string($secret) || $secret === '' || ! $this->mfa->verify($secret, (string) $code)) {
                throw ValidationException::withMessages([
                    'code' => __('The two-factor code is invalid.'),
                ]);
            }
        }

        if (method_exists($tenant, 'trashed') && $tenant->trashed()) {
            throw ValidationException::withMessages([
                'workspace' => __('This workspace is already closed.'),
            ]);
        }

        TenantClosureRequested::dispatch(
            $tenant,
            (string) $owner->getKey(),
            (int) config('workspace.closure_grace_days', 30),
        );
    }
}
