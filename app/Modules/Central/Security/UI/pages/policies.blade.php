<div class="flex flex-col gap-6 py-8">
    <div>
        <flux:heading size="xl">{{ __('Security & Compliance') }}</flux:heading>
        <flux:subheading>{{ __('Manage encryption keys, data retention policies, and secret rotation by tenant tier.') }}</flux:subheading>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <flux:card class="space-y-3">
            <flux:heading size="sm">{{ __('Encryption Keys') }}</flux:heading>
            <flux:text class="text-3xl font-bold">{{ \App\Modules\Central\Security\Models\TenantEncryptionKey::where('is_active', true)->count() }}</flux:text>
            <flux:text class="text-xs text-zinc-400">{{ __('Active encryption keys across all tenants') }}</flux:text>
        </flux:card>

        <flux:card class="space-y-3">
            <flux:heading size="sm">{{ __('Key Rotation') }}</flux:heading>
            <flux:text class="text-3xl font-bold">{{ \App\Modules\Central\Security\Models\TenantEncryptionKey::where('is_active', true)->where('created_at', '<', now()->subDays(85))->count() }}</flux:text>
            <flux:text class="text-xs text-zinc-400">{{ __('Keys nearing rotation (within 5 days)') }}</flux:text>
        </flux:card>

        <flux:card class="space-y-3">
            <flux:heading size="sm">{{ __('Active Tenants') }}</flux:heading>
            <flux:text class="text-3xl font-bold">{{ \App\Modules\Central\Provisioning\Models\Tenant::whereNull('archived_at')->count() }}</flux:text>
            <flux:text class="text-xs text-zinc-400">{{ __('Tenants with encryption policies') }}</flux:text>
        </flux:card>
    </div>

    <flux:card class="space-y-4">
        <flux:heading size="sm">{{ __('Data Retention Policies (by Plan Tier)') }}</flux:heading>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Plan') }}</flux:table.column>
                <flux:table.column>{{ __('Key Rotation (days)') }}</flux:table.column>
                <flux:table.column>{{ __('Encrypt at Rest') }}</flux:table.column>
                <flux:table.column>{{ __('Audit Retention') }}</flux:table.column>
                <flux:table.column>{{ __('Active Keys') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @php
                    $plans = \App\Modules\Central\Billing\Models\Plan::where('is_active', true)->get();
                @endphp
                @forelse($plans as $plan)
                    @php
                        $enc = $plan->features['encryption'] ?? [];
                        $ret = $plan->features['retention'] ?? [];
                    @endphp
                    <flux:table.row>
                        <flux:table.cell class="font-medium">{{ $plan->name }}</flux:table.cell>
                        <flux:table.cell>{{ $enc['key_rotation_days'] ?? 90 }}</flux:table.cell>
                        <flux:table.cell>{{ ($enc['encrypt_at_rest'] ?? true) ? '✅' : '❌' }}</flux:table.cell>
                        <flux:table.cell>{{ $ret['audit_logs'] ?? 365 }}d</flux:table.cell>
                        <flux:table.cell>{{ \App\Modules\Central\Security\Models\TenantEncryptionKey::whereIn('tenant_id', \App\Modules\Central\Provisioning\Models\Tenant::where('plan_id', $plan->slug)->pluck('id'))->where('is_active', true)->count() }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center text-zinc-400">{{ __('No plans configured.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:card class="space-y-4">
        <flux:heading size="sm">{{ __('Recent Security Events') }}</flux:heading>
        <div class="max-h-64 overflow-y-auto space-y-1">
            @php
                $events = \App\Modules\Shared\Models\Activity::whereIn('log_name', ['security'])->latest()->take(50)->get();
            @endphp
            @forelse($events as $event)
                <div class="flex items-center justify-between text-xs py-1 border-b last:border-0">
                    <span class="text-zinc-400 w-24">{{ $event->created_at->format('Y-m-d H:i') }}</span>
                    <span class="font-mono flex-1">{{ $event->description }}</span>
                    <span class="text-zinc-400">{{ $event->causer?->name ?? 'System' }}</span>
                </div>
            @empty
                <flux:text class="text-zinc-400">{{ __('No security events recorded yet.') }}</flux:text>
            @endforelse
        </div>
    </flux:card>
</div>
