<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('New Tenant') }}</flux:heading>
        <flux:subheading>{{ __('Atomic provisioning of a new SaaS instance.') }}</flux:subheading>
    </div>

    <flux:card>
        <form wire:submit="save" class="flex flex-col gap-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <flux:input 
                    wire:model="name" 
                    :label="__('Company Name')" 
                    placeholder="Acme Corp" 
                    required 
                />

                <flux:input 
                    wire:model="slug" 
                    :label="__('Subdomain / Slug')" 
                    placeholder="acme" 
                    required 
                    suffix=".{{ config('app.central_domain') }}" 
                />

                <flux:input 
                    wire:model="email" 
                    :label="__('Owner Email')" 
                    type="email" 
                    placeholder="admin@acme.com" 
                    required 
                />

                <flux:select wire:model="plan_id" :label="__('Plan')">
                    <option value="free">Free</flux:select.option>
                    <option value="pro">Pro</flux:select.option>
                    <option value="enterprise">Enterprise</flux:select.option>
                </flux:select>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button :href="route('central.provisioning.index')" variant="ghost" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('Provision Tenant') }}
                </flux:button>
            </div>
        </form>
    </flux:card>
</div>
