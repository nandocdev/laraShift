<div class="min-h-screen bg-zinc-50 dark:bg-zinc-950 py-12 flex flex-col justify-center sm:px-6 lg:px-8">
    {{-- Header --}}
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

    {{-- Step Indicator --}}
    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-2xl">
        <div class="flex items-center justify-center mb-8">
            @foreach([1 => __('Organization'), 2 => __('Plan'), 3 => __('Confirm')] as $num => $label)
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
                    @if($num < 3)
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
    <div class="sm:mx-auto sm:w-full {{ $step === 2 ? 'sm:max-w-5xl' : 'sm:max-w-xl' }} transition-all duration-300">
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

                    <div class="pt-4 border-t border-zinc-200 dark:border-zinc-800 flex justify-end">
                        <flux:button wire:click="nextStep" variant="primary" class="px-8">
                            {{ __('Continue') }}
                            <flux:icon icon="arrow-right" class="w-4 h-4 ml-2" />
                        </flux:button>
                    </div>
                </div>
            @endif

            {{-- ═══════════════════════════════════════════════════════ --}}
            {{-- STEP 2: Plan Selection                                 --}}
            {{-- ═══════════════════════════════════════════════════════ --}}
            @if($step === 2)
                <div class="space-y-6" wire:key="step-2">
                    <div class="text-center">
                        <flux:heading size="lg" class="mb-1">{{ __('Choose your plan') }}</flux:heading>
                        <flux:subheading class="text-zinc-500">{{ __('Start free and upgrade as you grow') }}</flux:subheading>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        @foreach($plans as $plan)
                            <div
                                wire:click="selectPlan('{{ $plan->slug }}')"
                                @class([
                                    'relative cursor-pointer rounded-xl p-6 border-2 transition-all duration-200 hover:shadow-lg',
                                    'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-950/20 shadow-md ring-1 ring-indigo-500/20' => $plan_id === $plan->slug,
                                    'border-zinc-200 dark:border-zinc-800 hover:border-zinc-300 dark:hover:border-zinc-700' => $plan_id !== $plan->slug,
                                ])
                            >
                                {{-- Selected badge --}}
                                @if($plan_id === $plan->slug)
                                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-0.5 bg-indigo-600 text-white text-xs font-bold rounded-full uppercase tracking-wider">
                                        {{ __('Selected') }}
                                    </div>
                                @endif

                                {{-- Popular badge --}}
                                @if($plan->slug === 'pro' && $plan_id !== $plan->slug)
                                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-0.5 bg-amber-500 text-white text-xs font-bold rounded-full uppercase tracking-wider">
                                        {{ __('Popular') }}
                                    </div>
                                @endif

                                <div class="mb-4">
                                    <h3 class="text-lg font-bold text-zinc-900 dark:text-white">{{ $plan->name }}</h3>
                                    <div class="flex items-baseline gap-1 mt-2">
                                        <span class="text-3xl font-extrabold tracking-tight text-zinc-900 dark:text-white">
                                            ${{ number_format($plan->price_monthly / 100, 2) }}
                                        </span>
                                        <span class="text-zinc-500 text-sm">/{{ __('month') }}</span>
                                    </div>
                                </div>

                                <ul class="space-y-3 mb-4">
                                    @foreach($plan->features['display_features'] ?? [] as $feature)
                                        <li class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                                            <flux:icon icon="check" class="text-emerald-500 shrink-0 w-4 h-4" />
                                            <span>{{ $feature }}</span>
                                        </li>
                                    @endforeach
                                </ul>

                                @if(isset($plan->features['quotas']))
                                    <div class="pt-3 border-t border-zinc-100 dark:border-zinc-800 space-y-1">
                                        <div class="text-[10px] font-bold uppercase text-zinc-400 tracking-wider">{{ __('Quotas') }}</div>
                                        <div class="flex justify-between text-xs text-zinc-500">
                                            <span>{{ __('Branches') }}</span>
                                            <span class="font-bold">{{ ($plan->features['quotas']['branches'] ?? 0) < 0 ? '∞' : $plan->features['quotas']['branches'] }}</span>
                                        </div>
                                        <div class="flex justify-between text-xs text-zinc-500">
                                            <span>{{ __('Staff') }}</span>
                                            <span class="font-bold">{{ ($plan->features['quotas']['staff'] ?? 0) < 0 ? '∞' : $plan->features['quotas']['staff'] }}</span>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div class="pt-4 border-t border-zinc-200 dark:border-zinc-800 flex justify-between">
                        <flux:button wire:click="previousStep" variant="ghost">
                            <flux:icon icon="arrow-left" class="w-4 h-4 mr-2" />
                            {{ __('Back') }}
                        </flux:button>
                        <flux:button wire:click="nextStep" variant="primary" class="px-8">
                            {{ __('Continue') }}
                            <flux:icon icon="arrow-right" class="w-4 h-4 ml-2" />
                        </flux:button>
                    </div>
                </div>
            @endif

            {{-- ═══════════════════════════════════════════════════════ --}}
            {{-- STEP 3: Payment & Confirmation                         --}}
            {{-- ═══════════════════════════════════════════════════════ --}}
            @if($step === 3)
                <div class="space-y-6" wire:key="step-3">
                    <div>
                        <flux:heading size="lg" class="mb-1">{{ __('Confirm & Launch') }}</flux:heading>
                        <flux:subheading class="text-zinc-500">{{ __('Review your details and finalize registration') }}</flux:subheading>
                    </div>

                    {{-- Order Summary --}}
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
                            <span class="text-zinc-600 dark:text-zinc-400">{{ __('Plan') }}</span>
                            <span class="font-medium text-zinc-900 dark:text-white">{{ $selectedPlan?->name ?? 'Free' }}</span>
                        </div>
                        @if($selectedPlan && $selectedPlan->price_monthly > 0)
                            <div class="flex justify-between text-sm pt-2 border-t border-zinc-200 dark:border-zinc-800">
                                <span class="font-bold text-zinc-900 dark:text-white">{{ __('Monthly Total') }}</span>
                                <span class="font-bold text-indigo-600 dark:text-indigo-400 text-lg">
                                    ${{ number_format($selectedPlan->price_monthly / 100, 2) }}
                                </span>
                            </div>
                            <p class="text-xs text-zinc-500 mt-2">
                                {{ __('You will be redirected to our secure payment gateway (PagueloFacil) to complete your subscription.') }}
                            </p>
                        @else
                            <div class="flex justify-between text-sm pt-2 border-t border-zinc-200 dark:border-zinc-800">
                                <span class="font-bold text-zinc-900 dark:text-white">{{ __('Monthly Total') }}</span>
                                <span class="font-bold text-emerald-600 text-lg">{{ __('Free') }}</span>
                            </div>
                        @endif
                    </div>

                    <div class="pt-4 border-t border-zinc-200 dark:border-zinc-800 flex justify-between">
                        <flux:button wire:click="previousStep" variant="ghost">
                            <flux:icon icon="arrow-left" class="w-4 h-4 mr-2" />
                            {{ __('Back') }}
                        </flux:button>

                        <flux:button wire:click="register" variant="primary" class="px-8" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="register">
                                {{ $isPlanFree ? __('Create Organization') : __('Create Organization & Pay') }}
                            </span>
                            <span wire:loading wire:target="register">{{ __('Processing...') }}</span>
                        </flux:button>
                    </div>
                </div>
            @endif

        </flux:card>
    </div>
</div>
