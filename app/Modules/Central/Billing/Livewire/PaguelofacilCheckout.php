<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Livewire;

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Billing\Support\PagueloFacilClient;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.central')]
class PaguelofacilCheckout extends Component
{
    public Tenant $tenant;
    public Plan $plan;

    // Card Info (Security: Not persisted in DB)
    public string $cardNumber = '';
    public string $expMonth = '';
    public string $expYear = '';
    public string $cvv = '';
    public string $firstName = '';
    public string $lastName = '';

    public function mount(string $tenant, string $plan): void
    {
        $this->tenant = Tenant::findOrFail($tenant);
        $this->plan = Plan::findOrFail($plan);
        
        $nameParts = explode(' ', $this->tenant->name, 2);
        $this->firstName = $nameParts[0];
        $this->lastName = $nameParts[1] ?? '';
    }

    public function process(): void
    {
        $this->validate([
            'cardNumber' => 'required|numeric|digits_between:13,19',
            'expMonth' => 'required|numeric|digits:2',
            'expYear' => 'required|numeric|digits:2',
            'cvv' => 'required|numeric|digits_between:3,4',
            'firstName' => 'required|string|max:50',
            'lastName' => 'required|string|max:50',
        ]);

        $client = new PagueloFacilClient();

        try {
            // 1. In real-world, check if customer already exists or create one
            // For now, we create one for the transaction
            $customer = $client->createCustomer([
                'first_name' => $this->firstName,
                'last_name' => $this->lastName,
                'email' => $this->tenant->email,
            ]);

            // 2. Create Subscription
            $subscriptionData = [
                'plan_id' => $this->plan->provider_plan_id,
                'customer_id' => $customer['data']['idCustomer'] ?? '',
                'amount' => $this->plan->amount,
                'period' => $this->plan->interval === 'month' ? 'mo' : ($this->plan->interval === 'year' ? 'yr' : 'mo'),
                'card_number' => $this->cardNumber,
                'exp_month' => $this->expMonth,
                'exp_year' => $this->expYear,
                'cvv' => $this->cvv,
                'first_name' => $this->firstName,
                'last_name' => $this->lastName,
            ];

            $response = $client->createSubscription($subscriptionData);

            // 3. Persist local record
            $this->tenant->subscriptions()->create([
                'plan_id' => $this->plan->id,
                'provider_subscription_id' => $response['data']['idSubscription'] ?? 'PF_'.uniqid(),
                'status' => 'active',
                'gateway' => 'paguelofacil',
                'current_period_end' => now()->addMonth(), // Rough estimation
            ]);

            session()->flash('success', __('Subscription created successfully via PagueloFacil.'));
            $this->redirect(route('central.billing.success', ['tenant' => $this->tenant->id]));

        } catch (\Exception $e) {
            \Log::error("PagueloFacil Checkout Error: " . $e->getMessage());
            $this->addError('payment', __('Transaction failed: ') . $e->getMessage());
        }
    }

    public function render(): View
    {
        return view('billing::pages.paguelofacil-checkout');
    }
}
