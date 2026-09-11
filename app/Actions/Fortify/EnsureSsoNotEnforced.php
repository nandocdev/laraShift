<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Modules\Tenant\Access\Application\Actions\SsoEnforcement;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

final class EnsureSsoNotEnforced
{
    public function handle(Request $request, Closure $next): mixed
    {
        $email = (string) $request->input(Fortify::username(), '');

        if ($email !== '' && app(SsoEnforcement::class)->isEnforcedFor($email)) {
            throw ValidationException::withMessages([
                Fortify::username() => __('Single Sign-On is enforced for this domain.'),
            ]);
        }

        return $next($request);
    }
}
