<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Interface\Livewire;

use App\Modules\Central\Billing\Application\Actions\CancelSubscriptionAction;
use App\Modules\Central\Billing\Domain\Models\Invoice;
use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Central\Billing\Domain\Models\Subscription;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ManageBilling extends Component
{
    use AuthorizesRequests;

    public bool $confirmingCancel = false;

    public function cancel(CancelSubscriptionAction $action): void
    {
        $subscription = $this->subscription();

        if (! $subscription) {
            return;
        }

        $action->execute($subscription);
        $this->confirmingCancel = false;

        session()->flash('status', __('Subscription will be canceled at period end.'));
    }

    public function resume(CancelSubscriptionAction $action): void
    {
        $subscription = $this->subscription();

        if (! $subscription) {
            return;
        }

        $action->resume($subscription);

        session()->flash('status', __('Subscription resumed.'));
    }

    private function subscription(): ?Subscription
    {
        return Subscription::where('tenant_id', tenant()->getId())->latest()->first();
    }

    public function render(): View
    {
        $tenantId = tenant()->getId();

        return view('billing::livewire.manage-billing', [
            'subscription' => $this->subscription(),
            'payments' => Payment::where('tenant_id', $tenantId)->latest()->limit(5)->get(),
            'invoices' => Invoice::where('tenant_id', $tenantId)->latest()->limit(5)->get(),
        ]);
    }
}
