<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Livewire;

use App\Modules\Central\Payments\Models\TenantBankAccount;
use Livewire\Component;

final class PayoutSettings extends Component
{
    public string $country = 'UY';
    public string $currency = 'USD';
    public string $method = 'BANK_TRANSFER';
    public array $beneficiary = [
        'name' => '',
        'id' => '',
        'bank' => '',
        'account' => '',
        'type' => 'SAVINGS',
    ];

    public function mount(): void
    {
        $account = TenantBankAccount::where('tenant_id', tenancy()->tenant->id)
            ->where('is_active', true)
            ->first();

        if ($account) {
            $this->country = $account->country;
            $this->currency = $account->currency;
            $this->method = $account->method;
            $this->beneficiary = array_merge($this->beneficiary, $account->beneficiary);
        }
    }

    public function save(): void
    {
        $this->validate([
            'beneficiary.name' => 'required|string|min:3',
            'beneficiary.id' => 'required|string',
            'beneficiary.bank' => 'required|string',
            'beneficiary.account' => 'required|string',
        ]);

        TenantBankAccount::updateOrCreate(
            ['tenant_id' => tenancy()->tenant->id, 'is_active' => true],
            [
                'country' => $this->country,
                'currency' => $this->currency,
                'method' => $this->method,
                'beneficiary' => $this->beneficiary,
            ]
        );

        $this->dispatch('toast', variant: 'success', heading: __('Settings Saved'), text: __('Bank account details updated successfully.'));
    }

    public function render(): \Illuminate\View\View
    {
        return view('payments::livewire.payout-settings');
    }
}
