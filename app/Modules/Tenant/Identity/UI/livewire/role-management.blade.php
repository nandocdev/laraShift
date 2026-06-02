<div class="flex flex-col gap-6 py-12">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Roles & Permissions') }}</flux:heading>
            <flux:subheading>{{ __('Define custom access levels for your organization members.') }}</flux:subheading>
        </div>
        
        <flux:modal.trigger name="create-role">
            <flux:button variant="primary" icon="plus">{{ __('Create Role') }}</flux:button>
        </flux:modal.trigger>
    </div>

    @if (session('status'))
        <flux:text color="emerald">{{ session('status') }}</flux:text>
    @endif

    <flux:card class="p-0 overflow-hidden">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Role Name') }}</flux:table.column>
                <flux:table.column>{{ __('Permissions') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($roles as $role)
                    <flux:table.row :key="$role->id">
                        <flux:table.cell class="font-medium text-sm">
                            {{ strtoupper($role->name) }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap gap-1">
                                @forelse($role->permissions as $perm)
                                    <flux:badge size="sm" variant="outline" class="text-[10px]">{{ $perm->name }}</flux:badge>
                                @empty
                                    <span class="text-xs text-zinc-400 italic">{{ __('No specific permissions') }}</span>
                                @endforelse
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($role->is_system)
                                <flux:badge size="sm" variant="neutral">{{ __('SYSTEM') }}</flux:badge>
                            @else
                                <flux:badge size="sm" variant="success">{{ __('CUSTOM') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    <flux:modal.trigger name="edit-role">
                                        <flux:menu.item icon="pencil" wire:click="edit('{{ $role->id }}')">{{ __('Edit Permissions') }}</flux:menu.item>
                                    </flux:modal.trigger>
                                    
                                    @if(!$role->is_system)
                                        <flux:menu.separator />
                                        <flux:menu.item variant="danger" icon="trash" wire:click="delete('{{ $role->id }}')" wire:confirm="{{ __('Are you sure? This action is permanent.') }}">
                                            {{ __('Delete Role') }}
                                        </flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <!-- Create Role Modal -->
    <flux:modal name="create-role" class="min-w-[30rem]">
        <form wire:submit="create" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Create Custom Role') }}</flux:heading>
                <flux:subheading>{{ __('Define a new access level with specific permissions.') }}</flux:subheading>
            </div>

            <flux:input wire:model="name" :label="__('Role Name')" placeholder="e.g. Moderator" required />

            <div class="space-y-2">
                <flux:label>{{ __('Grant Permissions') }}</flux:label>
                <div class="grid grid-cols-1 gap-2 p-3 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                    @foreach($availablePermissions as $key => $label)
                        <label class="flex items-center gap-3 p-2 hover:bg-zinc-50 dark:hover:bg-zinc-800 rounded cursor-pointer transition-colors">
                            <input type="checkbox" wire:model="selectedPermissions" value="{{ $key }}" class="rounded border-zinc-300 text-indigo-600 focus:ring-indigo-600">
                            <div class="flex flex-col">
                                <span class="text-xs font-bold font-mono">{{ $key }}</span>
                                <span class="text-[10px] text-zinc-500">{{ $label }}</span>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Create Role') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Edit Role Modal -->
    <flux:modal name="edit-role" class="min-w-[30rem]">
        <form wire:submit="update" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Edit Role: :name', ['name' => $editingRole?->name]) }}</flux:heading>
                <flux:subheading>{{ __('Update permissions for this role.') }}</flux:subheading>
            </div>

            <flux:input wire:model="editName" :label="__('Role Name')" :disabled="$editingRole?->is_system" required />

            <div class="space-y-2">
                <flux:label>{{ __('Permissions') }}</flux:label>
                <div class="grid grid-cols-1 gap-2 p-3 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                    @foreach($availablePermissions as $key => $label)
                        <label class="flex items-center gap-3 p-2 hover:bg-zinc-50 dark:hover:bg-zinc-800 rounded cursor-pointer transition-colors">
                            <input type="checkbox" wire:model="editPermissions" value="{{ $key }}" class="rounded border-zinc-300 text-indigo-600 focus:ring-indigo-600">
                            <div class="flex flex-col">
                                <span class="text-xs font-bold font-mono">{{ $key }}</span>
                                <span class="text-[10px] text-zinc-500">{{ $label }}</span>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Update Role') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
