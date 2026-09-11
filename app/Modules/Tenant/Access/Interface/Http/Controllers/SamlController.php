<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Access\Interface\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenant\Access\Application\Actions\EnsureTenantRolesExist;
use App\Modules\Tenant\Access\Domain\Models\SsoSetting;
use App\Modules\Tenant\Access\Domain\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

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
        } catch (InvalidStateException $e) {
            Log::warning('saml.invalid_state', ['tenant_id' => tenant('id')]);

            return redirect()->route('login')->withErrors(['email' => __('SAML Authentication failed')]);
        } catch (\Throwable $e) {
            Log::warning('saml.callback_failed', ['tenant_id' => tenant('id'), 'error' => $e->getMessage()]);

            return redirect()->route('login')->withErrors(['email' => __('SAML Authentication failed')]);
        }

        $email = $samlUser->getEmail();
        if (! $email) {
            return redirect()->route('login')->withErrors(['email' => __('No email received from IdP')]);
        }

        $tenantId = tenant('id');

        $user = User::where('email', $email)->first();

        if (! $user) {
            app(EnsureTenantRolesExist::class)->execute(tenancy()->tenant);
            setPermissionsTeamId($tenantId);
            $user = User::create([
                'tenant_id' => $tenantId,
                'name' => $samlUser->getName() ?: 'SAML User',
                'email' => $email,
                'password' => Hash::make(Str::random(32)),
                'status' => 'active',
            ]);
            $user->assignRole('member');
        }

        if (! $user->is_active) {
            return redirect()->route('login')->withErrors(['email' => __('auth.failed')]);
        }

        Auth::guard('web')->login($user);
        Session::regenerate();

        $ssoSetting = SsoSetting::first();
        if ($ssoSetting && ! $ssoSetting->is_tested) {
            $ssoSetting->update(['is_tested' => true]);
        }

        activity('auth')
            ->performedOn($user)
            ->log('tenant_user_logged_in_saml');

        return redirect()->intended(route('dashboard'));
    }

    public function metadata(): Response
    {
        $entityId = route('saml.metadata');
        $acs = route('saml.acs');

        $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <md:EntityDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata" entityID="{$entityId}">
          <md:SPSSODescriptor protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">
            <md:AssertionConsumerService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST" Location="{$acs}" index="0"/>
          </md:SPSSODescriptor>
        </md:EntityDescriptor>
        XML;

        return response($xml, 200, ['Content-Type' => 'application/xml']);
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
