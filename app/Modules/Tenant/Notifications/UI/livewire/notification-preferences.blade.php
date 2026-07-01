<div class="flex flex-col gap-6 py-12">
    <div>
        <flux:heading size="xl">{{ __('Notification Preferences') }}</flux:heading>
        <flux:subheading>{{ __('Choose which notifications you receive and through which channels.') }}</flux:subheading>
    </div>

    <flux:card class="overflow-hidden">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Notification') }}</flux:table.column>
                <flux:table.column>{{ __('In-App') }}</flux:table.column>
                <flux:table.column>{{ __('Email') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @php
                    $labels = [
                        'auth.login' => __('Login Alerts'),
                        'user.invited' => __('User Invitations'),
                        'role.updated' => __('Role Changes'),
                        'export.ready' => __('Export Ready'),
                        'quota.warning' => __('Quota Warnings'),
                        'billing.receipt' => __('Billing Receipts'),
                        'system.announcement' => __('System Announcements'),
                    ];
                @endphp

                @foreach ($labels as $key => $label)
                    <flux:table.row>
                        <flux:table.cell class="font-medium">{{ $label }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:checkbox wire:model="preferences.{{ $key }}.in-app" />
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:checkbox wire:model="preferences.{{ $key }}.email" />
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <div class="flex justify-end">
        <flux:button variant="primary" wire:click="save">{{ __('Save Preferences') }}</flux:button>
    </div>
</div>
