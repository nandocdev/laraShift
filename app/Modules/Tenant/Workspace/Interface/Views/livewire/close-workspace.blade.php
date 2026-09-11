<div class="max-w-2xl mx-auto py-12">
    <div class="mb-8">
        <flux:heading size="xl">{{ __('Danger zone') }}</flux:heading>
        <flux:subheading>{{ __('Closing the workspace is irreversible after the grace period.') }}</flux:subheading>
    </div>

    @if ($closed)
        <flux:card>
            <flux:heading size="lg">{{ __('Workspace closed') }}</flux:heading>
            <flux:text>{{ __('Your workspace is now inaccessible. Physical deletion is scheduled after the legal grace period.') }}</flux:text>
            <div class="pt-4">
                <flux:button variant="primary" :href="$centralUrl" data-navigate="false">
                    {{ __('Back to home') }}
                </flux:button>
            </div>
        </flux:card>
    @else
        <flux:card class="flex flex-col gap-4">
            <flux:text>
                {{ __('Download a final backup first:') }}
                <flux:link :href="route('tenant.settings.export')" wire:navigate>{{ __('export your data') }}</flux:link>
            </flux:text>

            <div>
                <flux:label>{{ __('Type the workspace slug to confirm (:slug)', ['slug' => tenant()->slug]) }}</flux:label>
                <flux:input wire:model="confirmSlug" autocomplete="off" />
                <flux:error name="confirmSlug" />
            </div>

            <div>
                <flux:label>{{ __('Password') }}</flux:label>
                <flux:input wire:model="password" type="password" autocomplete="current-password" />
                <flux:error name="password" />
            </div>

            @if ($mfaEnabled)
                <div>
                    <flux:label>{{ __('Two-factor code') }}</flux:label>
                    <flux:input wire:model="code" inputmode="numeric" autocomplete="one-time-code" />
                    <flux:error name="code" />
                </div>
            @endif

            <flux:error name="workspace" />

            <div class="flex justify-end">
                <flux:button wire:click="close" variant="danger" wire:loading.attr="disabled">
                    {{ __('Close workspace permanently') }}
                </flux:button>
            </div>
        </flux:card>
    @endif
</div>
