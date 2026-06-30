<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Livewire;

use App\Modules\Central\Payments\Models\PaymentWebhook;
use App\Modules\Shared\Tenancy\Models\Concerns\TenantScope;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.central')]
final class WebhookLog extends Component
{
    use WithPagination;

    public string $filterGateway = '';

    public string $filterStatus = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public ?string $expandedPayload = null;

    public function showPayload(string $webhookId): void
    {
        $webhook = PaymentWebhook::withoutGlobalScope(TenantScope::class)
            ->findOrFail($webhookId);

        $this->expandedPayload = $webhook->raw_payload;
    }

    public function closePayload(): void
    {
        $this->expandedPayload = null;
    }

    public function render(): View
    {
        $query = PaymentWebhook::withoutGlobalScope(TenantScope::class)
            ->latest('created_at');

        if ($this->filterGateway) {
            $query->where('gateway_code', $this->filterGateway);
        }

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        if ($this->dateFrom) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        return view('payments::livewire.webhook-log', [
            'webhooks' => $query->paginate(20),
        ]);
    }
}
