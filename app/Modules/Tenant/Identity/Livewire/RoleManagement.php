<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Livewire;

use App\Modules\Tenant\Identity\Models\Role;
use App\Modules\Tenant\Identity\Models\Permission;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Illuminate\Support\Str;

#[Layout('layouts.app')]
class RoleManagement extends Component
{
    // Create Role State
    public string $name = '';
    public array $selectedPermissions = [];

    // Edit Role State
    public ?Role $editingRole = null;
    public string $editName = '';
    public array $editPermissions = [];

    public array $availablePermissions = [
        'team:read' => 'View team members',
        'team:manage' => 'Invite and revoke members',
        'roles:manage' => 'Manage custom roles and permissions',
        'settings:manage' => 'Update organization settings',
        'billing:manage' => 'Manage subscriptions and invoices',
        'audit:read' => 'View organization audit logs',
    ];

    public function mount(): void
    {
        // Ensure core permissions exist in the DB (for this guard)
        foreach ($this->availablePermissions as $key => $label) {
            Permission::firstOrCreate(['name' => $key, 'guard_name' => 'web']);
        }
    }

    public function create(): void
    {
        $this->validate([
            'name' => 'required|string|min:3|max:100|unique:roles,name',
            'selectedPermissions' => 'array',
        ]);

        $role = Role::create([
            'id' => Str::uuid()->toString(),
            'tenant_id' => tenant('id'),
            'name' => $this->name,
            'guard_name' => 'web',
            'is_system' => false,
        ]);

        $role->syncPermissions($this->selectedPermissions);

        activity('identity')
            ->performedOn($role)
            ->log('role_created');

        event(new \App\Modules\Shared\Events\TenantRoleCreated($role));

        $this->reset(['name', 'selectedPermissions']);
        session()->flash('status', __('Custom role created.'));
    }

    public function edit(string $roleId): void
    {
        $this->editingRole = Role::findOrFail($roleId);
        
        if ($this->editingRole->is_system) {
            $this->addError('editName', __('System roles cannot be renamed.'));
        }

        $this->editName = $this->editingRole->name;
        $this->editPermissions = $this->editingRole->permissions->pluck('name')->toArray();
    }

    public function update(): void
    {
        $this->validate([
            'editName' => 'required|string|min:3|max:100',
            'editPermissions' => 'array',
        ]);

        if (! $this->editingRole->is_system) {
            $this->editingRole->update(['name' => $this->editName]);
        }

        $this->editingRole->syncPermissions($this->editPermissions);

        // Explicitly flush Spatie permission cache to ensure < 5s effectiveness
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        app(\App\Modules\Tenant\Audit\Actions\RecordAuditLogAction::class)->execute(
            new \App\Modules\Tenant\Audit\DTOs\AuditLogData(
                action: \App\Modules\Tenant\Audit\Enums\AuditAction::ROLE_UPDATED,
                resource: 'role',
                resourceId: $this->editingRole->id,
                metadata: ['name' => $this->editName, 'permissions' => $this->editPermissions]
            )
        );

        activity('identity')
            ->performedOn($this->editingRole)
            ->log('role_updated');

        event(new \App\Modules\Shared\Events\TenantRoleUpdated($this->editingRole, $this->editPermissions));

        $this->reset(['editingRole', 'editName', 'editPermissions']);
        session()->flash('status', __('Role updated successfully.'));
    }

    public function delete(string $roleId): void
    {
        $role = Role::findOrFail($roleId);

        if ($role->is_system) {
            session()->flash('error', __('System roles cannot be deleted.'));
            return;
        }

        if ($role->users()->exists()) {
            abort(409, __('Cannot delete role with active users. Please reassign them first.'));
            return;
        }

        $role->delete();

        // Explicitly flush Spatie permission cache to ensure < 5s effectiveness
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        activity('identity')
            ->performedOn($role)
            ->log('role_deleted');

        session()->flash('status', __('Role deleted.'));
    }

    public function render(): View
    {
        return view('identity::livewire.role-management', [
            'roles' => Role::with('permissions')->latest()->get(),
        ]);
    }
}
