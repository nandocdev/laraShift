<div class="flex flex-col gap-6 py-8">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Legal Documents') }}</flux:heading>
            <flux:subheading>{{ __('Manage Terms of Service, Privacy Policy, and Cookie Policy with version history.') }}</flux:subheading>
        </div>
        <flux:button wire:click="resetForm" icon="plus" @click="$dispatch('show-legal-form')">
            {{ __('New Version') }}
        </flux:button>
    </div>

    @if (session('status'))
        <flux:text color="emerald">{{ session('status') }}</flux:text>
    @endif

    <flux:modal name="legal-form" class="min-w-[40rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Create New Version') }}</flux:heading>
                <flux:subheading>{{ __('Saving creates a new version. Previous versions are preserved.') }}</flux:subheading>
            </div>

            <flux:select wire:model="type" :label="__('Document Type')">
                @foreach ($types as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </flux:select>

            <flux:input wire:model="title" :label="__('Title')" placeholder="e.g. Terms of Service v2" />

            <flux:textarea wire:model="content" rows="15" :label="__('Content (HTML)')"
                placeholder="<h1>Terms of Service</h1><p>Last updated: ...</p>" />

            <flux:checkbox wire:model="publish" :label="__('Publish immediately')" />

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost" wire:click="resetForm">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="save">{{ __('Save Version') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    @foreach ($types as $typeKey => $typeLabel)
        @php $docs = $documents->get($typeKey, collect()); @endphp
        <flux:card class="space-y-4">
            <flux:heading size="sm">{{ $typeLabel }}</flux:heading>

            @if ($docs->isEmpty())
                <flux:text class="text-zinc-400">{{ __('No versions yet.') }}</flux:text>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Version') }}</flux:table.column>
                        <flux:table.column>{{ __('Title') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Author') }}</flux:table.column>
                        <flux:table.column>{{ __('Date') }}</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($docs as $doc)
                            <flux:table.row :key="$doc->id">
                                <flux:table.cell class="font-mono text-xs">v{{ $doc->version }}</flux:table.cell>
                                <flux:table.cell>{{ $doc->title }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" :color="$doc->is_published ? 'emerald' : 'zinc'">
                                        {{ $doc->is_published ? __('Published') : __('Draft') }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell class="text-xs">{{ $doc->author?->name ?? '-' }}</flux:table.cell>
                                <flux:table.cell class="text-xs text-zinc-400">{{ $doc->created_at->format('Y-m-d') }}</flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex gap-1">
                                        <flux:button wire:click="edit({{ $doc->id }})" size="sm" variant="ghost" icon="pencil" />
                                        @if (! $doc->is_published)
                                            <flux:button wire:click="publish({{ $doc->id }})" size="sm" variant="ghost" icon="check" />
                                        @endif
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>

                @php
                    $history = $docs->first()->versions ?? collect();
                @endphp
                @if ($history->isNotEmpty())
                    <details class="text-xs text-zinc-400">
                        <summary class="cursor-pointer">{{ __('Version History') }}</summary>
                        <div class="mt-2 space-y-1">
                            @foreach ($history as $v)
                                <div class="flex gap-2">
                                    <span class="font-mono w-12">v{{ $v->version }}</span>
                                    <span>{{ $v->created_at->format('Y-m-d H:i') }}</span>
                                </div>
                            @endforeach
                        </div>
                    </details>
                @endif
            @endif
        </flux:card>
    @endforeach
</div>
