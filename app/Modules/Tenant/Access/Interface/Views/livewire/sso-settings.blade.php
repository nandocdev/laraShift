<div class="max-w-2xl mx-auto py-12">
    <div class="mb-8">
        <flux:heading size="xl">{{ __('SAML 2.0 / OIDC Configuration') }}</flux:heading>
        <flux:subheading>{{ __('Configure your corporate Single Sign-On Identity Provider.') }}</flux:subheading>
    </div>

    <flux:card>
        <form wire:submit="save" class="space-y-6">
            <div class="space-y-4">
                <flux:input wire:model="idp_entity_id" label="{{ __('Entity ID') }}" placeholder="https://idp.example.com/metadata" />
                <flux:input wire:model="idp_sso_url" label="{{ __('Single Sign-On URL') }}" placeholder="https://idp.example.com/sso" />
                <flux:textarea wire:model="idp_x509_cert" label="{{ __('X.509 Certificate') }}" rows="6" placeholder="-----BEGIN CERTIFICATE-----..." />
                <flux:input wire:model="enforced_domains" label="{{ __('Enforced Domains (comma separated)') }}" placeholder="example.com, corp.example.com" />
                
                <div class="pt-4 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:switch wire:model="is_forced" label="{{ __('Enforce SSO for these domains') }}" description="{{ __('Users with these domains will only be able to log in via SSO.') }}" />
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                @if ($setting && $setting->id)
                    <flux:button href="{{ route('saml.login') }}" variant="danger">
                        {{ __('Test Connection') }}
                    </flux:button>
                @endif
                
                <flux:button type="submit" variant="primary">
                    {{ __('Save') }}
                </flux:button>
            </div>
        </form>
    </flux:card>
</div>
