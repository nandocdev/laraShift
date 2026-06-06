<div class="max-w-xl mx-auto py-12">
    <div class="mb-8 text-center">
        <flux:heading size="xl">{{ __('Secure Checkout') }}</flux:heading>
        <flux:subheading>{{ __('Subscribe to :plan', ['plan' => $plan->name]) }}</flux:subheading>
        <div class="mt-2 text-2xl font-black text-primary">
            {{ Number::currency($plan->amount, $plan->currency ?? 'USD') }} <span class="text-sm font-normal opacity-60">/ {{ $plan->interval }}</span>
        </div>
    </div>

    <flux:card>
        <form wire:submit="process" class="space-y-6">
            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('First Name') }}</flux:label>
                    <flux:input wire:model="firstName" placeholder="John" required />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Last Name') }}</flux:label>
                    <flux:input wire:model="lastName" placeholder="Doe" required />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Card Number') }}</flux:label>
                <flux:input wire:model="cardNumber" placeholder="0000 0000 0000 0000" maxlength="19" required />
            </flux:field>

            <div class="grid grid-cols-3 gap-4">
                <flux:field>
                    <flux:label>{{ __('Month') }} (MM)</flux:label>
                    <flux:input wire:model="expMonth" placeholder="12" maxlength="2" required />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Year') }} (YY)</flux:label>
                    <flux:input wire:model="expYear" placeholder="28" maxlength="2" required />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('CVV') }}</flux:label>
                    <flux:input wire:model="cvv" placeholder="123" maxlength="4" required />
                </flux:field>
            </div>

            @error('payment')
                <div class="p-3 bg-red-50 border border-red-200 rounded-lg">
                    <flux:text color="red" size="sm">{{ $message }}</flux:text>
                </div>
            @enderror

            <div class="pt-4">
                <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled">
                    <span wire:loading.remove>{{ __('Confirm Subscription') }}</span>
                    <span wire:loading>{{ __('Processing...') }}</span>
                </flux:button>
            </div>
            
            <div class="flex items-center justify-center gap-2 mt-4 text-zinc-400">
                <flux:icon.lock-closed size="xs" />
                <span class="text-[10px] uppercase tracking-widest font-bold">{{ __('Secured by PagueloFacil') }}</span>
            </div>
        </form>
    </flux:card>
    
    <div class="mt-6 text-center">
        <flux:button variant="ghost" :href="route('central.billing.plans')">
            {{ __('Cancel and return') }}
        </flux:button>
    </div>
</div>
