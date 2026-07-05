<div class="flex flex-col gap-6">
    <x-auth-header
        :title="__('Welcome back')"
        :description="__('Log in to your :tenant account.', ['tenant' => tenant('name')])"
    />

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    @if (session('error'))
        <flux:card class="mb-4 bg-red-50 border-red-200">
            <flux:text color="red" size="sm">{{ session('error') }}</flux:text>
        </flux:card>
    @endif

    <form wire:submit="authenticate" class="flex flex-col gap-6">
        <!-- Email Address -->
        <flux:input
            wire:model="email"
            :label="__('Email')"
            type="email"
            required
            autofocus
            autocomplete="username"
            placeholder="email@example.com"
        />

        <!-- Password -->
        <div class="relative">
            <flux:input
                wire:model="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="current-password"
                placeholder="********"
            />

            @if (Route::has('password.request'))
                <flux:link :href="route('password.request')" class="absolute right-0 top-0 text-xs" wire:navigate>
                    {{ __('Forgot your password?') }}
                </flux:link>
            @endif
        </div>

        <!-- Remember Me -->
        <flux:checkbox wire:model="remember" :label="__('Remember me')" />

        <div class="flex items-center justify-end">
            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Log in') }}
            </flux:button>
        </div>
    </form>

    <div class="space-y-2 text-center text-sm">
        <flux:text>{{ __("Don't have an account?") }}</flux:text>
        <flux:link href="#" variant="subtle">{{ __('Contact your administrator') }}</flux:link>
    </div>
</div>
