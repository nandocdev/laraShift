<?php

declare(strict_types=1);

namespace App\Modules\Central\Auth\Http\Controllers;

use App\Modules\Central\Auth\Actions\LogoutCentralUserAction;
use Illuminate\Http\RedirectResponse;

class LogoutController
{
    public function __invoke(LogoutCentralUserAction $action): RedirectResponse
    {
        $action->execute();

        return redirect('/');
    }
}
