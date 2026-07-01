<div class="flex flex-col gap-6 py-8 max-w-2xl mx-auto">
    <div class="flex items-center gap-4">
        <flux:button icon="arrow-left" variant="ghost" :href="route('central.support.tickets')" wire:navigate />
        <div>
            <flux:heading size="xl">{{ __('Create Support Ticket') }}</flux:heading>
            <flux:subheading>{{ __('Log an issue for a tenant.') }}</flux:subheading>
        </div>
    </div>

    <flux:card>
        <form wire:submit="save" class="space-y-6">
            <flux:select wire:model="tenantId" :label="__('Tenant')" required searchable>
                <option value="">{{ __('Select tenant...') }}</option>
                @foreach ($tenants as $tenant)
                    <option value="{{ $tenant->id }}">{{ $tenant->name }} ({{ $tenant->slug }})</option>
                @endforeach
            </flux:select>

            <flux:input wire:model="subject" :label="__('Subject')" placeholder="{{ __('Brief description of the issue') }}" required />

            <flux:textarea wire:model="description" :label="__('Description')" rows="5"
                placeholder="{{ __('Detailed explanation of the issue...') }}" required />

            <div class="grid grid-cols-2 gap-4">
                <flux:select wire:model="priority" :label="__('Priority')">
                    <option value="low">{{ __('Low') }}</option>
                    <option value="medium" selected>{{ __('Medium') }}</option>
                    <option value="high">{{ __('High') }}</option>
                    <option value="critical">{{ __('Critical') }}</option>
                </flux:select>

                <flux:select wire:model="assignedTo" :label="__('Assign to')">
                    <option value="">{{ __('Unassigned') }}</option>
                    @foreach ($agents as $agent)
                        <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                    @endforeach
                </flux:select>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button :href="route('central.support.tickets')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Create Ticket') }}</flux:button>
            </div>
        </form>
    </flux:card>
</div>
