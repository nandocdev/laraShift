<div class="flex flex-col gap-6">
    <x-auth-header
        :title="__('Restablecer contraseña')"
        :description="__('Introduce tu nueva contraseña')"
    />

    <form wire:submit="resetPassword" class="flex flex-col gap-6">
        <!-- Email Address -->
        <flux:input
            wire:model="email"
            :label="__('Email')"
            type="email"
            name="email"
            required
            readonly
        />

        <!-- Password -->
        <flux:input
            wire:model="password"
            :label="__('Nueva Contraseña')"
            type="password"
            name="password"
            required
            autofocus
            autocomplete="new-password"
            placeholder="********"
        />

        <!-- Confirm Password -->
        <flux:input
            wire:model="password_confirmation"
            :label="__('Confirmar Contraseña')"
            type="password"
            name="password_confirmation"
            required
            autocomplete="new-password"
            placeholder="********"
        />

        <div class="flex items-center justify-end mt-4">
            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Restablecer contraseña') }}
            </flux:button>
        </div>
    </form>
</div>
