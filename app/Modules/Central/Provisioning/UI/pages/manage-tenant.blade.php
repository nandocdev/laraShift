<div class="flex flex-col gap-6 max-w-2xl mx-auto">
    <div class="flex items-center gap-4">
        <flux:button icon="arrow-left" variant="ghost" :href="route('central.provisioning.index')" wire:navigate />
        <div>
            <flux:heading size="xl">{{ __('Edit Tenant') }}: {{ $tenant->name }}</flux:heading>
            <flux:subheading>{{ __('Manage account details and operational status.') }}</flux:subheading>
        </div>
    </div>

    @if (session('status'))
        <flux:text color="emerald">{{ session('status') }}</flux:text>
    @endif

    <div class="flex flex-wrap gap-2" role="tablist" aria-label="{{ __('Tenant sections') }}">
        @foreach (['overview' => __('Overview'), 'subscription' => __('Subscription'), 'usage' => __('Usage'), 'health' => __('Health'), 'activity' => __('Activity'), 'danger' => __('Danger Zone')] as $tab => $label)
            <flux:button :variant="$activeTab === $tab ? 'primary' : 'ghost'" size="sm" wire:click="setTab('{{ $tab }}')" class="min-h-12">{{ $label }}</flux:button>
        @endforeach
    </div>

    @if ($activeTab === 'overview')
    <flux:card>
        <form wire:submit="save" class="flex flex-col gap-6">
            <flux:input
                wire:model="name"
                :label="__('Account Name')"
                required
            />

            <flux:input
                wire:model="email"
                :label="__('Owner Email')"
                type="email"
                required
            />

            <div class="grid grid-cols-2 gap-6">
                <flux:select wire:model="status" :label="__('Operational Status')">
                    <option value="provisioning">{{ __('Provisioning') }}</option>
                    <option value="active">{{ __('Active') }}</option>
                    <option value="pending_payment">{{ __('Pending payment') }}</option>
                    <option value="past_due">{{ __('Past due') }}</option>
                    <option value="suspended">{{ __('Suspended') }}</option>
                    <option value="quarantine">{{ __('Quarantine') }}</option>
                    <option value="archived">{{ __('Archived') }}</option>
                    <option value="failed">{{ __('Failed') }}</option>
                    <option value="expired">{{ __('Expired') }}</option>
                </flux:select>
            </div>

            <div class="space-y-4">
                <flux:checkbox
                    wire:model="maintenance_mode"
                    :label="__('Maintenance Mode')"
                    description="{{ __('Access will be blocked with a 503 error.') }}"
                />

                <flux:checkbox
                    wire:model="read_only"
                    :label="__('Read Only Mode')"
                    description="{{ __('Post/Put/Delete actions will be blocked for tenant users.') }}"
                />
            </div>

            <div class="flex justify-end gap-2 mt-4">
                <flux:button :href="route('central.provisioning.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Save Changes') }}</flux:button>
            </div>
        </form>
    </flux:card>
    @endif

    @if ($activeTab === 'subscription')
    <flux:card>
        <div class="flex flex-col gap-6">
            <div>
                <flux:heading size="sm">{{ __('Current plan') }}: {{ $tenant->plan_id ?? 'free' }}</flux:heading>
                @if ($this->currentSubscription)
                    <flux:subheading>{{ __('Status') }}: {{ $this->currentSubscription['status'] ?? '—' }} · {{ __('Renews') }}: {{ $this->currentSubscription['current_period_end'] ?? '—' }}</flux:subheading>
                @else
                    <flux:subheading>{{ __('No subscription on file.') }}</flux:subheading>
                @endif
            </div>
            <form wire:submit="changePlan" class="flex flex-col gap-4">
                <flux:select wire:model="plan_id" :label="__('Change Plan')">
                    @foreach ($this->plans as $plan)
                        <option value="{{ $plan['slug'] }}">{{ $plan['name'] }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="plan_id" />
                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary">{{ __('Change Plan') }}</flux:button>
                </div>
            </form>
        </div>
    </flux:card>
    @endif

    @if ($activeTab === 'usage')
    <flux:card>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-center">
            <div>
                <div class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">{{ __('Users') }}</div>
                <div class="text-2xl font-bold font-mono">{{ number_format($this->usage['users']) }}</div>
            </div>
            <div>
                <div class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">{{ __('Subscriptions') }}</div>
                <div class="text-2xl font-bold font-mono">{{ number_format($this->usage['subscriptions']) }}</div>
            </div>
            <div>
                <div class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">{{ __('Payments') }}</div>
                <div class="text-2xl font-bold font-mono">{{ number_format($this->usage['payments']) }}</div>
            </div>
        </div>
    </flux:card>
    @endif

    @if ($activeTab === 'health')
    <flux:card>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div><dt class="font-semibold">{{ __('Status') }}</dt><dd>{{ strtoupper($tenant->status) }}</dd></div>
            <div><dt class="font-semibold">{{ __('Maintenance') }}</dt><dd>{{ $tenant->maintenance_mode ? __('On') : __('Off') }}</dd></div>
            <div><dt class="font-semibold">{{ __('Read-only') }}</dt><dd>{{ $tenant->read_only ? __('On') : __('Off') }}</dd></div>
            <div><dt class="font-semibold">{{ __('Domain') }}</dt><dd>{{ $tenant->domains->first()?->domain ?? '—' }}</dd></div>
            <div><dt class="font-semibold">{{ __('Created') }}</dt><dd>{{ $tenant->created_at }}</dd></div>
            <div><dt class="font-semibold">{{ __('Updated') }}</dt><dd>{{ $tenant->updated_at }}</dd></div>
        </dl>
    </flux:card>
    @endif

    @if ($activeTab === 'activity')
    <flux:card>
        <div class="space-y-4">
            @forelse ($this->tenantActivity as $entry)
                <div class="flex items-start gap-3 text-sm">
                    <span class="size-2 rounded-full bg-zinc-400 mt-1.5 flex-shrink-0"></span>
                    <div class="flex flex-col">
                        <span class="font-semibold">{{ $entry['description'] }}</span>
                        <span class="text-xs text-zinc-500">{{ $entry['time'] }}</span>
                    </div>
                </div>
            @empty
                <p class="text-sm text-zinc-500">{{ __('No recorded activity for this tenant.') }}</p>
            @endforelse
        </div>
    </flux:card>
    @endif

    @if ($activeTab === 'danger')
    <flux:card class="border-rose-200">
        <div class="flex flex-col gap-4">
            <flux:heading size="sm">{{ __('Operational actions') }}</flux:heading>
    @if (session('status'))
        <flux:text color="emerald">{{ session('status') }}</flux:text>
    @endif

    <div class="flex flex-wrap gap-2">
                <flux:button variant="ghost" wire:click="suspend" wire:confirm="{{ __('Suspend this tenant?') }}">{{ __('Suspend') }}</flux:button>
                <flux:button variant="ghost" wire:click="quarantine" wire:confirm="{{ __('Quarantine this tenant? Users become read-only.') }}">{{ __('Quarantine') }}</flux:button>
                <flux:button variant="primary" wire:click="reactivate">{{ __('Reactivate') }}</flux:button>
            </div>
            <flux:separator />
            <flux:heading size="sm">{{ __('Purge tenant (irreversible)') }}</flux:heading>
            <flux:subheading>{{ __('Type the tenant slug to queue a hard purge. This is recorded immutably.') }}</flux:subheading>
            <form wire:submit="purge" class="flex flex-col gap-4">
                <flux:input wire:model="purgeConfirmSlug" :label="__('Tenant slug')" :placeholder="$tenant->slug" required />
                <flux:error name="purgeConfirmSlug" />
                <div class="flex justify-end">
                    <flux:button type="submit" variant="danger">{{ __('Initiate Purge') }}</flux:button>
                </div>
            </form>
        </div>
    </flux:card>
    @endif

    <div class="mt-12">
        <livewire:tenant-support-bitacora :tenant="$tenant" />
    </div>
</div>
