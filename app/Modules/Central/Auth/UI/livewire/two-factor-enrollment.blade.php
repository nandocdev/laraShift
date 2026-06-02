<div class="max-w-2xl mx-auto py-12">
    <div class="mb-8">
        <flux:heading size="xl">{{ __('Two-Factor Authentication') }}</flux:heading>
        <flux:subheading>{{ __('Add an extra layer of security to your account using TOTP.') }}</flux:subheading>
    </div>

    @if (session('status'))
        <flux:card class="mb-6 bg-emerald-50 border-emerald-200">
            <flux:text color="emerald">{{ session('status') }}</flux:text>
        </flux:card>
    @endif

    <flux:card>
        @if ($enabled && empty($recoveryCodes))
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="lg">{{ __('2FA is Enabled') }}</flux:heading>
                    <flux:text>{{ __('Your account is protected with two-factor authentication.') }}</flux:text>
                </div>
                <flux:badge variant="success">{{ __('Active') }}</flux:badge>
            </div>
        @elseif (! empty($recoveryCodes))
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Recovery Codes') }}</flux:heading>
                    <flux:text>{{ __('Store these recovery codes in a secure password manager. They can be used to recover access to your account if your two-factor authentication device is lost.') }}</flux:text>
                </div>

                <div class="grid grid-cols-2 gap-4 font-mono text-sm p-4 bg-zinc-100 dark:bg-zinc-800 rounded-lg">
                    @foreach ($recoveryCodes as $code)
                        <div>{{ $code }}</div>
                    @endforeach
                </div>

                <div class="flex justify-end">
                    <flux:button :href="route('central.dashboard')" wire:navigate>{{ __('I have saved these codes') }}</flux:button>
                </div>
            </div>
        @elseif ($showingQrCode)
            <div class="space-y-6 text-center">
                <flux:heading size="lg">{{ __('Complete 2FA Setup') }}</flux:heading>
                <flux:text>{{ __('Scan the QR code below using your authenticator app (Google Authenticator, Authy, etc.) and enter the 6-digit code.') }}</flux:text>

                <div class="flex justify-center p-4 bg-white rounded-lg inline-block mx-auto">
                    {!! $qrCodeUrl !!}
                </div>

                <div class="max-w-xs mx-auto space-y-4">
                    <flux:input wire:model="code" :label="__('Verification Code')" placeholder="000000" maxlength="6" />
                    <flux:button wire:click="confirm" variant="primary" class="w-full">{{ __('Enable 2FA') }}</flux:button>
                </div>
            </div>
        @else
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="lg">{{ __('2FA is Disabled') }}</flux:heading>
                    <flux:text>{{ __('You have not enabled two-factor authentication yet.') }}</flux:text>
                </div>
                <flux:button wire:click="initiate" variant="primary">{{ __('Setup 2FA') }}</flux:button>
            </div>
        @endif
    </flux:card>
</div>
