<div class="flex flex-col gap-6">
    <x-auth-header
        :title="__('Login Central')"
        :description="__('Introduce tus credenciales administrativas')"
    />

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="authenticate" class="flex flex-col gap-6">
        <!-- Email Address -->
        <flux:input
            wire:model="email"
            :label="__('Email')"
            type="email"
            name="email"
            required
            autofocus
            autocomplete="username"
            placeholder="admin@domain.com"
        />

        <!-- Password -->
        <div class="relative">
            <div class="flex justify-between mb-2">
                <flux:label>{{ __('Contraseña') }}</flux:label>
                <flux:link :href="route('central.password.request')" variant="subtle" class="text-xs" wire:navigate>
                    {{ __('¿Olvidaste tu contraseña?') }}
                </flux:link>
            </div>
            <flux:input
                wire:model="password"
                type="password"
                name="password"
                required
                autocomplete="current-password"
                placeholder="********"
            />
        </div>

        <!-- Remember Me -->
        <flux:checkbox wire:model="remember" :label="__('Recordarme')" />

        <div class="flex items-center justify-end">
            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Iniciar Sesión') }}
            </flux:button>
        </div>
    </form>
</div>
