<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Tenants') }}</flux:heading>
            <flux:subheading>{{ __('Manage platform customers and their isolation.') }}</flux:subheading>
        </div>
        <flux:button :href="route('central.provisioning.create')" variant="primary" icon="plus" wire:navigate>
            {{ __('New Tenant') }}
        </flux:button>
    </div>

    @if (session('status'))
        <flux:text color="emerald">{{ session('status') }}</flux:text>
    @endif

    <flux:card>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
            <flux:input wire:model.live.debounce.500ms="search" :label="__('Search')" placeholder="{{ __('Name, slug or email…') }}" />
            <flux:select wire:model.live="statusFilter" :label="__('Status')">
                <option value="">{{ __('All') }}</option>
                @foreach ($statuses as $option)
                    <option value="{{ $option['value'] }}">{{ __($option['label']) }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="planFilter" :label="__('Plan')">
                <option value="">{{ __('All') }}</option>
                @foreach ($plans as $plan)
                    <option value="{{ $plan['slug'] }}">{{ $plan['name'] }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="healthFilter" :label="__('Health')">
                <option value="">{{ __('All') }}</option>
                <option value="healthy">{{ __('Healthy') }}</option>
                <option value="warning">{{ __('Warning') }}</option>
                <option value="critical">{{ __('Critical') }}</option>
            </flux:select>
            <div class="flex items-end">
                <flux:button variant="ghost" size="sm" wire:click="clearFilters">{{ __('Clear') }}</flux:button>
            </div>
        </div>

        <flux:table :paginate="$tenants">
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Domain') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Plan') }}</flux:table.column>
                <flux:table.column>{{ __('Health') }}</flux:table.column>
                <flux:table.column>{{ __('Created At') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                <flux:table.row wire:loading.flex wire:target="search,statusFilter,planFilter,healthFilter,nextPage,previousPage,gotoPage">
                    <flux:table.cell colspan="7">
                        <div class="flex flex-col gap-2" aria-hidden="true">
                            <flux:skeleton class="h-10 w-full" />
                            <flux:skeleton class="h-10 w-full" />
                            <flux:skeleton class="h-10 w-full" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
                @forelse ($tenants as $tenant)
                    <flux:table.row :key="$tenant->id">
                        <flux:table.cell class="font-medium">
                            {{ $tenant->name }}
                            <div class="text-xs text-neutral-500">{{ $tenant->email }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:link :href="'http://' . $tenant->domains->first()?->domain" target="_blank">
                                {{ $tenant->domains->first()?->domain ?? 'No domain' }}
                            </flux:link>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:badge size="sm" :variant="$tenant->status === 'active' ? 'success' : ($tenant->status === 'maintenance' ? 'warning' : 'neutral')">
                                    {{ strtoupper($tenant->status) }}
                                </flux:badge>
                                @if($tenant->read_only)
                                    <flux:badge size="sm" variant="neutral">{{ __('READ-ONLY') }}</flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $tenant->plan_id ?? 'free' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @php($health = \App\Modules\Central\Provisioning\Livewire\TenantList::healthFor($tenant->status))
                            <div class="flex items-center gap-1.5 text-xs font-medium">
                                @if($health === 'healthy')
                                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                    <span class="text-emerald-600 dark:text-emerald-400">{{ __('Healthy') }}</span>
                                @elseif($health === 'warning')
                                    <span class="size-1.5 rounded-full bg-amber-500"></span>
                                    <span class="text-amber-600 dark:text-amber-400">{{ __('Warning') }}</span>
                                @else
                                    <span class="size-1.5 rounded-full bg-rose-500"></span>
                                    <span class="text-rose-600 dark:text-rose-400">{{ __('Critical') }}</span>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $tenant->created_at->format('Y-m-d H:i') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" class="min-h-12 min-w-12" aria-label="{{ __('Tenant actions') }}" />
                                <flux:menu>
                                    <flux:menu.item icon="pencil" :href="route('central.provisioning.edit', $tenant->id)" wire:navigate>{{ __('Edit') }}</flux:menu.item>
                                    
                                    <flux:modal.trigger name="impersonate-tenant">
                                        <flux:menu.item icon="shield-check" wire:click="selectTenant('{{ $tenant->id }}')">{{ __('Impersonate') }}</flux:menu.item>
                                    </flux:modal.trigger>

                                    <flux:menu.separator />
                                    <flux:modal.trigger name="delete-tenant">
                                        <flux:menu.item variant="danger" icon="trash" wire:click="selectTenant('{{ $tenant->id }}')">{{ __('Delete') }}</flux:menu.item>
                                    </flux:modal.trigger>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="text-center text-sm text-zinc-500">
                            {{ __('No tenants match the current filters.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal name="impersonate-tenant" class="min-w-[25rem]">
        <form wire:submit="impersonate" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Impersonate Tenant') }}</flux:heading>
                <flux:subheading>{{ __('You are about to access the account of :name.', ['name' => $selectedTenant?->name]) }}</flux:subheading>
            </div>

            <flux:input 
                wire:model="impersonationTicketId" 
                :label="__('Ticket ID')" 
                placeholder="{{ __('e.g. TICK-1234') }}" 
                required 
            />

            <flux:textarea 
                wire:model="impersonationReason" 
                :label="__('Reason for Access')" 
                placeholder="{{ __('e.g. Investigating reported bug in invoicing module...') }}" 
                description="{{ __('Min. 20 characters. This action is audited.') }}"
                required 
            />

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Start Session') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="delete-tenant" class="min-w-[25rem]">
        <form wire:submit="delete" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Hard Delete Tenant') }}</flux:heading>
                <flux:subheading>{{ __('Warning: This will permanently purge all data, database schema and files for :name.', ['name' => $selectedTenant?->name]) }}</flux:subheading>
            </div>

            <flux:input 
                wire:model="confirmSlug" 
                :label="__('Type the tenant slug to confirm')" 
                :placeholder="$selectedTenant?->slug"
                required 
            />

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger">{{ __('Confirm Purge') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
