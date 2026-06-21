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
    <div class="sm:mx-auto sm:w-full {{ $step === 1 ? 'sm:max-w-xl' : 'sm:max-w-5xl' }} transition-all duration-300">
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

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <flux:input wire:model.live.debounce.300ms="company" :label="__('Company Name')" placeholder="Acme Corp" required />
                        
                        <div class="space-y-1.5">
                            <flux:label class="font-semibold text-sm">{{ __('Country') }}</flux:label>
                            <select wire:model="country" class="block w-full h-10 px-3 text-sm rounded-lg bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 text-zinc-950 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all shadow-sm">
                                <option value="UY">{{ __('Uruguay') }}</option>
                                <option value="EC">{{ __('Ecuador') }}</option>
                                <option value="AR">{{ __('Argentina') }}</option>
                                <option value="BR">{{ __('Brazil') }}</option>
                                <option value="CL">{{ __('Chile') }}</option>
                                <option value="CO">{{ __('Colombia') }}</option>
                                <option value="MX">{{ __('Mexico') }}</option>
                                <option value="PE">{{ __('Peru') }}</option>
                                <option value="PA">{{ __('Panama') }}</option>
                            </select>
                            @error('country')
                                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

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
                                            {{ \App\Modules\Shared\Infrastructure\Services\PriceFormatter::format($plan->price_monthly) }}
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
                <div class="space-y-8" wire:key="step-3"
                    x-data="registrationCheckout({
                        apiKey: '{{ config('payments.dlocal.smart_fields') }}',
                        locale: '{{ app()->getLocale() }}',
                        country: '{{ $country }}',
                        isPlanFree: {{ $this->isPlanFree() ? 'true' : 'false' }},
                        cardholderName: '{{ $name }}'
                    })"
                >
                    {{-- Section Header --}}
                    <div>
                        <flux:heading size="lg" class="mb-1">{{ __('Confirm & Launch') }}</flux:heading>
                        <flux:subheading class="text-zinc-500">{{ __('Choose your billing method, review your details, and initialize your workspace') }}</flux:subheading>
                    </div>

                    {{-- 1. Full-Width Billing Option Selector --}}
                    @if(!$this->isPlanFree())
                        <div class="space-y-3">
                            <flux:label class="text-xs font-bold uppercase tracking-widest text-zinc-400 dark:text-zinc-500">{{ __('Select Billing Method') }}</flux:label>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <!-- Option A: Trial No Card -->
                                <label @class([
                                    'relative flex flex-col justify-between cursor-pointer rounded-xl border p-5 shadow-sm focus:outline-none transition-all duration-200 hover:shadow-md hover:border-zinc-300 dark:hover:border-zinc-700',
                                    'border-indigo-500 ring-2 ring-indigo-500/20 bg-indigo-50/50 dark:bg-indigo-950/10' => $billing_option === 'trial_no_card',
                                    'border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900' => $billing_option !== 'trial_no_card',
                                ])>
                                    <input type="radio" wire:model.live="billing_option" value="trial_no_card" class="sr-only">
                                    <div class="flex items-start justify-between">
                                        <div class="flex flex-col text-left">
                                            <span class="block text-sm font-bold text-zinc-900 dark:text-white">{{ __('Start 14-day Free Trial') }}</span>
                                            <span class="mt-2 block text-xs text-zinc-500 leading-normal">{{ __('No credit card required. Explore features instantly.') }}</span>
                                        </div>
                                        @if($billing_option === 'trial_no_card')
                                            <flux:icon icon="check-circle" variant="solid" class="text-indigo-600 dark:text-indigo-400 w-5 h-5 shrink-0" />
                                        @endif
                                    </div>
                                    <span class="mt-4 text-[10px] font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/30 px-2.5 py-1 rounded-md self-start uppercase tracking-wider">{{ __('Recommended') }}</span>
                                </label>

                                <!-- Option B: Trial With Card -->
                                <label @class([
                                    'relative flex flex-col justify-between cursor-pointer rounded-xl border p-5 shadow-sm focus:outline-none transition-all duration-200 hover:shadow-md hover:border-zinc-300 dark:hover:border-zinc-700',
                                    'border-indigo-500 ring-2 ring-indigo-500/20 bg-indigo-50/50 dark:bg-indigo-950/10' => $billing_option === 'trial_with_card',
                                    'border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900' => $billing_option !== 'trial_with_card',
                                ])>
                                    <input type="radio" wire:model.live="billing_option" value="trial_with_card" class="sr-only">
                                    <div class="flex items-start justify-between">
                                        <div class="flex flex-col text-left">
                                            <span class="block text-sm font-bold text-zinc-900 dark:text-white">{{ __('14-Day Trial (With Card)') }}</span>
                                            <span class="mt-2 block text-xs text-zinc-500 leading-normal">{{ __('Validate your card today. Pay nothing until your trial ends.') }}</span>
                                        </div>
                                        @if($billing_option === 'trial_with_card')
                                            <flux:icon icon="check-circle" variant="solid" class="text-indigo-600 dark:text-indigo-400 w-5 h-5 shrink-0" />
                                        @endif
                                    </div>
                                    <span class="mt-4 text-[10px] font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/30 px-2.5 py-1 rounded-md self-start uppercase tracking-wider">{{ __('Card Verification') }}</span>
                                </label>

                                <!-- Option C: Pay Now -->
                                <label @class([
                                    'relative flex flex-col justify-between cursor-pointer rounded-xl border p-5 shadow-sm focus:outline-none transition-all duration-200 hover:shadow-md hover:border-zinc-300 dark:hover:border-zinc-700',
                                    'border-indigo-500 ring-2 ring-indigo-500/20 bg-indigo-50/50 dark:bg-indigo-950/10' => $billing_option === 'pay_now',
                                    'border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900' => $billing_option !== 'pay_now',
                                ])>
                                    <input type="radio" wire:model.live="billing_option" value="pay_now" class="sr-only">
                                    <div class="flex items-start justify-between">
                                        <div class="flex flex-col text-left">
                                            <span class="block text-sm font-bold text-zinc-900 dark:text-white">{{ __('Pay & Activate Instantly') }}</span>
                                            <span class="mt-2 block text-xs text-zinc-500 leading-normal">{{ __('Pay immediately. Skip the trial period and start building now.') }}</span>
                                        </div>
                                        @if($billing_option === 'pay_now')
                                            <flux:icon icon="check-circle" variant="solid" class="text-indigo-600 dark:text-indigo-400 w-5 h-5 shrink-0" />
                                        @endif
                                    </div>
                                    <span class="mt-4 text-[10px] font-bold text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/30 px-2.5 py-1 rounded-md self-start uppercase tracking-wider">{{ __('Immediate') }}</span>
                                </label>
                            </div>
                        </div>
                    @endif

                    {{-- 2. Two-Column Grid: Left (Payment Form / Trial Info) & Right (Order Summary & CTA) --}}
                    <div class="grid grid-cols-1 lg:grid-cols-5 gap-8 items-start">
                        {{-- Left Column: 3/5 width --}}
                        <div class="lg:col-span-3 space-y-6">
                            
                            {{-- Error Display --}}
                            <div x-show="error || $wire.error" style="display: none;" class="p-4 text-sm text-red-600 bg-red-50 dark:bg-red-950/30 rounded-xl border border-red-200 dark:border-red-900 shadow-sm">
                                <div class="flex gap-2">
                                    <flux:icon icon="exclamation-triangle" class="w-5 h-5 shrink-0 text-red-500" />
                                    <span x-text="error || $wire.error"></span>
                                </div>
                            </div>

                            @if($this->isPlanFree())
                                {{-- Free Plan Presentation --}}
                                <div class="flex flex-col items-center justify-center p-8 text-center bg-emerald-50/20 dark:bg-emerald-950/10 border border-dashed border-emerald-200 dark:border-emerald-900 rounded-2xl shadow-sm space-y-4">
                                    <div class="w-16 h-16 rounded-full bg-emerald-100 dark:bg-emerald-900/50 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                                        <flux:icon icon="check-circle" class="w-10 h-10" />
                                    </div>
                                    <div class="space-y-1">
                                        <flux:heading size="md" class="text-emerald-800 dark:text-emerald-400 font-extrabold">{{ __('Ready to Launch!') }}</flux:heading>
                                        <flux:text class="max-w-sm text-zinc-500">{{ __('Your permanent free workspace is ready. Click the launch button on the right to start.') }}</flux:text>
                                    </div>
                                </div>
                            @else
                                {{-- If payment is already approved (e.g. page refreshed after success) --}}
                                @if ($paymentAlreadyApproved)
                                    <div class="flex flex-col items-center justify-center p-8 text-center bg-emerald-50/30 dark:bg-emerald-950/10 border border-emerald-200 dark:border-emerald-800/50 rounded-2xl shadow-sm space-y-4">
                                        <div class="w-12 h-12 rounded-full bg-emerald-100 dark:bg-emerald-900/50 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                                            <flux:icon icon="check" variant="mini" class="w-7 h-7" />
                                        </div>
                                        <div class="space-y-1">
                                            <flux:heading size="md" class="text-emerald-800 dark:text-emerald-400 font-extrabold">{{ __('Payment Already Confirmed') }}</flux:heading>
                                            <p class="text-sm text-zinc-500 max-w-sm">
                                                {{ __('Your credit card authorization succeeded. Feel free to launch and provision your workspace.') }}
                                            </p>
                                        </div>
                                    </div>
                                @else
                                    {{-- Case A: Trial No Card --}}
                                    <div x-show="$wire.billing_option === 'trial_no_card'" class="rounded-2xl border border-dashed border-indigo-200 dark:border-indigo-800/80 bg-indigo-50/20 dark:bg-indigo-950/10 p-8 text-center space-y-4">
                                        <div class="mx-auto w-14 h-14 rounded-full bg-indigo-100 dark:bg-indigo-950 flex items-center justify-center text-indigo-600 dark:text-indigo-400 shadow-md">
                                            <flux:icon icon="sparkles" class="w-7 h-7" />
                                        </div>
                                        <div class="max-w-md mx-auto space-y-2">
                                            <h3 class="text-lg font-bold text-zinc-950 dark:text-white">{{ __('Start Your 14-Day Free Trial') }}</h3>
                                            <p class="text-sm text-zinc-500 leading-relaxed">{{ __('Enjoy unrestricted access to all features of the Pro plan. No credit card is required to start. We will send you a reminder email before your trial expires.') }}</p>
                                        </div>
                                    </div>

                                    {{-- Case B: Card Details Input (Trial with Card OR Pay Now) --}}
                                    {{-- Crucial: the #reg-card-field div must always remain in the DOM, so instead of Blade @if, we can use Alpine's x-show --}}
                                    <div x-show="$wire.billing_option !== 'trial_no_card'" class="rounded-2xl border border-zinc-200/80 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-6 space-y-6 shadow-sm">
                                        <div>
                                            <h4 class="text-xs font-bold uppercase tracking-widest text-zinc-400 flex items-center gap-2">
                                                <flux:icon icon="credit-card" variant="mini" class="w-4 h-4 text-zinc-400" />
                                                {{ __('Card Details') }}
                                            </h4>
                                            <p class="text-xs text-zinc-500 mt-1">{{ __('Enter your card credentials below. Secure fields will handle validation automatically.') }}</p>
                                        </div>

                                        <div class="space-y-5 relative">
                                            {{-- Skeletons during iframe initialization --}}
                                            <div x-show="!fieldsMounted" class="absolute inset-0 z-10 bg-white dark:bg-zinc-900 rounded-xl space-y-4 select-none pointer-events-none transition-opacity duration-300">
                                                <div class="space-y-2">
                                                    <div class="h-3 w-28 bg-zinc-200 dark:bg-zinc-800 rounded animate-pulse"></div>
                                                    <div class="h-12 bg-zinc-100 dark:bg-zinc-800/40 rounded-lg animate-pulse"></div>
                                                </div>
                                                <div class="space-y-2">
                                                    <div class="h-3 w-32 bg-zinc-200 dark:bg-zinc-800 rounded animate-pulse"></div>
                                                    <div class="h-12 bg-zinc-100 dark:bg-zinc-800/40 rounded-lg animate-pulse"></div>
                                                </div>
                                            </div>

                                            <div class="space-y-2">
                                                <flux:label class="font-semibold">{{ __('Card Information') }}</flux:label>
                                                <div wire:ignore id="reg-card-field" class="h-12 px-4 py-3 bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-lg focus-within:ring-2 focus-within:ring-indigo-500/20 focus-within:border-indigo-500 transition-all shadow-sm"></div>
                                                <p x-show="fieldError" x-text="fieldError" class="text-xs text-red-500 mt-1" style="display: none;"></p>
                                            </div>

                                            <div class="space-y-2">
                                                <flux:label class="font-semibold">{{ __('Cardholder Name') }}</flux:label>
                                                <input type="text" x-model="cardholderName" placeholder="As shown on card" class="block w-full h-12 px-4 rounded-lg bg-white dark:bg-zinc-900 border border-zinc-300 dark:border-zinc-800 text-zinc-950 dark:text-white placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all shadow-sm">
                                            </div>
                                        </div>

                                        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-zinc-100 dark:border-zinc-800 text-[10px] text-zinc-400 uppercase font-bold tracking-widest">
                                            <div class="flex items-center gap-2">
                                                <flux:icon icon="lock-closed" variant="mini" class="w-4 h-4 text-emerald-500" />
                                                {{ __('Secure 256-bit SSL Connection') }}
                                            </div>
                                            <div>{{ __('Powered by dLocal') }}</div>
                                        </div>

                                        <p class="text-[10px] text-zinc-500 leading-relaxed pt-2 border-t border-zinc-100 dark:border-zinc-800">
                                            {{ config('app.name', 'LaraShift') }} uses dLocal to process payments securely. By providing your payment credentials, you authorize dLocal to validate and securely store your card token for transaction fulfillment. Learn more at dLocal's <a href="https://www.dlocal.com/legal/privacy-hub/" target="_blank" class="text-indigo-500 hover:underline">Privacy Hub</a>.
                                        </p>
                                    </div>
                                @endif
                            @endif

                            {{-- Secondary Back Button --}}
                            <div class="flex justify-start pt-4">
                                <flux:button wire:click="previousStep" variant="ghost" class="px-6 py-2.5">
                                    <flux:icon icon="arrow-left" class="w-4 h-4 mr-2" />
                                    {{ __('Back to Plans') }}
                                </flux:button>
                            </div>
                        </div>

                        {{-- Right Column: 2/5 width (Summary & CTA Card) --}}
                        <div class="lg:col-span-2 space-y-6 lg:sticky lg:top-6">
                            <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 p-6 space-y-5 shadow-sm">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Order Summary') }}</h4>

                                <div class="space-y-4 pt-2">
                                    <div class="flex justify-between items-start text-sm">
                                        <span class="text-zinc-500">{{ __('Organization') }}</span>
                                        <span class="font-bold text-zinc-900 dark:text-white text-right max-w-[180px] truncate">{{ $company }}</span>
                                    </div>
                                    <div class="flex justify-between items-start text-sm">
                                        <span class="text-zinc-500">{{ __('Workspace') }}</span>
                                        <span class="font-semibold text-indigo-600 dark:text-indigo-400 text-right max-w-[180px] truncate">{{ $slug }}.{{ config('tenancy.central_domain') }}</span>
                                    </div>
                                    <div class="flex justify-between items-start text-sm">
                                        <span class="text-zinc-500">{{ __('Email') }}</span>
                                        <span class="font-medium text-zinc-900 dark:text-white text-right max-w-[180px] truncate">{{ $email }}</span>
                                    </div>
                                    <div class="flex justify-between items-start text-sm">
                                        <span class="text-zinc-500">{{ __('Selected Plan') }}</span>
                                        <span class="font-bold text-zinc-900 dark:text-white text-right">{{ $selectedPlan?->name ?? 'Free' }}</span>
                                    </div>
                                </div>

                                <div class="pt-4 border-t border-zinc-200 dark:border-zinc-800 space-y-2">
                                    <div class="flex justify-between items-baseline">
                                        <span class="text-xs font-bold uppercase text-zinc-400 dark:text-zinc-500 tracking-wider">
                                            @if($this->isPlanFree())
                                                {{ __('Plan Price') }}
                                            @elseif($billing_option === 'pay_now')
                                                {{ __('Due Today') }}
                                            @else
                                                {{ __('Due After 14 Days') }}
                                            @endif
                                        </span>
                                        
                                        <div class="text-right">
                                            @if($selectedPlan && $selectedPlan->price_monthly->isPositive())
                                                <span class="text-3xl font-black text-zinc-950 dark:text-white">
                                                    {{ \App\Modules\Shared\Infrastructure\Services\PriceFormatter::format($selectedPlan->price_monthly) }}
                                                </span>
                                                <span class="text-xs text-zinc-500">/{{ __('mo') }}</span>
                                            @else
                                                <span class="text-2xl font-black text-emerald-500">{{ __('Free') }}</span>
                                            @endif
                                        </div>
                                    </div>

                                    @if(!$this->isPlanFree() && $billing_option !== 'pay_now')
                                        <div class="flex justify-between text-xs text-indigo-600 dark:text-indigo-400 font-bold bg-indigo-50/50 dark:bg-indigo-950/20 px-3 py-2 rounded-lg mt-2">
                                            <span>{{ __('Trial Period') }}</span>
                                            <span>{{ __('14 Days Free') }}</span>
                                        </div>
                                    @endif
                                </div>

                                {{-- Main Action Call to Action (CTA) Button --}}
                                <div class="pt-4">
                                    <flux:button x-on:click="handleSubmit" variant="primary" class="w-full py-4 text-base font-bold shadow-lg shadow-indigo-500/10 cursor-pointer h-12" x-bind:disabled="loading || (!isPlanFree && $wire.billing_option !== 'trial_no_card' && (!isFormValid || !fieldsMounted) && !$wire.paymentAlreadyApproved)" wire:loading.attr="disabled">
                                        <span x-show="!loading" wire:loading.remove wire:target="register">
                                            @if($isPlanFree || $billing_option === 'trial_no_card')
                                                {{ __('Create Organization') }}
                                            @elseif($billing_option === 'trial_with_card')
                                                {{ __('Verify Card & Start Trial') }}
                                            @else
                                                {{ __('Pay & Activate') }}
                                            @endif
                                        </span>
                                        <span x-show="loading || $wire.loading" class="flex items-center justify-center gap-2">
                                            <div class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                                            {{ __('Provisioning...') }}
                                        </span>
                                    </flux:button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <script>
                (function() {
                    window.registrationCheckout = function(config) {
                        return {
                            loading: false,
                            fieldsMounted: false,
                            error: null,
                            cardholderName: config.cardholderName,
                            isFormValid: false,
                            isPlanFree: config.isPlanFree,
                            dlocalInstance: null,
                            fields: null,
                            cardFieldInstance: null,
                            fieldError: '',

                            init() {
                                if (this.isPlanFree || this.$wire.paymentAlreadyApproved) return;
                                
                                if (this.$wire.billing_option !== 'trial_no_card') {
                                    this.$nextTick(() => {
                                        this.initDlocal();
                                    });
                                }

                                this.$watch('$wire.billing_option', (value) => {
                                    if ((value === 'trial_with_card' || value === 'pay_now') && !this.fieldsMounted) {
                                        this.$nextTick(() => {
                                            this.initDlocal();
                                        });
                                    }
                                });
                            },

                            initDlocal() {
                                if (typeof dlocal === 'undefined') {
                                    if (!document.querySelector('script[src*="dlocal.com"]')) {
                                        const script = document.createElement('script');
                                        script.src = '{{ config('payments.dlocal.environment') === 'production' ? 'https://js.dlocal.com/' : 'https://js-sandbox.dlocal.com/' }}';
                                        script.async = true;
                                        script.onload = () => this.initDlocal();
                                        document.head.appendChild(script);
                                        return;
                                    }
                                    setTimeout(() => this.initDlocal(), 200);
                                    return;
                                }

                                const container = document.getElementById('reg-card-field');

                                if (!container) {
                                    setTimeout(() => this.initDlocal(), 100);
                                    return;
                                }

                                this.setupFields();
                            },

                            setupFields() {
                                try {
                                    if (!config.apiKey) {
                                        this.error = 'dLocal configuration missing.';
                                        return;
                                    }

                                    this.dlocalInstance = dlocal(config.apiKey);
                                    this.fields = this.dlocalInstance.fields({
                                        locale: config.locale,
                                        country: config.country,
                                        fonts: [{ cssSrc: 'https://fonts.googleapis.com/css?family=Inter' }]
                                    });

                                    const style = {
                                        base: {
                                            fontSize: '14px',
                                            lineHeight: '24px',
                                            color: window.matchMedia('(prefers-color-scheme: dark)').matches ? '#ffffff' : '#000000',
                                            '::placeholder': { color: '#a1a1aa' }
                                        },
                                        invalid: { color: '#ef4444' }
                                    };

                                    this.cardFieldInstance = this.fields.create('card', { style });

                                    try {
                                        this.cardFieldInstance.mount(document.getElementById('reg-card-field'));
                                        this.fieldsMounted = true;
                                    } catch (err) {
                                        this.error = 'Failed to mount secure field.';
                                        console.error('Mount error:', err);
                                    }

                                    const validate = () => {
                                        this.isFormValid = this.cardholderName.length > 2 && !this.fieldError;
                                    };

                                    this.cardFieldInstance.on('change', (e) => {
                                        this.fieldError = e.error ? e.error.message : '';
                                        validate();
                                    });
                                } catch (err) {
                                    console.error('dLocal setup error:', err);
                                    this.error = 'Initialization error. Please try again.';
                                }
                            },

                            async handleSubmit() {
                                this.loading = true;
                                this.error = null;

                                if (this.isPlanFree || this.$wire.paymentAlreadyApproved || this.$wire.billing_option === 'trial_no_card') {
                                    try {
                                        await this.$wire.register();
                                    } catch (e) {
                                        this.error = 'An unexpected error occurred';
                                    } finally {
                                        this.loading = false;
                                    }
                                    return;
                                }

                                try {
                                    const result = await this.dlocalInstance.createToken(this.cardFieldInstance, {
                                        name: this.cardholderName
                                    });

                                    if (result.error) {
                                        this.error = result.error.message;
                                        this.loading = false;
                                        return;
                                    }

                                    this.$wire.set('payment_token', result.token);
                                    await this.$wire.register();
                                } catch (e) {
                                    this.error = 'An unexpected error occurred';
                                } finally {
                                    this.loading = false;
                                }
                            }
                        };
                    }
                })();
            </script>

        </flux:card>
    </div>
</div>
