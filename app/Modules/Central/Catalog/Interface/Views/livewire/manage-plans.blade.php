<div class="space-y-6" x-on:plan-modal-close.window="$flux.modal('plan-form').close()">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Plans') }}</flux:heading>
            <flux:subheading>{{ __('Catalog of plans, features and quotas offered to tenants.') }}</flux:subheading>
        </div>
        <flux:modal.trigger name="plan-form">
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add plan') }}</flux:button>
        </flux:modal.trigger>
    </div>

    @if (session()->has('status'))
        <flux:text color="green">{{ session('status') }}</flux:text>
    @endif

    <flux:card class="overflow-hidden">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Slug') }}</flux:table.column>
                <flux:table.column>{{ __('Features / quotas') }}</flux:table.column>
                <flux:table.column>{{ __('Monthly') }}</flux:table.column>
                <flux:table.column>{{ __('Yearly') }}</flux:table.column>
                <flux:table.column>{{ __('Active') }}</flux:table.column>
                <flux:table.column>{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse($plans as $plan)
                    <flux:table.row :key="$plan->id">
                        <flux:table.cell>{{ $plan->name }}</flux:table.cell>
                        <flux:table.cell>{{ $plan->slug }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap gap-1">
                                @foreach ($plan->features['display_features'] ?? [] as $feature)
                                    <flux:badge size="sm" variant="solid">{{ $feature }}</flux:badge>
                                @endforeach
                                @foreach ($plan->features['quotas'] ?? [] as $metric => $limit)
                                    <flux:badge size="sm" variant="outline">{{ $metric }}: {{ $limit }}</flux:badge>
                                @endforeach
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ number_format($plan->price_monthly / 100, 2) }} {{ $plan->currency }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($plan->price_yearly / 100, 2) }} {{ $plan->currency }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" variant="outline">{{ $plan->is_active ? __('Yes') : __('No') }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex justify-end gap-1">
                                <flux:button size="sm" variant="ghost" icon="pencil" wire:click="edit('{{ $plan->id }}')" x-on:click="$flux.modal('plan-form').show()" tooltip="{{ __('Edit') }}" />
                                <flux:button size="sm" variant="ghost" icon="power" wire:click="toggleActive('{{ $plan->id }}')" tooltip="{{ $plan->is_active ? __('Deactivate') : __('Activate') }}" />
                                <flux:button size="sm" variant="ghost" icon="archive-box" wire:click="delete('{{ $plan->id }}')" wire:confirm="{{ __('Archive this plan?') }}" tooltip="{{ __('Archive') }}" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7">{{ __('No plans yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        <div class="mt-4">{{ $plans->links() }}</div>
    </flux:card>

    <flux:modal name="plan-form" class="min-w-[40rem]">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? __('Edit plan') : __('New plan') }}</flux:heading>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('Name') }}</flux:label>
                    <flux:input wire:model="name" />
                    <flux:error name="name" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Slug') }}</flux:label>
                    <flux:input wire:model="slug" @disabled($editingId) />
                    <flux:error name="slug" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Monthly price') }}</flux:label>
                    <flux:input wire:model="priceMonthly" type="number" step="0.01" min="0" />
                    <flux:error name="priceMonthly" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Yearly price') }}</flux:label>
                    <flux:input wire:model="priceYearly" type="number" step="0.01" min="0" />
                    <flux:error name="priceYearly" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Currency') }}</flux:label>
                    <flux:input wire:model="currency" maxlength="3" />
                    <flux:error name="currency" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Interval') }}</flux:label>
                    <flux:select wire:model="interval">
                        <flux:select.option value="month">{{ __('Monthly') }}</flux:select.option>
                        <flux:select.option value="year">{{ __('Yearly') }}</flux:select.option>
                    </flux:select>
                    <flux:error name="interval" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Features') }}</flux:label>
                <div class="flex flex-wrap gap-4">
                    @foreach ($featureLabels as $feature => $label)
                        <flux:checkbox wire:model="displayFeatures" value="{{ $feature }}" :label="__($label)" />
                    @endforeach
                </div>
                <flux:error name="displayFeatures" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Quotas (blank = unlimited)') }}</flux:label>
                <div class="grid grid-cols-3 gap-4">
                    @foreach ($quotaLabels as $metric => $label)
                        <flux:field>
                            <flux:label>{{ __($label) }}</flux:label>
                            <flux:input wire:model="quotas.{{ $metric }}" type="number" min="0" placeholder="{{ __('Unlimited') }}" />
                            <flux:error name="quotas.{{ $metric }}" />
                        </flux:field>
                    @endforeach
                </div>
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('Clave service ID (optional)') }}</flux:label>
                    <flux:input wire:model="gatewayClave" />
                    <flux:error name="gatewayClave" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('dLocal plan ref (optional)') }}</flux:label>
                    <flux:input wire:model="gatewayDlocal" />
                    <flux:error name="gatewayDlocal" />
                </flux:field>
            </div>

            <flux:field>
                <flux:checkbox wire:model="isActive" :label="__('Active')" />
            </flux:field>

            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">{{ $editingId ? __('Update plan') : __('Create plan') }}</flux:button>
                <flux:modal.close>
                    <flux:button variant="ghost" wire:click="cancelEdit">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
            </div>
        </form>
    </flux:modal>
</div>
