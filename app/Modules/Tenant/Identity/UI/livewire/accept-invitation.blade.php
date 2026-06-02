<div class="flex flex-col gap-6">
    <x-auth-header
        :title="__('Join :tenant', ['tenant' => tenant('name')])"
        :description="__('Complete your account to start collaborating with your team.')"
    />

    <flux:card>
        <form wire:submit="accept" class="flex flex-col gap-6">
            <flux:input 
                wire:model="name" 
                :label="__('Full Name')" 
                required 
                autofocus
            />

            <flux:input 
                :label="__('Email Address')" 
                :value="$invitation->email" 
                disabled 
                description="{{ __('Your account will be linked to this email.') }}"
            />

            <flux:input 
                wire:model="password" 
                :label="__('Create Password')" 
                type="password" 
                required 
                placeholder="********"
                description="{{ __('Min. 12 characters.') }}"
            />

            <flux:input 
                wire:model="password_confirmation" 
                :label="__('Confirm Password')" 
                type="password" 
                required 
                placeholder="********"
            />

            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Accept Invitation & Join') }}
            </flux:button>
        </form>
    </flux:card>
</div>
