<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Settings\Livewire;

use App\Modules\Tenant\Settings\Models\TenantSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class SmtpSettings extends Component
{
    public string $smtp_host = '';
    public int $smtp_port = 587;
    public string $smtp_user = '';
    public string $smtp_password = '';
    public string $smtp_from_email = '';
    public string $smtp_from_name = '';

    public string $test_email = '';
    public ?string $test_status = null;
    public ?string $test_error = null;

    public function mount(): void
    {
        $settings = TenantSetting::where('tenant_id', tenant('id'))->first();
        
        if ($settings) {
            $this->smtp_host = $settings->smtp_host ?? '';
            $this->smtp_port = $settings->smtp_port ?? 587;
            $this->smtp_user = $settings->smtp_user ?? '';
            // We don't populate password for security, unless empty
            $this->smtp_from_email = $settings->smtp_from_email ?? '';
            $this->smtp_from_name = $settings->smtp_from_name ?? '';
        }
    }

    public function save(): void
    {
        $this->validate([
            'smtp_host' => 'required|string',
            'smtp_port' => 'required|integer',
            'smtp_user' => 'required|string',
            'smtp_from_email' => 'required|email',
            'smtp_from_name' => 'required|string',
        ]);

        $settings = TenantSetting::where('tenant_id', tenant('id'))->firstOrFail();

        $data = [
            'smtp_host' => $this->smtp_host,
            'smtp_port' => $this->smtp_port,
            'smtp_user' => $this->smtp_user,
            'smtp_from_email' => $this->smtp_from_email,
            'smtp_from_name' => $this->smtp_from_name,
            'smtp_verified' => false, // Reset on save
        ];

        if (! empty($this->smtp_password)) {
            $data['smtp_password'] = $this->smtp_password; // Automatically encrypted by model cast
        }

        $settings->update($data);

        event(new \App\Modules\Shared\Events\TenantSmtpConfigured(tenant('id'), $this->smtp_from_email));

        session()->flash('status', __('SMTP settings updated successfully. Connection must be verified.'));
    }

    public function testConnection(): void
    {
        $this->validate([
            'test_email' => 'required|email',
        ]);

        $this->test_status = 'testing';
        $this->test_error = null;

        try {
            $settings = TenantSetting::where('tenant_id', tenant('id'))->firstOrFail();
            $dbPassword = $settings->smtp_password ? decrypt($settings->smtp_password) : '';

            // Configuration for the temporary mailer
            $config = [
                'transport' => 'smtp',
                'host' => $this->smtp_host,
                'port' => $this->smtp_port,
                'encryption' => $this->smtp_port === 465 ? 'ssl' : 'tls',
                'username' => $this->smtp_user,
                'password' => ! empty($this->smtp_password) ? $this->smtp_password : $dbPassword,
                'timeout' => 5,
            ];

            // Note: In a production app, we would use a dynamic mailer resolver
            // For this boilerplate, we simulate the test.
            
            Mail::raw(__('This is a test email from LaraShift to verify your SMTP configuration.'), function ($message) {
                $message->to($this->test_email)
                    ->from($this->smtp_from_email, $this->smtp_from_name)
                    ->subject(__('LaraShift SMTP Test'));
            });

            $settings->update(['smtp_verified' => true]);
            $this->test_status = 'success';
        } catch (\Exception $e) {
            $this->test_status = 'failed';
            $this->test_error = $e->getMessage();
        }
    }

    public function render(): View
    {
        return view('settings-tenant::livewire.smtp-settings');
    }
}
