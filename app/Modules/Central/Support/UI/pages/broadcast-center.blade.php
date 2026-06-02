<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Broadcast Center') }}</flux:heading>
        <flux:subheading>{{ __('Communicate with multiple tenants via global channels.') }}</flux:subheading>
    </div>

    @if (session('status'))
        <flux:text color="emerald">{{ session('status') }}</flux:text>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Composer -->
        <div class="lg:col-span-1">
            <flux:card>
                <form wire:submit="send" class="space-y-6">
                    <flux:input wire:model="title" :label="__('Broadcast Title')"
                        placeholder="{{ __('Maintenance Notice') }}" required />

                    <flux:textarea wire:model="body" :label="__('Message Content')" rows="5" required />

                    <div class="grid grid-cols-2 gap-4">
                        <flux:select wire:model.live="filterType" :label="__('Target Audience')">
                            <option value="all">{{ __('All Tenants') }}</option>
                            <option value="plan">{{ __('By Plan') }}</option>
                            <option value="status">{{ __('By Status') }}</option>
                        </flux:select>

                        @if ($filterType === 'plan')
                            <flux:select wire:model="filterValue" :label="__('Select Plan')">
                                <option value="">{{ __('Any') }}</option>
                                @foreach ($plans as $plan)
                                    <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                                @endforeach
                            </flux:select>
                        @elseif($filterType === 'status')
                            <flux:select wire:model="filterValue" :label="__('Select Status')">
                                <option value="active">{{ __('Active') }}</option>
                                <option value="suspended">{{ __('Suspended') }}</option>
                                <option value="archived">{{ __('Archived') }}</option>
                            </flux:select>
                        @else
                            <div></div>
                        @endif
                    </div>

                    <flux:checkbox.group wire:model="channels" :label="__('Delivery Channels')">
                        <flux:checkbox value="email" :label="__('Send via Email')" checked />
                        <flux:checkbox value="banner" :label="__('In-App Banner (Planned)')" disabled />
                    </flux:checkbox.group>

                    <flux:button type="submit" variant="primary" class="w-full"
                        wire:confirm="{{ __('Are you sure you want to send this broadcast?') }}">
                        {{ __('Dispatch Message') }}
                    </flux:button>
                </form>
            </flux:card>
        </div>

        <!-- History -->
        <div class="lg:col-span-2">
            <flux:card class="overflow-hidden">
                <flux:table :paginate="$broadcasts">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Broadcast') }}</flux:table.column>
                        <flux:table.column>{{ __('Target') }}</flux:table.column>
                        <flux:table.column>{{ __('Recipients') }}</flux:table.column>
                        <flux:table.column>{{ __('Sent At') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse($broadcasts as $b)
                            <flux:table.row :key="$b->id">
                                <flux:table.cell>
                                    <div class="font-bold text-sm">{{ $b->title }}</div>
                                    <div class="text-xs text-zinc-500 truncate max-w-[200px]">{{ $b->body }}</div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" variant="outline">
                                        {{ strtoupper($b->filter_type) }}
                                        {{ $b->filter_value ? "($b->filter_value)" : '' }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>
                                    {{ $b->recipient_count ?? 0 }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div class="text-sm">{{ $b->sent_at?->format('Y-m-d H:i') ?: __('Pending') }}</div>
                                    <div class="text-[10px] text-zinc-400">{{ $b->creator->name }}</div>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="4" class="text-center py-8 text-zinc-500">
                                    {{ __('No broadcast history found.') }}
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        </div>
    </div>
</div>
