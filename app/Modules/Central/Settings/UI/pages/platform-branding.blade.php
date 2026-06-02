<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Platform Branding') }}</flux:heading>
        <flux:subheading>{{ __('Customize the appearance of the SaaS platform and generated invoices.') }}</flux:subheading>
    </div>

    <flux:card>
        <form wire:submit="save" class="flex flex-col gap-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <flux:input 
                    wire:model="platformName" 
                    :label="__('SaaS Platform Name')" 
                    required 
                />

                <flux:input 
                    wire:model="primaryColor" 
                    :label="__('Primary Brand Color')" 
                    type="color" 
                    required 
                />

                <flux:input 
                    wire:model="logoUrl" 
                    :label="__('Logo URL')" 
                    placeholder="https://example.com/logo.png" 
                />
            </div>

            @if (session('status'))
                <flux:text color="success">{{ session('status') }}</flux:text>
            @endif

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">
                    {{ __('Save Branding Settings') }}
                </flux:button>
            </div>
        </form>
    </flux:card>

    <div class="mt-8">
        <flux:heading size="lg">{{ __('PDF Preview') }}</flux:heading>
        <flux:subheading>{{ __('This is how your pro-forma invoices will look with current settings.') }}</flux:subheading>
        
        <div class="mt-4 border border-zinc-200 dark:border-zinc-700 rounded-lg p-8 bg-white text-black shadow-inner overflow-hidden max-w-4xl mx-auto">
            <div class="flex justify-between items-start mb-8">
                <div>
                    @if($logoUrl)
                        <img src="{{ $logoUrl }}" class="h-12">
                    @else
                        <span class="text-2xl font-bold" style="color: {{ $primaryColor }}">{{ $platformName }}</span>
                    @endif
                </div>
                <div class="text-3xl font-bold uppercase" style="color: {{ $primaryColor }}">PRO-FORMA</div>
            </div>
            
            <div class="grid grid-cols-2 gap-8 mb-8">
                <div>
                    <div class="text-xs font-bold uppercase border-b-2 mb-2" style="border-color: {{ $primaryColor }}">From</div>
                    <div class="font-bold">{{ $platformName }}</div>
                    <div class="text-sm">SaaS Platform Administration</div>
                </div>
                <div class="text-right">
                    <div class="text-xs font-bold uppercase border-b-2 mb-2 inline-block" style="border-color: {{ $primaryColor }}">Bill To</div>
                    <div class="font-bold">Tenant Name Example</div>
                    <div class="text-sm">admin@tenant.com</div>
                </div>
            </div>

            <div class="w-full bg-zinc-50 border-t border-b py-2 px-4 flex justify-between font-bold text-sm mb-4" style="background-color: {{ $primaryColor }}; color: white;">
                <span>Description</span>
                <span>Amount</span>
            </div>
            <div class="w-full px-4 flex justify-between text-sm py-2 border-b">
                <span>Subscription to PRO Plan</span>
                <span>$29.00 USD</span>
            </div>
        </div>
    </div>
</div>
