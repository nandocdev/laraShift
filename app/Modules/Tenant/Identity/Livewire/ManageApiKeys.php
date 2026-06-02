<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Livewire;

use App\Modules\Tenant\Identity\Actions\GenerateApiKeyAction;
use App\Modules\Tenant\Identity\Actions\RevokeApiKeyAction;
use App\Modules\Tenant\Identity\Models\ApiKey;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ManageApiKeys extends Component
{
    // Form state
    public string $name = '';
    public array $selectedScopes = [];
    
    // Result state
    public string $plainKey = '';
    public bool $showingKey = false;

    public array $availableScopes = [
        'identity:read' => 'Read team members and roles',
        'identity:write' => 'Manage team members and roles',
        'settings:read' => 'View tenant settings',
        'settings:write' => 'Update tenant settings',
        'audit:read' => 'View audit logs',
    ];

    public function generate(GenerateApiKeyAction $action): void
    {
        $this->validate([
            'name' => 'required|string|max:100',
            'selectedScopes' => 'required|array|min:1',
        ]);

        // Check Limit (US-T104: 10 keys)
        $activeCount = ApiKey::whereNull('revoked_at')->count();
        if ($activeCount >= 10) {
            $this->addError('name', __('You have reached the maximum limit of 10 active API keys.'));
            return;
        }

        $result = $action->execute($this->name, $this->selectedScopes, auth()->user());

        $this->plainKey = $result['key'];
        $this->showingKey = true;
        
        $this->reset(['name', 'selectedScopes']);
        session()->flash('status', __('API Key generated successfully. Save it now!'));
    }

    public function revoke(string $id, RevokeApiKeyAction $action): void
    {
        $apiKey = ApiKey::findOrFail($id);
        $action->execute($apiKey);

        session()->flash('status', __('API Key revoked.'));
    }

    public function closeKeyModal(): void
    {
        $this->plainKey = '';
        $this->showingKey = false;
    }

    public function render(): View
    {
        return view('identity::livewire.manage-api-keys', [
            'apiKeys' => ApiKey::with('creator')->latest()->get(),
        ]);
    }
}
