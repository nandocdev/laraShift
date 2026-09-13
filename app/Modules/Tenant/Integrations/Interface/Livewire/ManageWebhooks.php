<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Integrations\Interface\Livewire;

use App\Modules\Tenant\Integrations\Application\Actions\DeleteWebhookEndpoint;
use App\Modules\Tenant\Integrations\Application\Actions\DispatchTenantWebhook;
use App\Modules\Tenant\Integrations\Application\Actions\RegisterWebhookEndpoint;
use App\Modules\Tenant\Integrations\Application\DTO\WebhookEndpointData;
use App\Modules\Tenant\Integrations\Domain\Models\WebhookDelivery;
use App\Modules\Tenant\Integrations\Domain\Models\WebhookEndpoint;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ManageWebhooks extends Component
{
    use AuthorizesRequests;

    public string $url = '';

    /** @var array<int, string> */
    public array $events = [];

    public string $plainSecret = '';

    public bool $showingSecret = false;

    public function register(RegisterWebhookEndpoint $action): void
    {
        $this->authorize('settings:manage');

        $this->validate([
            'url' => 'required|url:https|max:2000',
            'events' => ['required', 'array', 'min:1', Rule::in(DispatchTenantWebhook::EVENTS)],
            'events.*' => ['string', Rule::in(DispatchTenantWebhook::EVENTS)],
        ]);

        $result = $action->execute(new WebhookEndpointData(
            url: $this->url,
            events: array_values(array_unique($this->events)),
        ));

        $this->plainSecret = $result['secret'];
        $this->showingSecret = true;

        $this->reset(['url', 'events']);
        session()->flash('status', __('Webhook registered. Copy the secret now — it will not be shown again.'));
    }

    public function revoke(string $id, DeleteWebhookEndpoint $action): void
    {
        $this->authorize('settings:manage');

        $action->execute(WebhookEndpoint::findOrFail($id));

        session()->flash('status', __('Webhook endpoint removed.'));
    }

    public function closeSecretModal(): void
    {
        $this->plainSecret = '';
        $this->showingSecret = false;
    }

    public function render(): View
    {
        return view('settings-tenant::livewire.manage-webhooks', [
            'endpoints' => WebhookEndpoint::latest()->get(),
            'deliveries' => WebhookDelivery::with('endpoint')->latest()->limit(20)->get(),
            'availableEvents' => DispatchTenantWebhook::EVENTS,
        ]);
    }
}
