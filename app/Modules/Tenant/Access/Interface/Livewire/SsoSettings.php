<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Access\Interface\Livewire;

use App\Modules\Tenant\Access\Domain\Models\SsoSetting;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.tenant')]
class SsoSettings extends Component
{
    public ?SsoSetting $setting = null;

    public string $idp_entity_id = '';

    public string $idp_sso_url = '';

    public string $idp_x509_cert = '';

    public string $enforced_domains = '';

    public bool $is_forced = false;

    public bool $is_tested = false;

    public function mount(): void
    {
        $this->setting = SsoSetting::first();

        if ($this->setting) {
            $this->idp_entity_id = $this->setting->idp_entity_id ?? '';
            $this->idp_sso_url = $this->setting->idp_sso_url ?? '';
            $this->idp_x509_cert = $this->setting->idp_x509_cert ?? '';
            $this->enforced_domains = implode(', ', $this->setting->enforced_domains ?? []);
            $this->is_forced = $this->setting->is_forced;
            $this->is_tested = $this->setting->is_tested;
        }
    }

    public function save(): void
    {
        $this->validate([
            'idp_entity_id' => 'required|url',
            'idp_sso_url' => 'required|url',
            'idp_x509_cert' => 'required|string',
            'enforced_domains' => 'nullable|string',
            'is_forced' => 'boolean',
        ]);

        $domains = array_filter(array_map('trim', explode(',', $this->enforced_domains)));

        if ($this->is_forced && ! $this->is_tested) {
            $this->addError('is_forced', __('You must test the SSO connection before enforcing it.'));

            return;
        }

        if (! $this->setting) {
            $this->setting = new SsoSetting;
        }

        $this->setting->idp_entity_id = $this->idp_entity_id;
        $this->setting->idp_sso_url = $this->idp_sso_url;
        $this->setting->idp_x509_cert = $this->idp_x509_cert;
        $this->setting->enforced_domains = $domains;
        $this->setting->is_forced = $this->is_forced;

        $this->setting->save();

        session()->flash('status', __('SSO Settings saved successfully.'));
    }

    public function render(): View
    {
        return view('identity::livewire.sso-settings');
    }
}
