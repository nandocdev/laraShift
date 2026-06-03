<div class="min-h-screen bg-zinc-50 dark:bg-zinc-950 py-12 flex flex-col justify-center sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <a href="/" class="flex justify-center text-3xl font-extrabold text-indigo-600 dark:text-indigo-400">
            LaraShift
        </a>
        <flux:heading size="xl" class="mt-6 text-center text-3xl font-extrabold text-zinc-900 dark:text-white">
            {{ __('Create your organization') }}
        </flux:heading>
        <p class="mt-2 text-center text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('Or') }}
            <a href="{{ route('central.login') }}" class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                {{ __('sign in to an existing account') }}
            </a>
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-xl">
        <flux:card class="py-8 px-4 shadow sm:rounded-lg sm:px-10">
            <form wire:submit="register" class="space-y-6">
                <!-- Personal Info -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <flux:input wire:model="name" :label="__('Your Name')" placeholder="Jane Doe" required />
                    <flux:input wire:model="email" type="email" :label="__('Work Email')" placeholder="jane@company.com" required />
                </div>

                <!-- Organization Info -->
                <flux:input wire:model.blur="company" :label="__('Company Name')" placeholder="Acme Corp" required />
                
                <flux:input wire:model="slug" :label="__('Workspace URL')" required>
                    <x-slot name="append">
                        .{{ config('tenancy.central_domain') }}
                    </x-slot>
                </flux:input>

                <!-- Password -->
                <flux:input wire:model="password" type="password" :label="__('Password')" required />
                
                <!-- Plan Selection -->
                <flux:select wire:model="plan_id" :label="__('Selected Plan')" required>
                    @foreach($plans as $plan)
                        <option value="{{ $plan->slug }}">{{ $plan->name }} - ${{ number_format($plan->price_monthly / 100, 2) }}/mo</option>
                    @endforeach
                </flux:select>

                <div class="pt-4 border-t border-zinc-200 dark:border-zinc-800">
                    <flux:button type="submit" variant="primary" class="w-full justify-center">
                        {{ __('Create Organization') }}
                    </flux:button>
                </div>
            </form>
        </flux:card>
    </div>
</div>
