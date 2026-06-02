<?php

declare(strict_types=1);

namespace App\Modules\Central\Auth\Actions;

use App\Modules\Central\Auth\DTOs\LoginData;
use Illuminate\Support\Facades\Auth;

final readonly class LoginCentralUserAction {
    /**
     * Execute the login action for a CentralUser.
     *
     * @param LoginData $data
     * @return bool
     */
    public function execute(LoginData $data): bool {
        $attempt = Auth::guard('central')->attempt(
            [
                'email' => $data->email,
                'password' => $data->password,
            ],
            $data->remember
        );

        if ($attempt) {
            activity('auth')
                ->performedOn(Auth::guard('central')->user())
                ->log('central_user_logged_in');
        } else {
            activity('auth')
                ->withProperties(['email' => $data->email])
                ->log('central_user_login_failed');
        }

        return $attempt;
    }
}
