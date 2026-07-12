<div class="space-y-6 max-w-2xl">
    <div>
        <flux:heading size="xl">{{ __('Platform Branding') }}</flux:heading>
        <flux:subheading>{{ __('Customize the look and feel of the central administration panel.') }}</flux:subheading>
    </div>

    <flux:separator />

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
                <flux:input wire:model="logoUrl" type="url" placeholder="https://example.com/logo.png" />
                <flux:error name="logoUrl" />
            </flux:field>

            <div class="flex justify-end">
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:card>
</div>
