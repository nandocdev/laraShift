<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Actions;

use App\Modules\Tenant\Identity\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

final readonly class ImpersonateTenantUserAction
{
    /**
     * Start impersonating a tenant user.
     * Returns the redirect URL.
     */
    public function execute(User $target, User $impersonator, string $reason): string
    {
        if (! $impersonator->hasRole('admin') && ! $impersonator->hasRole('Owner')) {
            throw new \RuntimeException('Only admins can impersonate users.');
        }

        if ($target->id === $impersonator->id) {
            throw new \RuntimeException('Cannot impersonate yourself.');
        }

        if ($target->tenant_id !== $impersonator->tenant_id) {
            abort(404);
        }

        Session::put('impersonate_target_id', $target->id);
        Session::put('impersonate_by_id', $impersonator->id);
        Session::put('impersonate_reason', $reason);
        Session::put('impersonate_started_at', now()->toIso8601String());

        Auth::login($target);

        activity('auth')
            ->performedOn($target)
            ->causedBy($impersonator)
            ->withProperties([
                'reason' => $reason,
                'impersonator_id' => $impersonator->id,
                'target_id' => $target->id,
            ])
            ->log('tenant_impersonation_started');

        return route('dashboard');
    }

    /**
     * Stop impersonating and return to the impersonator's session.
     */
    public function revert(): ?string
    {
        $impersonatorId = Session::pull('impersonate_by_id');
        $targetId = Session::pull('impersonate_target_id');
        Session::pull('impersonate_reason');
        Session::pull('impersonate_started_at');

        if (! $impersonatorId) {
            return null;
        }

        $impersonator = User::find($impersonatorId);

        if (! $impersonator) {
            Auth::logout();
            Session::invalidate();

            return route('login');
        }

        Auth::login($impersonator);

        if ($targetId) {
            $target = User::find($targetId);

            activity('auth')
                ->performedOn($target)
                ->causedBy($impersonator)
                ->log('tenant_impersonation_ended');
        }

        return route('dashboard');
    }

    public static function isActive(): bool
    {
        return Session::has('impersonate_target_id');
    }

    public static function currentTargetId(): ?string
    {
        return Session::get('impersonate_target_id');
    }
}
