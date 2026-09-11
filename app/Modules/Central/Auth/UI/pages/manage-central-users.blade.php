<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Admin Users') }}</flux:heading>
            <flux:subheading>{{ __('People who can operate the Control Plane. Deactivation preserves audit trail.') }}</flux:subheading>
        </div>
        <flux:modal.trigger name="invite-admin">
            <flux:button variant="primary" icon="plus">{{ __('Invite Admin') }}</flux:button>
        </flux:modal.trigger>
    </div>

    @if (session('status'))
        <flux:text color="emerald">{{ session('status') }}</flux:text>
    @endif

    <flux:card class="overflow-hidden">
        <flux:table :paginate="$users">
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Role') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('2FA') }}</flux:table.column>
                <flux:table.column>{{ __('Sessions') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($users as $user)
                    <flux:table.row :key="$user->id">
                        <flux:table.cell class="font-medium">
                            {{ $user->name }}
                            <div class="text-xs text-neutral-500">{{ $user->email }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :variant="$user->is_global_admin ? 'solid' : 'outline'">
                                {{ $user->is_global_admin ? __('Super Admin') : __('Staff') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($user->locked_until && $user->locked_until->isFuture())
                                <flux:badge size="sm" variant="danger">{{ __('Disabled') }}</flux:badge>
                            @else
                                <flux:badge size="sm" variant="success">{{ __('Active') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $user->hasTwoFactorEnabled() ? __('On') : __('Off') }}
                        </flux:table.cell>
                        <flux:table.cell class="font-mono">
                            {{ $user->active_sessions }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    <flux:menu.item icon="shield-check" wire:click="toggleAdmin('{{ $user->id }}')">{{ $user->is_global_admin ? __('Demote to Staff') : __('Promote to Super Admin') }}</flux:menu.item>
                                    @if($user->locked_until && $user->locked_until->isFuture())
                                        <flux:menu.item icon="check" wire:click="enable('{{ $user->id }}')">{{ __('Enable') }}</flux:menu.item>
                                    @else
                                        <flux:menu.item icon="no-symbol" wire:click="disable('{{ $user->id }}')" wire:confirm="{{ __('Disable access for :email?', ['email' => $user->email]) }}">{{ __('Disable') }}</flux:menu.item>
                                    @endif
                                    <flux:menu.separator />
                                    <flux:menu.item variant="danger" icon="arrow-right-start-on-rectangle" wire:click="revokeSessions('{{ $user->id }}')" wire:confirm="{{ __('Revoke all sessions for :email?', ['email' => $user->email]) }}">{{ __('Revoke Sessions') }}</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center text-sm text-zinc-500">{{ __('No admin users.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal name="invite-admin" class="min-w-[25rem]">
        <form wire:submit="invite" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Invite Admin') }}</flux:heading>
                <flux:subheading>{{ __('Share the initial password out-of-band. MFA enrollment is required at first login.') }}</flux:subheading>
            </div>

            <flux:input wire:model="name" :label="__('Name')" required />
            <flux:input wire:model="email" type="email" :label="__('Email')" required />
            <flux:input wire:model="password" type="password" :label="__('Initial password')" description="{{ __('Minimum 12 characters.') }}" required viewable />
            <flux:input wire:model="password_confirmation" type="password" :label="__('Confirm password')" required viewable />
            <flux:checkbox wire:model="isGlobalAdmin" :label="__('Super Admin')" description="{{ __('Full control over the Control Plane.') }}" />

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Invite') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
