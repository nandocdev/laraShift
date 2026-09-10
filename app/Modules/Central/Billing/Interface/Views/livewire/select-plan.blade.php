<div class="flex flex-col gap-6 py-12">
    <div>
        <flux:heading size="xl">{{ __('Plans') }}</flux:heading>
        <flux:subheading>
            @if ($gatewayName === 'clave')
                {{ __('Pay per cycle through Clave. We will email you a renewal link before each period ends.') }}
            @else
                {{ __('Pay with card. Card subscriptions renew automatically.') }}
            @endif
        </flux:subheading>
    </div>

    @if (session('status'))
        <flux:text color="emerald">{{ session('status') }}</flux:text>
    @endif

    <div class="grid gap-6 md:grid-cols-2">
        @foreach ($plans as $plan)
            <flux:card :key="$plan->id" class="flex flex-col gap-4">
                <div class="flex items-baseline justify-between">
                    <flux:heading size="lg">{{ $plan->name }}</flux:heading>
                    <flux:text class="text-2xl font-bold">
                        {{ number_format($plan->price_monthly / 100, 2) }} {{ $plan->currency }}
                    </flux:text>
                </div>

                <ul class="flex flex-col gap-1">
                    @foreach ($plan->features['display_features'] ?? [] as $feature)
                        <li class="text-sm text-zinc-600 dark:text-zinc-400">· {{ $feature }}</li>
                    @endforeach
                </ul>

                @if ($autoRenew)
                    <flux:text class="text-xs">{{ __('Auto-renews on your card.') }}</flux:text>
                @endif

                <div class="flex gap-2">
                    @if ($gatewayName === 'clave')
                        <flux:button variant="primary" wire:click="checkout('{{ $plan->slug }}')" wire:loading.attr="disabled">
                            {{ __('Pay now') }}
                        </flux:button>
                    @else
                        @if ($directCard)
                            <flux:button variant="primary" :href="route('tenant.billing.checkout.hosted', $plan->slug)" wire:navigate>
                                {{ __('Pay with card') }}
                            </flux:button>
                        @endif
                        <flux:button variant="ghost" wire:click="checkout('{{ $plan->slug }}')" wire:loading.attr="disabled">
                            {{ __('Single checkout') }}
                        </flux:button>
                    @endif
                </div>
            </flux:card>
        @endforeach
    </div>
</div>
