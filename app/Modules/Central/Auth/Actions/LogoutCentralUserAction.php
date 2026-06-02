<?php

declare(strict_types=1);

namespace App\Modules\Central\Auth\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

final readonly class LogoutCentralUserAction
{
    /**
     * Procesa el cierre de sesión para el área central.
     */
    public function execute(): void
    {
        Auth::guard('central')->logout();

        Session::invalidate();
        Session::regenerateToken();
    }
}
