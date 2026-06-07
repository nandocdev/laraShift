<div class="max-w-2xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
    <div class="mb-8 text-center">
        <flux:heading size="xl">{{ __('Subscription Checkout') }}</flux:heading>
        <flux:subheading>{{ __('Complete your subscription to :plan', ['plan' => $plan->name]) }}</flux:subheading>
    </div>

    <flux:card class="p-0 overflow-hidden">
        <livewire:payments.checkout 
            :amount="$plan->amount"
            :description="'Subscription to ' . $plan->name"
            :display-id="'sub_' . $tenant->id"
            :email="$tenant->email"
            :custom-field-values="[
                'type' => 'subscription',
                'plan_id' => $plan->id,
                'tenant_id' => $tenant->id
            ]"
        />
    </flux:card>

    <div class="mt-8 text-center">
        <flux:button variant="ghost" :href="route('tenant.billing.plans')" wire:navigate>
            {{ __('Cancel and Go Back') }}
        </flux:button>
    </div>
</div>
