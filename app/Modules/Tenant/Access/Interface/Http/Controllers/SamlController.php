<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Access\Interface\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenant\Access\Domain\Models\SsoSetting;
use App\Modules\Tenant\Access\Domain\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Laravel\Socialite\Facades\Socialite;

class SamlController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $this->configureSamlProvider();

        return Socialite::driver('saml2')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $this->configureSamlProvider();

        try {
            $samlUser = Socialite::driver('saml2')->stateless()->user();

            // Basic mapping logic
            $email = $samlUser->getEmail();
            if (! $email) {
                return redirect()->route('login')->withErrors(['email' => __('No email received from IdP')]);
            }

            $user = User::where('email', $email)->first();

            if (! $user) {
                // JIT Provisioning (minimal)
                $user = User::create([
                    'name' => $samlUser->getName() ?: 'SAML User',
                    'email' => $email,
                    'password' => bcrypt(str()->random(32)),
                ]);
            }

            Auth::login($user);

            $ssoSetting = SsoSetting::first();
            if ($ssoSetting && ! $ssoSetting->is_tested) {
                $ssoSetting->update(['is_tested' => true]);
            }

            return redirect()->intended(route('dashboard'));
        } catch (\Exception $e) {
            return redirect()->route('login')->withErrors(['email' => __('SAML Authentication failed')]);
        }
    }

    public function metadata(): Response
    {
        $this->configureSamlProvider();

        // This is pseudo-code for metadata if the provider supports it, otherwise manual XML is needed.
        // For simplicity, we just return a 404 if not natively supported by the socialite manager package.
        abort(404, 'Not Implemented');
    }

    private function configureSamlProvider(): void
    {
        $setting = SsoSetting::first();
        if (! $setting) {
            abort(400, 'SSO Not Configured');
        }

        Config::set('services.saml2', [
            'sp_entityid' => route('saml.metadata'),
            'sp_acs' => route('saml.acs'),
            'idp_entityid' => $setting->idp_entity_id,
            'idp_sso_url' => $setting->idp_sso_url,
            'idp_x509_cert' => $setting->idp_x509_cert,
        ]);
    }
}
