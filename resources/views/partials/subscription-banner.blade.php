@php
    $showSubscriptionBanner = false;
    $isGracePeriod = false;
    
    if (function_exists('tenant') && tenant() && tenant('plan_id') !== 'free') {
        $subscription = tenant()->subscription('default');
        if (! $subscription || ! $subscription->active()) {
            if (! $subscription?->onGracePeriod()) {
                $showSubscriptionBanner = true;
            } else {
                $isGracePeriod = true;
            }
        }
    }
@endphp

@if($showSubscriptionBanner)
    <div class="w-full bg-red-600 text-white px-4 py-2 flex items-center justify-between text-sm font-bold shadow-lg">
        <div class="flex items-center gap-2">
            <flux:icon icon="credit-card" variant="solid" />
            <span>{{ __('Your subscription is inactive.') }} {{ __('Please complete your payment to restore full access.') }}</span>
        </div>
        <a href="{{ route('tenant.billing.plans') }}" class="underline hover:text-zinc-200 transition-colors uppercase tracking-widest">
            {{ __('Update Subscription') }}
        </a>
    </div>
@elseif($isGracePeriod)
    <div class="w-full bg-amber-500 text-white px-4 py-2 flex items-center justify-between text-sm font-bold shadow-lg">
        <div class="flex items-center gap-2">
            <flux:icon icon="clock" variant="solid" />
            <span>{{ __('Your subscription has been cancelled but remains active until the end of the billing period.') }}</span>
        </div>
        <a href="{{ route('tenant.billing.plans') }}" class="underline hover:text-zinc-200 transition-colors uppercase tracking-widest">
            {{ __('Renew Subscription') }}
        </a>
    </div>
@endif
