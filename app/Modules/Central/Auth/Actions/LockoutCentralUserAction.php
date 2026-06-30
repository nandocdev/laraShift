<?php

declare(strict_types=1);

namespace App\Modules\Central\Auth\Actions;

use App\Modules\Central\Auth\Models\CentralUser;
use Illuminate\Support\Facades\Cache;

final readonly class LockoutCentralUserAction {
    private const CACHE_PREFIX = 'central_login_attempts:';

    private const MAX_ATTEMPTS = 5;

    private const LOCKOUT_MINUTES = 15;

    public function recordAttempt(string $email): int {
        $key = $this->cacheKey($email);
        $attempts = (int) Cache::get($key, 0) + 1;

        Cache::put($key, $attempts, now()->addMinutes(self::LOCKOUT_MINUTES));

        if ($attempts >= self::MAX_ATTEMPTS) {
            $user = CentralUser::where('email', $email)->first();

            if ($user) {
                $user->update(['locked_until' => now()->addMinutes(self::LOCKOUT_MINUTES)]);

                activity('auth')
                    ->performedOn($user)
                    ->withProperties(['attempts' => $attempts])
                    ->log('central_user_account_locked');
            }
        }

        return $attempts;
    }

    public function isLocked(string $email): bool {
        $key = $this->cacheKey($email);
        $attempts = (int) Cache::get($key, 0);

        if ($attempts >= self::MAX_ATTEMPTS) {
            return true;
        }

        $user = CentralUser::where('email', $email)->first();

        if ($user && $user->locked_until && $user->locked_until->isFuture()) {
            return true;
        }

        return false;
    }

    public function remainingAttempts(string $email): int {
        $key = $this->cacheKey($email);
        $attempts = (int) Cache::get($key, 0);

        return max(0, self::MAX_ATTEMPTS - $attempts);
    }

    public function clearAttempts(string $email): void {
        Cache::forget($this->cacheKey($email));
    }

    public function clearLockout(string $email): void {
        $this->clearAttempts($email);

        $user = CentralUser::where('email', $email)->first();

        if ($user) {
            $user->update(['locked_until' => null]);

            activity('auth')
                ->performedOn($user)
                ->log('central_user_account_unlocked');
        }
    }

    private function cacheKey(string $email): string {
        return self::CACHE_PREFIX . strtolower(trim($email));
    }
}
