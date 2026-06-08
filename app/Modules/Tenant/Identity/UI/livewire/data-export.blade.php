<div class="space-y-6 max-w-2xl mx-auto py-12">
    <div>
        <flux:heading size="xl">{{ __('Data Export & Portability') }}</flux:heading>
        <flux:subheading>{{ __('Download a complete copy of your organization data for compliance or portability.') }}</flux:subheading>
    </div>

    @if (session('status'))
        <flux:card class="bg-emerald-50 border-emerald-200">
            <flux:text color="emerald">{{ session('status') }}</flux:text>
        </flux:card>
    @endif

    <flux:card>
        <div class="space-y-4">
            <flux:text>{{ __('By initiating an export, we will collect all available data from your organization across identity, settings, and billing modules. The process runs in the background and you will be notified via email when the JSON file is ready for download.') }}</flux:text>
            
            <div class="pt-4">
                <flux:button 
                    wire:click="export" 
                    variant="primary" 
                    icon="document-arrow-down" 
                    :loading="$exporting"
                >
                    {{ __('Request Data Export') }}
                </flux:button>
            </div>
        </div>
    </flux:card>
</div>
