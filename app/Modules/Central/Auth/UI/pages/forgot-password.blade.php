<div class="flex flex-col gap-6">
    <x-auth-header
        :title="__('¿Olvidaste tu contraseña?')"
        :description="__('No hay problema. Dinos tu email y te enviaremos un enlace para restablecerla.')"
    />

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="sendResetLink" class="flex flex-col gap-6">
        <!-- Email Address -->
        <flux:input
            wire:model="email"
            :label="__('Email')"
            type="email"
            name="email"
            required
            autofocus
            placeholder="admin@domain.com"
        />

        <div class="flex items-center justify-end mt-4">
            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Enviar enlace de restablecimiento') }}
            </flux:button>
        </div>

        <div class="text-center">
            <flux:link :href="route('central.login')" variant="subtle" wire:navigate>
                {{ __('Volver al login') }}
            </flux:link>
        </div>
    </form>
</div>
