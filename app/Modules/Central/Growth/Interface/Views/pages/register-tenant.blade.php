<div class="min-h-screen bg-zinc-50 dark:bg-zinc-950 py-12 flex flex-col justify-center sm:px-6 lg:px-8">
    {{-- Header --}}
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <a href="/" class="flex justify-center text-3xl font-extrabold text-indigo-600 dark:text-indigo-400">
            openSaaS
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

    {{-- Step Indicator --}}
    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-2xl">
        <div class="flex items-center justify-center mb-8">
            @foreach([1 => __('Organization'), 2 => __('Confirm')] as $num => $label)
                <div class="flex items-center">
                    <div class="flex flex-col items-center">
                        <div @class([
                            'w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold transition-all duration-300',
                            'bg-indigo-600 text-white shadow-lg shadow-indigo-500/30' => $step >= $num,
                            'bg-zinc-200 dark:bg-zinc-800 text-zinc-500' => $step < $num,
                        ])>
                            @if($step > $num)
                                <flux:icon icon="check" class="w-5 h-5" />
                            @else
                                {{ $num }}
                            @endif
                        </div>
                        <span @class([
                            'mt-2 text-xs font-medium transition-colors',
                            'text-indigo-600 dark:text-indigo-400' => $step >= $num,
                            'text-zinc-400' => $step < $num,
                        ])>{{ $label }}</span>
                    </div>
                    @if($num < 2)
                        <div @class([
                            'w-16 sm:w-24 h-0.5 mx-2 mb-6 transition-all duration-500',
                            'bg-indigo-600' => $step > $num,
                            'bg-zinc-200 dark:bg-zinc-800' => $step <= $num,
                        ])></div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- Wizard Content --}}
    <div class="sm:mx-auto sm:w-full sm:max-w-xl transition-all duration-300">
        <flux:card class="py-8 px-4 shadow-xl sm:rounded-xl sm:px-10 border border-zinc-200/50 dark:border-zinc-800/50">

            {{-- ═══════════════════════════════════════════════════════ --}}
            {{-- STEP 1: Organization Info                              --}}
            {{-- ═══════════════════════════════════════════════════════ --}}
            @if($step === 1)
                <div class="space-y-6" wire:key="step-1">
                    <div>
                        <flux:heading size="lg" class="mb-1">{{ __('Organization Details') }}</flux:heading>
                        <flux:subheading class="text-zinc-500">{{ __('Tell us about your company') }}</flux:subheading>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <flux:input wire:model="name" :label="__('Your Name')" placeholder="Jane Doe" required />
                        <flux:input wire:model="email" type="email" :label="__('Work Email')" placeholder="jane@company.com" required />
                    </div>

                    <flux:input wire:model.live.debounce.300ms="company" :label="__('Company Name')" placeholder="Acme Corp" required />

                    <flux:input wire:model.live.debounce.300ms="slug" :label="__('Workspace URL')" required>
                        <x-slot name="append">
                            .{{ config('tenancy.central_domain') }}
                        </x-slot>
                    </flux:input>

                    <flux:input wire:model="password" type="password" :label="__('Password')" required />

                    <div class="hidden" aria-hidden="true">
                        <flux:input wire:model="honeypot" name="website" autocomplete="off" tabindex="-1" :label="__('Website')" />
                    </div>

                    <div class="pt-4 border-t border-zinc-200 dark:border-zinc-800 flex justify-end">
                        <flux:button wire:click="nextStep" variant="primary" class="px-8">
                            {{ __('Continue') }}
                            <flux:icon icon="arrow-right" class="w-4 h-4 ml-2" />
                        </flux:button>
                    </div>
                </div>
            @endif

            {{-- ═══════════════════════════════════════════════════════ --}}
            {{-- STEP 2: Confirmation                                   --}}
            {{-- ═══════════════════════════════════════════════════════ --}}
            @if($step === 2)
                <div class="space-y-6" wire:key="step-2">
                    <div>
                        <flux:heading size="lg" class="mb-1">{{ __('Confirm & Launch') }}</flux:heading>
                        <flux:subheading class="text-zinc-500">{{ __('Review your details and finalize registration') }}</flux:subheading>
                    </div>

                    {{-- Summary --}}
                    <div class="rounded-lg bg-zinc-100 dark:bg-zinc-900 p-5 space-y-3">
                        <h4 class="text-sm font-bold uppercase tracking-wider text-zinc-500">{{ __('Summary') }}</h4>
                        <div class="flex justify-between text-sm">
                            <span class="text-zinc-600 dark:text-zinc-400">{{ __('Organization') }}</span>
                            <span class="font-medium text-zinc-900 dark:text-white">{{ $company }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-zinc-600 dark:text-zinc-400">{{ __('Workspace') }}</span>
                            <span class="font-medium text-zinc-900 dark:text-white">{{ $slug }}.{{ config('tenancy.central_domain') }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-zinc-600 dark:text-zinc-400">{{ __('Email') }}</span>
                            <span class="font-medium text-zinc-900 dark:text-white">{{ $email }}</span>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-zinc-200 dark:border-zinc-800 flex justify-between">
                        <flux:button wire:click="previousStep" variant="ghost">
                            <flux:icon icon="arrow-left" class="w-4 h-4 mr-2" />
                            {{ __('Back') }}
                        </flux:button>

                        <flux:button wire:click="register" variant="primary" class="px-8" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="register">
                                {{ __('Create Organization') }}
                            </span>
                            <span wire:loading wire:target="register">{{ __('Processing...') }}</span>
                        </flux:button>
                    </div>
                </div>
            @endif

        </flux:card>
    </div>
</div>
