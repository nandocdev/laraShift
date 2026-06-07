<div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
    <div class="text-center mb-12">
        <flux:heading size="xl">{{ __('Change Your Plan') }}</flux:heading>
        <flux:subheading>{{ __('Select the tier that best fits your business needs.') }}</flux:subheading>
    </div>

    @if (session('status'))
        <div class="mb-8 max-w-md mx-auto">
            <flux:text color="emerald" class="text-center">{{ session('status') }}</flux:text>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        @foreach($plans as $plan)
            <flux:card wire:key="plan-{{ $plan->id }}" class="relative flex flex-col p-8 {{ $plan->slug === $currentPlanId ? 'ring-2 ring-primary border-primary' : '' }}">
                @if($plan->slug === $currentPlanId)
                    <div class="absolute -top-4 left-1/2 -translate-x-1/2 px-3 py-1 rounded-full text-xs font-bold text-white bg-primary uppercase tracking-widest shadow-sm">
                        {{ __('Current Plan') }}
                    </div>
                @endif

                <div class="mb-8">
                    <flux:heading size="lg" class="text-2xl mb-1">{{ $plan->name }}</flux:heading>
                    <div class="flex items-baseline gap-1 mt-4">
                        <span class="text-4xl font-extrabold tracking-tight text-zinc-900 dark:text-white">${{ number_format($plan->price_monthly / 100, 2) }}</span>
                        <span class="text-zinc-500 text-sm">/{{ __('month') }}</span>
                    </div>
                </div>

                <ul class="flex-1 space-y-4 mb-8">
                    @foreach($plan->features['display_features'] ?? [] as $feature)
                        <li class="flex items-center gap-3 text-sm text-zinc-600 dark:text-zinc-400">
                            <flux:icon icon="check" class="text-emerald-500 shrink-0" size="sm" />
                            <span>{{ $feature }}</span>
                        </li>
                    @endforeach
                </ul>

                <flux:button 
                    type="button"
                    wire:click="selectPlan('{{ $plan->id }}')"
                    variant="{{ $plan->slug === $currentPlanId ? 'ghost' : 'primary' }}" 
                    class="w-full py-3"
                    :disabled="$plan->slug === $currentPlanId"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="selectPlan">
                        @if($plan->slug === $currentPlanId)
                            {{ __('Selected') }}
                        @else
                            {{ $plan->price_monthly > 0 ? __('Upgrade Now') : __('Select Plan') }}
                        @endif
                    </span>
                    <span wire:loading wire:target="selectPlan">
                        {{ __('Redirecting...') }}
                    </span>
                </flux:button>
            </flux:card>
        @endforeach
    </div>

    <div class="mt-12 text-center">
        <flux:button variant="ghost" :href="route('tenant.billing.manage')" wire:navigate>
            {{ __('Back to Billing') }}
        </flux:button>
    </div>
</div>
