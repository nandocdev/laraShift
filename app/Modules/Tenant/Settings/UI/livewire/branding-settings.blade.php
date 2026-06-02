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

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-end">
                <flux:input wire:model="primary_color" type="color" :label="__('Brand Color')" required />
                
                <div>
                    @if($logo)
                        <img src="{{ $logo->temporaryUrl() }}" class="h-12 w-auto mb-2">
                    @elseif($logo_path)
                        <img src="{{ Storage::disk('public')->url($logo_path) }}" class="h-12 w-auto mb-2">
                    @endif
                    
                    <flux:input wire:model="logo" type="file" :label="__('Upload New Logo')" />
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
</div>
