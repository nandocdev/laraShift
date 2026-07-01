<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Integrations\Livewire;

use App\Modules\Tenant\Integrations\Actions\RetryWebhookDeliveryAction;
use App\Modules\Tenant\Integrations\Models\TenantWebhookDelivery;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class WebhookDeliveryLog extends Component
{
    use WithPagination;

    public string $filterStatus = '';

    public function retry(string $deliveryId, RetryWebhookDeliveryAction $action): void
    {
        $delivery = TenantWebhookDelivery::findOrFail($deliveryId);

        try {
            $action->execute($delivery);
            $this->dispatch('notify', message: __('Delivery retry queued.'));
        } catch (\RuntimeException $e) {
            $this->addError('retry', $e->getMessage());
        }
    }

    public function render(): View
    {
        $query = TenantWebhookDelivery::with('webhook')
            ->where('tenant_id', tenant('id'))
            ->latest();

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        return view('integrations::livewire.delivery-log', [
            'deliveries' => $query->paginate(30),
        ]);
    }
}
