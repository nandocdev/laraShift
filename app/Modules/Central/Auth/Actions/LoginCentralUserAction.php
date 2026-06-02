<?php

declare(strict_types=1);

namespace App\Modules\Central\Auth\Actions;

use App\Modules\Central\Auth\DTOs\LoginData;
use App\Modules\Central\Auth\Models\CentralUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

final readonly class LoginCentralUserAction {
    /**
     * Execute the login action for a CentralUser.
     * 
     * Returns:
     * - 'success': Logged in.
     * - 'requires_2fa': Credentials valid, but 2FA needed.
     * - 'failed': Invalid credentials.
     */
    public function execute(LoginData $data): string {
        $user = CentralUser::where('email', $data->email)->first();

        if (! $user || ! Hash::check($data->password, $user->password)) {
            activity('auth')
                ->withProperties(['email' => $data->email])
                ->log('central_user_login_failed');
                
            return 'failed';
        }

        if ($user->hasTwoFactorEnabled()) {
            Session::put('login.id', $user->id);
            Session::put('login.remember', $data->remember);
            
            return 'requires_2fa';
        }

        Auth::guard('central')->login($user, $data->remember);

        activity('auth')
            ->performedOn($user)
            ->log('central_user_logged_in');

        return 'success';
    }
}
