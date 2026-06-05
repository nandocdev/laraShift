<div class="flex flex-col gap-6 max-w-2xl mx-auto py-12">
    <div>
        <flux:heading size="xl">{{ __('Branding & Identity') }}</flux:heading>
        <flux:subheading>{{ __('Customize how your account looks for your team and clients.') }}</flux:subheading>
    </div>

    @if (session('status'))
        <flux:text color="emerald">{{ session('status') }}</flux:text>
    @endif

    <flux:card>
        <form wire:submit="save" class="flex flex-col gap-6">
            <flux:input wire:model="name" :label="__('Display Name')" placeholder="Acme Corp" required />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
                <div class="space-y-4">
                    <flux:select wire:model.live="theme_preset" :label="__('Theme Preset')">
                        @foreach($this->presets as $key => $preset)
                            <option value="{{ $key }}">{{ $preset['name'] }}</option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="primary_color" type="color" :label="__('Brand Color')" required :disabled="$theme_preset !== 'custom'" />
                </div>
                
                <div>
                    @if($this->logoPreviewUrl)
                        <img src="{{ $this->logoPreviewUrl }}" class="h-12 w-auto mb-2">
                    @elseif($logo_path)
                        <img src="{{ tenant_asset($logo_path) }}" class="h-12 w-auto mb-2">
                    @endif
                    
                    <flux:input wire:model="logo" type="file" :label="__('Upload New Logo')" />
                    <flux:error name="logo" />
                    <flux:text color="zinc" size="xs" class="mt-1">{{ __('Max 2MB. JPG/PNG.') }}</flux:text>
                </div>
            </div>

            <div class="flex justify-end gap-2 mt-4">
                <flux:button type="submit" variant="primary">
                    {{ __('Save Branding') }}
                </flux:button>
            </div>
        </form>
    </flux:card>

    <div class="mt-8">
        <flux:heading size="lg">{{ __('Security Policies') }}</flux:heading>
        <flux:subheading>{{ __('Configure global security requirements for all organization members.') }}</flux:subheading>
    </div>

    <flux:card>
        <div class="flex items-center justify-between">
            <div class="flex flex-col">
                <span class="font-medium text-zinc-900 dark:text-white">{{ __('Mandatory Multi-Factor Authentication') }}</span>
                <span class="text-xs text-zinc-500">{{ __('When enabled, all members will be required to set up MFA to access the dashboard.') }}</span>
            </div>
            <flux:switch wire:model="mfa_required" wire:click="save" />
        </div>
    </flux:card>

    <flux:card class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Public Landing Page') }}</flux:heading>
            <flux:subheading>{{ __('Customize the page that visitors see when they visit your root domain.') }}</flux:subheading>
        </div>

        <div class="flex items-center justify-between p-4 bg-zinc-50 dark:bg-zinc-900 rounded-2xl border border-zinc-100 dark:border-zinc-700">
            <div class="flex items-center gap-4">
                <div class="p-3 bg-primary/10 text-primary rounded-xl">
                    <flux:icon.megaphone size="lg" />
                </div>
                <div>
                    <p class="font-bold text-zinc-800 dark:text-white">{{ __('Visual Builder') }}</p>
                    <p class="text-sm text-zinc-500">{{ __('Drag, drop and edit your marketing site.') }}</p>
                </div>
            </div>

            @php
                $landing = \App\Modules\Central\Landings\Models\Landing::where('tenant_id', tenant('id'))->where('slug', 'saas-landing')->first();
            @endphp

            @if($landing)
                <flux:button href="{{ route('tenant.landings.builder', $landing) }}" variant="primary" icon="pencil-square" target="_blank">
                    {{ __('Open Builder') }}
                </flux:button>
            @else
                <flux:button wire:click="initializeLanding" variant="primary" icon="plus">
                    {{ __('Initialize Landing') }}
                </flux:button>
            @endif
        </div>
    </flux:card>
</div>
