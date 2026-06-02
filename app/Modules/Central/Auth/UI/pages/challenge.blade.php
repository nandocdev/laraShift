<div class="flex flex-col gap-6">
    <x-auth-header 
        :title="__('Security Challenge')" 
        :description="__('Please enter the 6-digit code from your authenticator app.')" 
    />

    <flux:card>
        <form wire:submit="verify" class="flex flex-col gap-6">
            <flux:input 
                wire:model="code" 
                :label="__('Verification Code')" 
                placeholder="000000" 
                maxlength="6" 
                required 
                autofocus
            />

            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Verify and Login') }}
            </flux:button>
        </form>
    </flux:card>

    <div class="text-center">
        <flux:link :href="route('central.login')" wire:navigate variant="subtle">
            {{ __('Back to login') }}
        </flux:link>
    </div>
</div>
