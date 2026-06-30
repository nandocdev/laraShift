<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Livewire;

use App\Modules\Central\Payments\Actions\LoadMerchantAction;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.central')]
final class GatewaySettings extends Component
{
    public string $gateway = '';

    public string $environment = '';

    public bool $testing = false;

    public ?string $testResult = null;

    public bool $testSuccess = false;

    public function mount(): void
    {
        $this->gateway = config('payments.default', 'dlocal');
        $this->environment = config("payments.{$this->gateway}.environment", 'sandbox');
    }

    public function updatedGateway(string $value): void
    {
        $this->gateway = $value;
        $this->environment = config("payments.{$value}.environment", 'sandbox');
        $this->testResult = null;
    }

    public function testConnection(LoadMerchantAction $action): void
    {
        $this->testing = true;
        $this->testResult = null;

        try {
            $apiKey = config("payments.{$this->gateway}.api_key")
                ?? config("payments.{$this->gateway}.login")
                ?? '';

            if (empty($apiKey)) {
                $this->testResult = __('API key not configured for this gateway.');
                $this->testSuccess = false;

                return;
            }

            $merchant = $action->execute($apiKey);

            $this->testResult = __('Connection successful. Merchant: :name', ['name' => $merchant->name]);
            $this->testSuccess = true;
        } catch (\Exception $e) {
            $this->testResult = $e->getMessage();
            $this->testSuccess = false;
        } finally {
            $this->testing = false;
        }
    }

    public function render(): View
    {
        $gatewayConfig = config("payments.{$this->gateway}", []);

        $configured = collect($gatewayConfig)
            ->except('environment')
            ->filter(fn ($value) => ! empty($value))
            ->isNotEmpty();

        return view('payments::livewire.gateway-settings', [
            'gatewayConfig' => $gatewayConfig,
            'configured' => $configured,
            'hasApiKey' => ! empty($gatewayConfig['api_key'] ?? $gatewayConfig['login'] ?? ''),
            'hasWebhookSecret' => ! empty($gatewayConfig['webhook_secret'] ?? ''),
        ]);
    }
}
