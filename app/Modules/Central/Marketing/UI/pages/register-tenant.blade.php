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
                <div class="space-y-6" wire:key="step-3"
                    x-data="registrationCheckout({
                        apiKey: '{{ config('payments.dlocal.login') }}',
                        locale: '{{ app()->getLocale() }}',
                        country: 'US',
                        isPlanFree: {{ $this->isPlanFree() ? 'true' : 'false' }},
                        cardholderName: '{{ $name }}'
                    })"
                >
                    <div>
                        <flux:heading size="lg" class="mb-1">{{ __('Confirm & Launch') }}</flux:heading>
                        <flux:subheading class="text-zinc-500">{{ __('Review your details and finalize registration') }}</flux:subheading>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        {{-- Left: Order Summary --}}
                        <div class="space-y-6">
                            <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 p-6 space-y-4">
                                <h4 class="text-xs font-bold uppercase tracking-widest text-zinc-400">{{ __('Registration Summary') }}</h4>
                                
                                <div class="space-y-3">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-zinc-500">{{ __('Organization') }}</span>
                                        <span class="font-semibold text-zinc-900 dark:text-white">{{ $company }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-zinc-500">{{ __('Workspace URL') }}</span>
                                        <span class="font-semibold text-indigo-600 dark:text-indigo-400">{{ $slug }}.{{ config('tenancy.central_domain') }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-zinc-500">{{ __('Email') }}</span>
                                        <span class="font-medium text-zinc-900 dark:text-white">{{ $email }}</span>
                                    </div>
                                </div>

                                <div class="pt-4 border-t border-zinc-200 dark:border-zinc-800">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <span class="text-xs font-bold uppercase text-zinc-400 tracking-wider">{{ __('Selected Plan') }}</span>
                                            <div class="text-lg font-bold text-zinc-900 dark:text-white">{{ $selectedPlan?->name ?? 'Free' }}</div>
                                        </div>
                                        <div class="text-right">
                                            @if($selectedPlan && $selectedPlan->price_monthly->isPositive())
                                                <div class="text-2xl font-black text-indigo-600 dark:text-indigo-400">
                                                    {{ \App\Modules\Shared\Infrastructure\Services\PriceFormatter::format($selectedPlan->price_monthly) }}
                                                </div>
                                                <div class="text-[10px] uppercase text-zinc-500 font-bold">{{ __('per month') }}</div>
                                            @else
                                                <div class="text-2xl font-black text-emerald-500">{{ __('Free') }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Right: Payment Details (if applicable) --}}
                        <div class="space-y-4">
                            @if(!$this->isPlanFree())
                                <h4 class="text-xs font-bold uppercase tracking-widest text-zinc-400">{{ __('Payment Information') }}</h4>
                                
                                <div x-show="error || ($wire.payment_token === null && $wire.error)" style="display: none;" class="p-3 text-xs text-red-600 bg-red-50 dark:bg-red-950/30 rounded-lg border border-red-200 dark:border-red-900">
                                    <span x-text="error || $wire.error"></span>
                                </div>

                                @if ($paymentAlreadyApproved)
                                    <div class="flex flex-col items-center justify-center p-6 text-center bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-900 rounded-xl space-y-3 min-h-[200px]">
                                        <div class="w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-900/50 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                                            <flux:icon icon="check" variant="mini" class="w-6 h-6" />
                                        </div>
                                        <flux:heading size="sm" class="text-emerald-700 dark:text-emerald-400">{{ __('Payment Completed') }}</flux:heading>
                                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ __('Your payment has been successfully processed. You can safely retry provisioning your workspace without being charged again.') }}
                                        </p>
                                    </div>
                                @else
                                    <div class="relative space-y-4 bg-white dark:bg-zinc-900 p-5 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm min-h-[200px]">
                                        {{-- Loading Overlay with Structured Skeletons --}}
                                        <div x-show="!fieldsMounted" class="absolute inset-0 z-10 bg-white dark:bg-zinc-900 rounded-xl p-5 space-y-4 select-none pointer-events-none">
                                            {{-- Card Input Skeleton --}}
                                            <div class="space-y-2">
                                                <div class="h-3 w-24 bg-zinc-200 dark:bg-zinc-800 rounded animate-pulse"></div>
                                                <div class="h-10 bg-zinc-100 dark:bg-zinc-800/50 rounded-lg animate-pulse"></div>
                                            </div>

                                            {{-- Cardholder Name Skeleton --}}
                                            <div class="space-y-2">
                                                <div class="h-3 w-28 bg-zinc-200 dark:bg-zinc-800 rounded animate-pulse"></div>
                                                <div class="h-10 bg-zinc-100 dark:bg-zinc-800/50 rounded-lg animate-pulse"></div>
                                            </div>

                                            <div class="flex items-center justify-between pt-1">
                                                <div class="h-2 w-32 bg-zinc-200 dark:bg-zinc-800 rounded animate-pulse"></div>
                                                <div class="flex items-center gap-1.5 text-zinc-400 dark:text-zinc-500">
                                                    <div class="w-3 h-3 border-2 border-zinc-300 dark:border-zinc-700 border-t-zinc-600 rounded-full animate-spin"></div>
                                                    <span class="text-[9px] font-bold uppercase tracking-wider animate-pulse">{{ __('Loading secure inputs...') }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="space-y-1">
                                            <flux:label size="sm">{{ __('Card Information') }}</flux:label>
                                            <div wire:ignore id="reg-card-field" class="h-10 px-3 py-2 bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-lg"></div>
                                            <p x-show="fieldError" x-text="fieldError" class="text-[10px] text-red-500 mt-1"></p>
                                        </div>

                                        <div class="space-y-1">
                                            <flux:label size="sm">{{ __('Cardholder Name') }}</flux:label>
                                            <flux:input x-model="cardholderName" size="sm" placeholder="As shown on card" />
                                        </div>
                                    </div>
                                @endif
                                
                                <div class="flex items-center justify-center gap-2 text-[10px] text-zinc-400 uppercase font-bold tracking-widest">
                                    <flux:icon icon="lock-closed" variant="mini" class="w-3 h-3" />
                                    {{ __('Securely processed by dLocal') }}
                                </div>
                                <p class="text-[10px] text-zinc-500 leading-tight">
                                    {{ config('app.name', 'LaraShift') }} is powered by dLocal, which has been appointed by {{ config('app.name', 'LaraShift') }} to provide payment services on its behalf, including the collection of the data necessary to facilitate and remit your payments. As such, you are now providing your personal data to dLocal. For more information, please visit dLocal’s <a href="https://www.dlocal.com/legal/privacy-hub/" target="_blank" class="text-indigo-500 hover:underline">Privacy Hub</a>.
                                </p>
                            @else
                                <div class="flex flex-col items-center justify-center h-full p-8 text-center bg-emerald-50/30 dark:bg-emerald-950/10 border border-dashed border-emerald-200 dark:border-emerald-900 rounded-xl">
                                    <flux:icon icon="check-circle" class="w-12 h-12 text-emerald-500 mb-3" />
                                    <flux:heading size="sm">{{ __('Ready to launch') }}</flux:heading>
                                    <flux:text size="sm" class="mt-1">{{ __('Your free organization is one click away.') }}</flux:text>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="pt-6 border-t border-zinc-200 dark:border-zinc-800 flex justify-between">
                        <flux:button wire:click="previousStep" variant="ghost">
                            <flux:icon icon="arrow-left" class="w-4 h-4 mr-2" />
                            {{ __('Back') }}
                        </flux:button>

                        <flux:button x-on:click="handleSubmit" variant="primary" class="px-12 py-3" x-bind:disabled="loading || (!isPlanFree && (!isFormValid || !fieldsMounted) && !$wire.paymentAlreadyApproved)" wire:loading.attr="disabled">
                            <span x-show="!loading" wire:loading.remove wire:target="register">
                                {{ $isPlanFree ? __('Create Organization') : ($paymentAlreadyApproved ? __('Retry Provisioning') : __('Create Organization & Pay')) }}
                            </span>
                            <span x-show="loading || $wire.loading" class="flex items-center gap-2">
                                <div class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                                {{ __('Processing...') }}
                            </span>
                        </flux:button>
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
                                
                                this.$nextTick(() => {
                                    this.initDlocal();
                                });
                            },

                            initDlocal() {
                                if (typeof dlocal === 'undefined') {
                                    if (!document.querySelector('script[src*="js.dlocal.com"]')) {
                                        const script = document.createElement('script');
                                        script.src = 'https://js.dlocal.com/';
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

                                    this.cardFieldInstance.mount('#reg-card-field').then(() => {
                                        this.fieldsMounted = true;
                                    }).catch(err => {
                                        this.error = 'Failed to mount secure field.';
                                    });

                                    const validate = () => {
                                        this.isFormValid = this.cardholderName.length > 2 && !this.fieldError;
                                    };

                                    this.cardFieldInstance.on('change', (e) => {
                                        this.fieldError = e.error ? e.error.message : '';
                                        validate();
                                    });
                                } catch (err) {
                                    this.error = 'Initialization error.';
                                }
                            },

                            async handleSubmit() {
                                if (this.isPlanFree || this.$wire.paymentAlreadyApproved) {
                                    this.$wire.register();
                                    return;
                                }

                                this.loading = true;
                                this.error = null;

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
                                    this.$wire.register();
                                } catch (e) {
                                    this.error = 'An unexpected error occurred';
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
