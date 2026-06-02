<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Team Management') }}</flux:heading>
            <flux:subheading>{{ __('Manage users, roles and invitations for :tenant.', ['tenant' => tenant('name')]) }}</flux:subheading>
        </div>
        
        <flux:modal.trigger name="invite-member">
            <flux:button variant="primary" icon="plus">{{ __('Invite Member') }}</flux:button>
        </flux:modal.trigger>
    </div>

    @if (session('status'))
        <flux:text color="emerald">{{ session('status') }}</flux:text>
    @endif

    <!-- Members Table -->
    <flux:card class="p-0 overflow-hidden">
        <flux:table :paginate="$members">
            <flux:table.columns>
                <flux:table.column>{{ __('Member') }}</flux:table.column>
                <flux:table.column>{{ __('Role') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Joined') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($members as $member)
                    <flux:table.row :key="$member->id">
                        <flux:table.cell class="flex items-center gap-3">
                            <flux:avatar :name="$member->name" size="sm" />
                            <div class="flex flex-col">
                                <span class="font-medium text-sm text-zinc-900 dark:text-white">{{ $member->name }}</span>
                                <span class="text-xs text-zinc-500">{{ $member->email }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" variant="outline">{{ strtoupper($member->getRoleNames()->first() ?: 'member') }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($member->is_active)
                                <flux:badge size="sm" variant="success">{{ __('ACTIVE') }}</flux:badge>
                            @else
                                <flux:badge size="sm" variant="danger">{{ __('REVOKED') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-sm text-zinc-500">
                            {{ $member->created_at->format('Y-m-d') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($member->id !== auth()->id())
                                <flux:dropdown>
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                    <flux:menu>
                                        <flux:menu.item icon="pencil">{{ __('Change Role') }}</flux:menu.item>
                                        <flux:menu.separator />
                                        @if($member->is_active)
                                            <flux:menu.item variant="danger" icon="user-minus" wire:click="revokeAccess('{{ $member->id }}')">{{ __('Revoke Access') }}</flux:menu.item>
                                        @else
                                            <flux:menu.item variant="success" icon="user-plus">{{ __('Restore Access') }}</flux:menu.item>
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <!-- Pending Invitations -->
    @if($invitations->isNotEmpty())
        <div class="mt-8">
            <flux:heading size="lg" class="mb-4">{{ __('Pending Invitations') }}</flux:heading>
            <flux:card class="p-0 overflow-hidden">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Email') }}</flux:table.column>
                        <flux:table.column>{{ __('Role') }}</flux:table.column>
                        <flux:table.column>{{ __('Status / Expires') }}</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach($invitations as $invite)
                            <flux:table.row :key="$invite->id">
                                <flux:table.cell class="text-sm font-medium">{{ $invite->email }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" variant="outline">{{ strtoupper($invite->role->name) }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if($invite->expires_at->isPast())
                                        <div class="flex flex-col">
                                            <flux:badge size="sm" variant="danger">{{ __('EXPIRED') }}</flux:badge>
                                            <span class="text-[10px] text-zinc-500 mt-1">{{ $invite->expires_at->format('Y-m-d H:i') }}</span>
                                        </div>
                                    @else
                                        <span class="text-xs text-zinc-500">
                                            {{ __('Expires') }} {{ $invite->expires_at->diffForHumans() }}
                                        </span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <flux:button 
                                            icon="arrow-path" 
                                            size="sm" 
                                            variant="ghost" 
                                            wire:click="resendInvitation('{{ $invite->id }}')" 
                                            tooltip="{{ __('Resend Invitation') }}"
                                        />
                                        <flux:button 
                                            icon="trash" 
                                            size="sm" 
                                            variant="ghost" 
                                            wire:click="cancelInvitation('{{ $invite->id }}')"
                                            wire:confirm="{{ __('Are you sure you want to cancel this invitation?') }}"
                                            tooltip="{{ __('Cancel Invitation') }}"
                                        />
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        </div>
    @endif

    <!-- Invitation Modal -->
    <flux:modal name="invite-member" class="min-w-[25rem]">
        <form wire:submit="invite" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Invite New Member') }}</flux:heading>
                <flux:subheading>{{ __('Send an invitation link to join this team.') }}</flux:subheading>
            </div>

            <flux:input wire:model="inviteEmail" :label="__('Email Address')" type="email" placeholder="colleague@example.com" required />

            <flux:select wire:model="inviteRole" :label="__('Initial Role')">
                @foreach($availableRoles as $role)
                    <option value="{{ $role->name }}">{{ strtoupper($role->name) }}</option>
                @endforeach
            </flux:select>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Send Invitation') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
