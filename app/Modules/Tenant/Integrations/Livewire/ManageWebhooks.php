<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Integrations\Livewire;

use App\Modules\Tenant\Integrations\Actions\CreateWebhookAction;
use App\Modules\Tenant\Integrations\Actions\DeleteWebhookAction;
use App\Modules\Tenant\Integrations\Actions\UpdateWebhookAction;
use App\Modules\Tenant\Integrations\DTOs\CreateWebhookData;
use App\Modules\Tenant\Integrations\DTOs\UpdateWebhookData;
use App\Modules\Tenant\Integrations\Models\TenantWebhook;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ManageWebhooks extends Component
{
    public ?TenantWebhook $editing = null;

    public bool $isEditing = false;

    public string $url = '';

    public string $secret = '';

    public array $selectedEvents = [];

    public bool $is_active = true;

    public int $max_retries = 5;

    public int $timeout_seconds = 5;

    public array $availableEvents = [
        'user.created' => 'User Created',
        'user.updated' => 'User Updated',
        'user.deleted' => 'User Deleted',
        'role.created' => 'Role Created',
        'role.updated' => 'Role Updated',
        'settings.updated' => 'Settings Updated',
        'billing.invoice_paid' => 'Invoice Paid',
        'audit.export_ready' => 'Audit Export Ready',
    ];

    public function mount(?TenantWebhook $webhook = null): void
    {
        if ($webhook && $webhook->exists) {
            $this->editing = $webhook;
            $this->isEditing = true;
            $this->url = $webhook->url;
            $this->secret = '';
            $this->selectedEvents = $webhook->events ?? [];
            $this->is_active = $webhook->is_active;
            $this->max_retries = $webhook->max_retries;
            $this->timeout_seconds = $webhook->timeout_seconds;
        } else {
            $this->secret = Str::random(32);
        }
    }

    public function save(CreateWebhookAction $createAction, UpdateWebhookAction $updateAction): void
    {
        $this->validate([
            'url' => 'required|url|max:500',
            'secret' => 'required|string|min:16',
            'selectedEvents' => 'required|array|min:1',
            'max_retries' => 'required|integer|min:0|max:20',
            'timeout_seconds' => 'required|integer|min:1|max:30',
            'is_active' => 'boolean',
        ]);

        if ($this->isEditing) {
            $data = new UpdateWebhookData(
                url: $this->url !== $this->editing->url ? $this->url : null,
                secret: $this->secret ?: null,
                events: $this->selectedEvents,
                is_active: $this->is_active,
                max_retries: $this->max_retries,
                timeout_seconds: $this->timeout_seconds,
            );

            $updateAction->execute($this->editing, $data);
            session()->flash('status', __('Webhook updated.'));
        } else {
            $data = new CreateWebhookData(
                url: $this->url,
                secret: $this->secret,
                events: $this->selectedEvents,
                is_active: $this->is_active,
                max_retries: $this->max_retries,
                timeout_seconds: $this->timeout_seconds,
            );

            $createAction->execute($data);
            session()->flash('status', __('Webhook created.'));
        }

        $this->resetForm();
    }

    public function edit(TenantWebhook $webhook): void
    {
        $this->mount($webhook);
    }

    public function delete(string $id, DeleteWebhookAction $action): void
    {
        $webhook = TenantWebhook::findOrFail($id);
        $action->execute($webhook);

        session()->flash('status', __('Webhook deleted.'));
    }

    public function regenerateSecret(): void
    {
        $this->secret = Str::random(32);
    }

    public function resetForm(): void
    {
        $this->reset(['editing', 'isEditing', 'url', 'secret', 'selectedEvents', 'is_active', 'max_retries', 'timeout_seconds']);
        $this->secret = Str::random(32);
    }

    public function render(): View
    {
        return view('integrations::livewire.manage-webhooks', [
            'webhooks' => TenantWebhook::where('tenant_id', tenant('id'))->latest()->get(),
        ]);
    }
}
