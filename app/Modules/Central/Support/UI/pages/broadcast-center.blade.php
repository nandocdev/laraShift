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
                            <option value="status">{{ __('By Status') }}</option>
                            <option value="plan">{{ __('By Plan') }}</option>
                            <option value="selected">{{ __('Selected Tenants') }}</option>
                        </flux:select>

                        @if($filterType === 'status')
                            <flux:select wire:model="filterValue" :label="__('Select Status')">
                                <option value="active">{{ __('Active') }}</option>
                                <option value="pending_payment">{{ __('Pending payment') }}</option>
                                <option value="past_due">{{ __('Past due') }}</option>
                                <option value="suspended">{{ __('Suspended') }}</option>
                                <option value="quarantine">{{ __('Quarantine') }}</option>
                                <option value="archived">{{ __('Archived') }}</option>
                            </flux:select>
                        @elseif($filterType === 'plan')
                            <flux:select wire:model="filterValue" :label="__('Select Plan')">
                                @foreach ($this->plans as $plan)
                                    <option value="{{ $plan['slug'] }}">{{ $plan['name'] }}</option>
                                @endforeach
                            </flux:select>
                        @else
                            <div></div>
                        @endif
                    </div>

                    @if($filterType === 'selected')
                        <flux:textarea wire:model="tenantSlugs" :label="__('Tenant slugs (comma separated)')" rows="2"
                            placeholder="acme, globex" description="{{ __('Estimated recipients: :count', ['count' => $this->recipientEstimate]) }}" />
                        <flux:error name="tenantSlugs" />
                    @endif

                    <flux:checkbox.group wire:model="channels" :label="__('Delivery Channels')">
                        <flux:checkbox value="email" :label="__('Send via Email')" />
                        <flux:checkbox value="banner" :label="__('In-App Banner')" />
                    </flux:checkbox.group>

                    <flux:input wire:model="scheduledAt" type="datetime-local" :label="__('Schedule for (optional)')" />

                    <flux:button type="submit" variant="primary" class="w-full"
                        wire:confirm="{{ __('Send now to :count tenants? This cannot be undone.', ['count' => $this->recipientEstimate]) }}">
                        {{ __('Send Now') }}
                    </flux:button>
                    <div class="grid grid-cols-2 gap-2">
                        <flux:button wire:click="schedule" variant="ghost" class="w-full">{{ __('Schedule') }}</flux:button>
                        <flux:button wire:click="saveDraft" variant="ghost" class="w-full">{{ __('Save Draft') }}</flux:button>
                    </div>
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
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column></flux:table.column>
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
                                    <div class="text-sm">{{ $b->sent_at?->format('Y-m-d H:i') ?? ($b->is_draft ? __('Draft') : ($b->scheduled_at?->format('Y-m-d H:i') ?? __('Queued'))) }}</div>
                                    <div class="text-[10px] text-zinc-400">{{ $b->creator->name }}</div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if($b->is_draft)
                                        <div class="flex gap-1">
                                            <flux:button size="sm" variant="ghost" wire:click="publishDraft('{{ $b->id }}')">{{ __('Publish') }}</flux:button>
                                            <flux:button size="sm" variant="ghost" wire:click="deleteDraft('{{ $b->id }}')" wire:confirm="{{ __('Delete this draft?') }}">{{ __('Delete') }}</flux:button>
                                        </div>
                                    @elseif(!$b->sent_at && $b->scheduled_at)
                                        <flux:button size="sm" variant="ghost" wire:click="unschedule('{{ $b->id }}')">{{ __('Cancel') }}</flux:button>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="5" class="text-center py-8 text-zinc-500">
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
