<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Subscription Plans') }}</flux:heading>
            <flux:subheading>{{ __('Manage the commercial matrix and platform tiers.') }}</flux:subheading>
        </div>
        <flux:button :href="route('central.billing.plans.create')" variant="primary" icon="plus" wire:navigate>{{ __('New Plan') }}</flux:button>
    </div>

    @if (session('status'))
        <flux:text color="emerald">{{ session('status') }}</flux:text>
    @endif

    <flux:card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Monthly') }}</flux:table.column>
                <flux:table.column>{{ __('Yearly') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($plans as $plan)
                    <flux:table.row :key="$plan->id">
                        <flux:table.cell class="font-medium">
                            {{ $plan->name }}
                            <div class="text-xs text-neutral-500">{{ $plan->slug }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ number_format($plan->price_monthly / 100, 2) }} USD
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ number_format($plan->price_yearly / 100, 2) }} USD
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :variant="$plan->is_active ? 'success' : 'neutral'">
                                {{ $plan->is_active ? __('ACTIVE') : __('INACTIVE') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    <flux:menu.item :href="route('central.billing.plans.edit', $plan->id)" icon="pencil" wire:navigate>{{ __('Edit') }}</flux:menu.item>
                                    
                                    <flux:modal.trigger name="plan-features">
                                        <flux:menu.item icon="eye" wire:click="showFeatures('{{ $plan->id }}')">{{ __('View Features') }}</flux:menu.item>
                                    </flux:modal.trigger>

                                    <flux:menu.separator />
                                    <flux:menu.item variant="danger" icon="trash" disabled>{{ __('Delete') }}</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal name="plan-features" class="min-w-[25rem]">
        <div class="space-y-6">
            @if($selectedPlan)
                <div>
                    <flux:heading size="lg">{{ __('Features for :name', ['name' => $selectedPlan->name]) }}</flux:heading>
                    <flux:subheading>{{ __('Functional capabilities included in this tier.') }}</flux:subheading>
                </div>

                <div class="space-y-4">
                    <div class="flex flex-wrap gap-1">
                        @forelse($selectedPlan->catalogFeatures as $f)
                            <flux:badge size="sm" variant="outline" class="font-mono text-[10px]">{{ $f->key }}</flux:badge>
                        @empty
                            <flux:text color="zinc" size="sm">{{ __('No functional features assigned to this plan yet.') }}</flux:text>
                        @endforelse
                    </div>

                    @if(isset($selectedPlan->features['quotas']))
                        <div class="border-t border-zinc-100 dark:border-zinc-800 pt-4">
                            <flux:heading size="sm" class="mb-2">{{ __('Technical Quotas') }}</flux:heading>
                            <div class="grid grid-cols-3 gap-2">
                                <div class="bg-zinc-50 dark:bg-zinc-800 p-2 rounded text-center">
                                    <div class="text-[10px] uppercase text-zinc-500">{{ __('Branches') }}</div>
                                    <div class="font-bold">{{ $selectedPlan->features['quotas']['branches'] ?? '0' }}</div>
                                </div>
                                <div class="bg-zinc-50 dark:bg-zinc-800 p-2 rounded text-center">
                                    <div class="text-[10px] uppercase text-zinc-500">{{ __('Staff') }}</div>
                                    <div class="font-bold">{{ $selectedPlan->features['quotas']['staff'] ?? '0' }}</div>
                                </div>
                                <div class="bg-zinc-50 dark:bg-zinc-800 p-2 rounded text-center">
                                    <div class="text-[10px] uppercase text-zinc-500">{{ __('Bookings') }}</div>
                                    <div class="font-bold">{{ $selectedPlan->features['quotas']['bookings'] ?? '0' }}</div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            <div class="flex">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Close') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
