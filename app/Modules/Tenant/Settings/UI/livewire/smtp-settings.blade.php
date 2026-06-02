<div class="flex flex-col gap-6 max-w-2xl mx-auto py-12">
    <div>
        <flux:heading size="xl">{{ __('SMTP Configuration') }}</flux:heading>
        <flux:subheading>{{ __('Configure your own outgoing mail server for all emails sent from this account.') }}</flux:subheading>
    </div>

    @if (session('status'))
        <flux:text color="emerald">{{ session('status') }}</flux:text>
    @endif

    <flux:card>
        <form wire:submit="save" class="flex flex-col gap-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="md:col-span-3">
                    <flux:input wire:model="smtp_host" :label="__('SMTP Host')" placeholder="smtp.mailgun.org" required />
                </div>
                <div class="md:col-span-1">
                    <flux:input wire:model="smtp_port" type="number" :label="__('Port')" placeholder="587" required />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <flux:input wire:model="smtp_user" :label="__('Username')" placeholder="postmaster@yourdomain.com" required />
                <flux:input wire:model="smtp_password" type="password" :label="__('Password')" placeholder="********" description="{{ __('Leave empty to keep current password.') }}" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <flux:input wire:model="smtp_from_email" :label="__('Sender Email')" placeholder="no-reply@yourdomain.com" required />
                <flux:input wire:model="smtp_from_name" :label="__('Sender Name')" placeholder="Acme Support" required />
            </div>

            <div class="flex justify-end gap-2 mt-4">
                <flux:button type="submit" variant="primary">
                    {{ __('Save SMTP Settings') }}
                </flux:button>
            </div>
        </form>
    </flux:card>

    <div class="mt-8">
        <flux:heading size="lg">{{ __('Test Connection') }}</flux:heading>
        <flux:subheading>{{ __('Send a test email to verify your settings are correct.') }}</flux:subheading>
    </div>

    <flux:card>
        <div class="flex flex-col gap-4">
            <div class="flex items-end gap-4">
                <div class="flex-1">
                    <flux:input wire:model="test_email" :label="__('Recipient Email')" placeholder="your-email@example.com" />
                </div>
                <flux:button wire:click="testConnection" variant="ghost" icon="paper-airplane" :loading="$test_status === 'testing'">
                    {{ __('Send Test Email') }}
                </flux:button>
            </div>

            @if ($test_status === 'success')
                <div class="p-3 bg-emerald-50 border border-emerald-200 rounded text-emerald-700 text-sm flex items-center gap-2">
                    <flux:icon icon="check-circle" size="sm" />
                    {{ __('Test email sent successfully! Check your inbox.') }}
                </div>
            @elseif ($test_status === 'failed')
                <div class="p-3 bg-red-50 border border-red-200 rounded text-red-700 text-sm">
                    <div class="font-bold flex items-center gap-2 mb-1">
                        <flux:icon icon="x-circle" size="sm" />
                        {{ __('Connection failed') }}
                    </div>
                    <div class="font-mono text-[10px] break-all">{{ $test_error }}</div>
                </div>
            @endif
        </div>
    </flux:card>
</div>
