<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Livewire;

use App\Modules\Central\Payments\Actions\RequestPayoutAction;
use App\Modules\Central\Payments\DTOs\PayoutData;
use App\Modules\Central\Payments\Models\PayoutRequest;
use App\Modules\Central\Payments\Models\TenantBankAccount;
use Illuminate\View\View;
use Livewire\Component;

final class PayoutRequests extends Component
{
    public float $amount = 0.0;

    public ?string $error = null;

    public function requestPayout(RequestPayoutAction $action): void
    {
        $this->error = null;

        $account = TenantBankAccount::where('tenant_id', tenancy()->tenant->id)
            ->where('is_active', true)
            ->first();

        if (! $account) {
            $this->error = __('Please configure your bank account details first.');

            return;
        }

        if ($this->amount <= 0) {
            $this->error = __('Amount must be greater than zero.');

            return;
        }

        // Logic to verify tenant balance would go here

        $payoutRequest = PayoutRequest::create([
            'tenant_id' => (string) tenancy()->tenant->id,
            'bank_account_id' => $account->id,
            'amount' => $this->amount,
            'currency' => $account->currency,
            'status' => 'pending',
        ]);

        $data = new PayoutData(
            amount: $this->amount,
            currency: $account->currency,
            country: $account->country,
            tenantId: (string) tenancy()->tenant->id,
            externalId: (string) $payoutRequest->id,
            method: $account->method,
            beneficiary: $account->beneficiary,
            description: 'Payout for tenant '.tenancy()->tenant->id,
        );

        try {
            $result = $action->execute($data);

            $payoutRequest->update([
                'status' => strtolower($result->status),
                'gateway_reference' => $result->id,
                'error_message' => $result->statusDetail,
                'metadata' => $result->raw,
            ]);

            if ($result->isSuccessful() || $result->isPending()) {
                $this->dispatch('toast', variant: 'success', heading: __('Request Submitted'), text: __('Your payout request is being processed.'));
                $this->amount = 0;
            } else {
                $this->error = $result->statusDetail ?? __('Payout request failed.');
            }
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $payoutRequest->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
        }
    }

    public function render(): View
    {
        $requests = PayoutRequest::where('tenant_id', tenancy()->tenant->id)
            ->latest()
            ->paginate(10);

        return view('payments::livewire.payout-requests', [
            'requests' => $requests,
        ]);
    }
}
