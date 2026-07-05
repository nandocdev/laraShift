<div class="flex flex-col gap-6">
    <div class="flex items-center gap-4">
        <flux:button icon="arrow-left" variant="ghost" :href="route('central.provisioning.index')" wire:navigate />
        <div>
            <flux:heading size="xl">{{ __('Feature Overrides for') }} {{ $tenantData->name }}</flux:heading>
            <flux:subheading>{{ __('Manually grant or deny specific functionalities for this tenant.') }}
            </flux:subheading>
        </div>
    </div>

    @if (session('status'))
        <flux:text color="emerald">{{ session('status') }}</flux:text>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- New Override Form -->
        <div class="lg:col-span-1">
            <flux:card class="space-y-6">
                <flux:heading size="lg">{{ __('Apply New Override') }}</flux:heading>

                <form wire:submit="applyOverride" class="space-y-4">
                    <flux:select wire:model="selectedFeatureKey" :label="__('Feature')">
                        <option value="">{{ __('Select a feature...') }}</option>
                        @foreach ($availableFeatures as $f)
                            <option value="{{ $f->key }}">{{ $f->name }} ({{ $f->key }})</option>
                        @endforeach
                    </flux:select>

                    <flux:radio.group wire:model="type" :label="__('Action Type')">
                        <flux:radio value="allow" :label="__('Allow (Grant Access)')" />
                        <flux:radio value="deny" :label="__('Deny (Revoke Access)')" />
                    </flux:radio.group>

                    <flux:input wire:model="expiresAt" type="datetime-local" :label="__('Expiration (Optional)')" />

                    <flux:textarea wire:model="reason" :label="__('Reason / Internal Note')"
                        placeholder="e.g. Beta testing, Support exception..." required />

                    <flux:button type="submit" variant="primary" class="w-full">{{ __('Apply Override') }}
                    </flux:button>
                </form>
            </flux:card>

            <div class="mt-6">
                <flux:card class="bg-zinc-50 dark:bg-zinc-800 border-none shadow-none">
                    <flux:heading size="sm" class="mb-2">{{ __('Effective Feature Set') }}</flux:heading>
                    <div class="flex flex-wrap gap-1">
                        @foreach ($effectiveFeatures as $key)
                            <flux:badge size="sm" variant="outline" class="font-mono text-[10px]">
                                {{ $key }}</flux:badge>
                        @endforeach
                    </div>
                </flux:card>
            </div>
        </div>

        <!-- Active Overrides List -->
        <div class="lg:col-span-2">
            <flux:card class=" overflow-hidden">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Feature') }}</flux:table.column>
                        <flux:table.column>{{ __('Type') }}</flux:table.column>
                        <flux:table.column>{{ __('Expires At') }}</flux:table.column>
                        <flux:table.column>{{ __('Reason') }}</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse($overrides as $override)
                            <flux:table.row :key="$override->id">
                                <flux:table.cell>
                                    <div class="font-medium text-sm">{{ $override->feature->name }}</div>
                                    <div class="text-xs text-zinc-500 font-mono">{{ $override->feature->key }}</div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm"
                                        :variant="$override->type === 'allow' ? 'success' : 'danger'">
                                        {{ strtoupper($override->type) }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <span
                                        class="text-sm {{ $override->expires_at?->isPast() ? 'text-red-500 line-through' : '' }}">
                                        {{ $override->expires_at?->format('Y-m-d H:i') ?: __('Never') }}
                                    </span>
                                </flux:table.cell>
                                <flux:table.cell class="max-w-[200px] truncate text-sm">
                                    {{ $override->reason }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:button icon="trash" size="sm" variant="ghost"
                                        wire:click="removeOverride('{{ $override->id }}')"
                                        wire:confirm="{{ __('Remove this override?') }}" />
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="5" class="text-center py-8 text-zinc-500">
                                    {{ __('No active overrides for this tenant.') }}
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        </div>
    </div>
</div>
