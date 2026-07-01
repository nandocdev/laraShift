<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $doc?->title ?? __('Legal') }} - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white dark:bg-zinc-900 text-zinc-800 dark:text-zinc-200 antialiased">
    <div class="max-w-3xl mx-auto py-16 px-4">
        @if ($doc)
            <h1 class="text-3xl font-bold mb-2">{{ $doc->title }}</h1>
            <p class="text-sm text-zinc-400 mb-8">{{ __('Last updated: :date', ['date' => $doc->published_at?->format('F d, Y') ?? $doc->created_at->format('F d, Y')]) }}</p>
            <div class="prose dark:prose-invert max-w-none">
                {!! $doc->content !!}
            </div>
        @else
            <h1 class="text-2xl font-bold">{{ __('Content not available') }}</h1>
            <p class="mt-4">{{ __('The requested legal document has not been published yet.') }}</p>
        @endif
    </div>
</body>
</html>
