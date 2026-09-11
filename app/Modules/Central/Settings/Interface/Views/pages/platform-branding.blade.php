<div class="space-y-6 max-w-2xl">
    <div>
        <flux:heading size="xl">{{ __('Platform Branding') }}</flux:heading>
        <flux:subheading>{{ __('Customize the look and feel of the central administration panel.') }}</flux:subheading>
    </div>

    <flux:separator />

    @if (session('status'))
        <flux:text color="emerald">{{ session('status') }}</flux:text>
    @endif

    <flux:card>
        <form wire:submit="save" class="space-y-6">
            <flux:field>
                <flux:label>{{ __('Platform Name') }}</flux:label>
                <flux:input wire:model="platformName" />
                <flux:error name="platformName" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Primary Color') }}</flux:label>
                <flux:input wire:model="primaryColor" type="color" class="h-10 w-20 p-1" />
                <flux:error name="primaryColor" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Logo URL') }}</flux:label>
                <flux:input wire:model="logoUrl" type="text" placeholder="https://example.com/logo.png" />
                <flux:error name="logoUrl" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Upload Logo (Optional)') }}</flux:label>
                <flux:input type="file" wire:model="logoImage" accept="image/*" />
                <flux:error name="logoImage" />

                @if ($logoImage)
                    <div class="mt-2">
                        <img src="{{ $logoImage->temporaryUrl() }}" class="h-12 object-contain rounded">
                    </div>
                @elseif ($logoUrl)
                    <div class="mt-2">
                        <img src="{{ $logoUrl }}" class="h-12 object-contain rounded">
                    </div>
                @endif
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Favicon URL') }}</flux:label>
                <flux:input wire:model="faviconUrl" type="text" placeholder="https://example.com/favicon.png" />
                <flux:error name="faviconUrl" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Upload Favicon (Optional)') }}</flux:label>
                <flux:input type="file" wire:model="faviconImage" accept="image/*" />
                <flux:error name="faviconImage" />

                @if ($faviconImage)
                    <div class="mt-2">
                        <img src="{{ $faviconImage->temporaryUrl() }}" class="h-8 w-8 object-contain rounded">
                    </div>
                @elseif ($faviconUrl)
                    <div class="mt-2">
                        <img src="{{ $faviconUrl }}" class="h-8 w-8 object-contain rounded">
                    </div>
                @endif
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="resetToDefaults" variant="ghost" wire:confirm="{{ __('Reset branding to defaults?') }}">{{ __('Reset') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Save Changes') }}</flux:button>
            </div>
        </form>
    </flux:card>

    <flux:card>
        <flux:heading size="sm" class="font-bold uppercase tracking-wider text-xs text-zinc-500 mb-4">{{ __('Preview') }}</flux:heading>
        <div class="rounded border border-zinc-200 dark:border-zinc-800 overflow-hidden">
            <div class="flex items-center gap-3 px-4 py-3" style="background-color: {{ $primaryColor }}">
                @if ($faviconUrl)
                    <img src="{{ $faviconUrl }}" class="h-6 w-6 object-contain rounded bg-white/90 p-0.5">
                @endif
                <span class="font-bold text-white">{{ $platformName }}</span>
            </div>
            <div class="flex gap-4 px-4 py-3 text-sm bg-zinc-50 dark:bg-zinc-900">
                <span class="font-semibold" style="color: {{ $primaryColor }}">{{ __('Dashboard') }}</span>
                <span class="text-zinc-500">{{ __('Tenants') }}</span>
                <span class="text-zinc-500">{{ __('Subscriptions') }}</span>
            </div>
            <div class="px-4 py-4">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" class="h-10 object-contain">
                @else
                    <span class="text-sm text-zinc-400">{{ __('No logo configured') }}</span>
                @endif
            </div>
        </div>
    </flux:card>
</div>
