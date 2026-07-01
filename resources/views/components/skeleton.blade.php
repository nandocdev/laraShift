@props([
    'type' => 'table',
    'rows' => 5,
    'count' => 3,
    'fields' => 3,
])

@php
    $pulse = 'animate-pulse rounded-lg bg-zinc-200 dark:bg-zinc-700';
@endphp

@if($type === 'table')
    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900" aria-busy="true" aria-label="{{ __('Loading table...') }}">
        <div class="flex items-center gap-4 border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
            <div class="{{ $pulse }} h-8 w-48"></div>
            <div class="{{ $pulse }} ml-auto h-8 w-24"></div>
        </div>
        <div class="flex items-center gap-4 border-b border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800/50">
            @for($i = 0; $i < 4; $i++)
                <div class="{{ $pulse }} h-3 {{ match($i) { 0 => 'w-32', 1 => 'w-24', 2 => 'w-20', default => 'w-16 ml-auto' } }}"></div>
            @endfor
        </div>
        @for($r = 0; $r < $rows; $r++)
            <div class="flex items-center gap-4 border-b border-zinc-100 px-4 py-3.5 last:border-0 dark:border-zinc-800">
                <div class="{{ $pulse }} size-7 rounded-full shrink-0"></div>
                <div class="{{ $pulse }} h-3.5 w-36"></div>
                <div class="{{ $pulse }} h-3.5 w-24"></div>
                <div class="{{ $pulse }} h-5 w-16 rounded-md"></div>
                <div class="{{ $pulse }} ml-auto h-3.5 w-20"></div>
            </div>
        @endfor
    </div>

@elseif($type === 'card')
    <div class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900" aria-busy="true" aria-label="{{ __('Loading...') }}">
        <div class="{{ $pulse }} mb-4 h-4 w-1/3"></div>
        <div class="space-y-2">
            <div class="{{ $pulse }} h-3 w-full"></div>
            <div class="{{ $pulse }} h-3 w-5/6"></div>
            <div class="{{ $pulse }} h-3 w-4/6"></div>
        </div>
        <div class="{{ $pulse }} mt-4 h-8 w-24"></div>
    </div>

@elseif($type === 'stat')
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-{{ min($count, 6) }}" aria-busy="true" aria-label="{{ __('Loading stats...') }}">
        @for($i = 0; $i < $count; $i++)
            <div class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="{{ $pulse }} mb-3 h-3 w-24"></div>
                <div class="{{ $pulse }} mb-1 h-7 w-32"></div>
                <div class="{{ $pulse }} h-3 w-20"></div>
            </div>
        @endfor
    </div>

@elseif($type === 'form')
    <div class="space-y-5" aria-busy="true" aria-label="{{ __('Loading form...') }}">
        @for($i = 0; $i < $fields; $i++)
            <div>
                <div class="{{ $pulse }} mb-1.5 h-3 w-24"></div>
                <div class="{{ $pulse }} h-9 w-full rounded-lg"></div>
            </div>
        @endfor
        <div class="{{ $pulse }} ml-auto h-9 w-28 rounded-lg"></div>
    </div>

@elseif($type === 'text')
    <div class="space-y-2" aria-busy="true" aria-label="{{ __('Loading content...') }}">
        <div class="{{ $pulse }} h-4 w-3/4"></div>
        <div class="{{ $pulse }} h-3 w-full"></div>
        <div class="{{ $pulse }} h-3 w-5/6"></div>
        <div class="{{ $pulse }} h-3 w-2/3"></div>
    </div>
@endif
