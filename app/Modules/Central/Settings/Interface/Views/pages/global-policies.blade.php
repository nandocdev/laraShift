<div class="space-y-6 max-w-2xl">
    <div>
        <flux:heading size="xl">{{ __('Platform Policies') }}</flux:heading>
        <flux:subheading>{{ __('Global thresholds and operational policies. Changes apply immediately.') }}</flux:subheading>
    </div>

    <flux:separator />

    @if (session('status'))
        <flux:text color="emerald">{{ session('status') }}</flux:text>
    @endif

    <flux:card>
        <form wire:submit="save" class="space-y-6">
            <flux:field>
                <flux:label>{{ __('Fraud quarantine threshold (score)') }}</flux:label>
                <flux:input wire:model="fraudThreshold" type="number" min="1" max="100" />
                <flux:error name="fraudThreshold" />
                <flux:description>{{ __('Registrations scoring at or above this value are quarantined.') }}</flux:description>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('SecOps alert email') }}</flux:label>
                <flux:input wire:model="secopsEmail" type="email" placeholder="secops@example.com" />
                <flux:error name="secopsEmail" />
                <flux:description>{{ __('Empty disables quarantine email alerts.') }}</flux:description>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Stale provisioning (minutes)') }}</flux:label>
                <flux:input wire:model="staleMinutes" type="number" min="1" max="1440" />
                <flux:error name="staleMinutes" />
                <flux:description>{{ __('Tenants stuck in provisioning longer than this are retried by the reconciler.') }}</flux:description>
            </flux:field>

            <div>
                <flux:button type="submit" variant="primary">{{ __('Save policies') }}</flux:button>
            </div>
        </form>
    </flux:card>
</div>
