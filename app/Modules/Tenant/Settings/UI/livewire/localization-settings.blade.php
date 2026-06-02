<div class="flex flex-col gap-6 max-w-2xl mx-auto py-12">
    <div>
        <flux:heading size="xl">{{ __('Localization & Regional') }}</flux:heading>
        <flux:subheading>{{ __('Set your preferred timezone, language, and currency.') }}</flux:subheading>
    </div>

    @if (session('status'))
        <flux:text color="emerald">{{ session('status') }}</flux:text>
    @endif

    <flux:card>
        <form wire:submit="save" class="flex flex-col gap-6">
            <flux:select wire:model="timezone" :label="__('Timezone')" searchable>
                @foreach($timezones as $tz)
                    <option value="{{ $tz }}">{{ $tz }}</option>
                @endforeach
            </flux:select>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <flux:select wire:model="locale" :label="__('Language')">
                    @foreach($locales as $code => $label)
                        <option value="{{ $code }}">{{ $label }}</option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="currency" :label="__('Preferred Currency')">
                    @foreach($currencies as $code => $label)
                        <option value="{{ $code }}">{{ $label }}</option>
                    @endforeach
                </flux:select>
            </div>

            <div class="flex justify-end gap-2 mt-4">
                <flux:button type="submit" variant="primary">
                    {{ __('Save Regional Settings') }}
                </flux:button>
            </div>
        </form>
    </flux:card>
    
    <div class="mt-4 p-4 bg-amber-50 border border-amber-200 rounded-lg">
        <flux:text size="sm" color="amber" class="flex items-center gap-2">
            <flux:icon icon="information-circle" size="sm" />
            {{ __('Note: Changing the currency here will affect future transactions. Active subscriptions in Stripe will remain in their original currency.') }}
        </flux:text>
    </div>
</div>
