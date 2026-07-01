<div class="flex flex-col gap-6 py-12">
    <div>
        <flux:heading size="xl">{{ __('Data Retention Policies') }}</flux:heading>
        <flux:subheading>{{ __('Configure how long different types of data are kept before automatic purging.') }}</flux:subheading>
    </div>

    <flux:card class="space-y-6">
        <div class="grid grid-cols-2 gap-4">
            <flux:input wire:model="audit_logs" type="number" min="30" max="3650" :label="__('Audit Logs (days)')" />
            <flux:input wire:model="notifications" type="number" min="30" max="3650" :label="__('Notifications (days)')" />
            <flux:input wire:model="activity_log" type="number" min="30" max="3650" :label="__('Activity Log (days)')" />
            <flux:input wire:model="exports" type="number" min="1" max="365" :label="__('Exports (days)')" />
            <flux:input wire:model="backups" type="number" min="1" max="90" :label="__('Backups (days)')" />
        </div>

        <div class="flex justify-end">
            <flux:button variant="primary" wire:click="save">{{ __('Save Policies') }}</flux:button>
        </div>
    </flux:card>
</div>
