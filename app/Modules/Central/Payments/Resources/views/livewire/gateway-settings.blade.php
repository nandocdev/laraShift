<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Payment Gateway Settings') }}</flux:heading>
        <flux:subheading>{{ __('Configure and test payment gateway connectivity.') }}</flux:subheading>
    </div>

    @if(session('status'))
        <flux:toast variant="success" :text="session('status')" />
    @endif

    {{-- Active Gateway --}}
    <flux:card class="flex flex-col gap-4">
        <flux:heading size="lg">{{ __('Active Gateway') }}</flux:heading>
        <flux:separator />

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="flex flex-col gap-4">
                <flux:select wire:model.live="gateway" :label="__('Payment Gateway')">
                    <option value="clave">{{ __('Clave (PagueloFacil)') }}</option>
                    <option value="dlocal">{{ __('dLocal') }}</option>
                </flux:select>

                <flux:select wire:model.live="environment" :label="__('Environment')">
                    <option value="sandbox">{{ __('Sandbox') }}</option>
                    <option value="production">{{ __('Production') }}</option>
                </flux:select>

                <flux:button wire:click="testConnection" variant="primary" icon="arrow-path" :loading="$testing">
                    {{ __('Test Connection') }}
                </flux:button>

                @if($testResult)
                    <flux:card class="{{ $testSuccess ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'bg-red-50 dark:bg-red-900/20' }}">
                        <flux:text size="sm" :class="$testSuccess ? 'text-emerald-800' : 'text-red-800'">
                            {{ $testResult }}
                        </flux:text>
                    </flux:card>
                @endif
            </div>

            {{-- Status Overview --}}
            <div class="flex flex-col gap-3">
                <flux:heading size="sm">{{ __('Configuration Status') }}</flux:heading>
                <div class="flex items-center justify-between text-sm">
                    <flux:text variant="subtle">{{ __('Gateway') }}</flux:text>
                    <flux:badge size="sm" color="blue">{{ strtoupper($this->gateway) }}</flux:badge>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <flux:text variant="subtle">{{ __('Environment') }}</flux:text>
                    <flux:badge size="sm" :color="$this->environment === 'production' ? 'amber' : 'zinc'">
                        {{ ucfirst($this->environment) }}
                    </flux:badge>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <flux:text variant="subtle">{{ __('API Key') }}</flux:text>
                    <flux:badge size="sm" :color="$hasApiKey ? 'emerald' : 'red'">
                        {{ $hasApiKey ? __('Configured') : __('Missing') }}
                    </flux:badge>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <flux:text variant="subtle">{{ __('Webhook Secret') }}</flux:text>
                    <flux:badge size="sm" :color="$hasWebhookSecret ? 'emerald' : 'red'">
                        {{ $hasWebhookSecret ? __('Configured') : __('Missing') }}
                    </flux:badge>
                </div>
            </div>
        </div>
    </flux:card>

    {{-- Configuration Details --}}
    <flux:card class="flex flex-col gap-4">
        <flux:heading size="lg">{{ __('Configuration') }}</flux:heading>
        <flux:separator />

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th class="px-4 py-2 text-left font-medium text-zinc-500">{{ __('Key') }}</th>
                        <th class="px-4 py-2 text-left font-medium text-zinc-500">{{ __('Value') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach($gatewayConfig as $key => $value)
                        <tr>
                            <td class="px-4 py-2 font-mono text-xs text-zinc-600">{{ $key }}</td>
                            <td class="px-4 py-2 font-mono text-xs">
                                @if(in_array($key, ['api_key', 'login', 'trans_key', 'secret_key', 'webhook_secret', 'smart_fields']))
                                    {{ $value ? Str::mask($value, '*', 0, max(0, strlen($value) - 8)) : '-' }}
                                @else
                                    {{ $value ?: '-' }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <flux:text size="xs" variant="subtle">
            {{ __('Gateway configuration is managed via environment variables. Changes require a deployment.') }}
        </flux:text>
    </flux:card>
</div>
