<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Landing Page' }}</title>
    
    <!-- Using Tailwind CDN for simplicity in published landings as per spec v1 -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <style>
        :root {
            --primary-color: {{ $theme['colors']['primary'] ?? '#2563EB' }};
            --secondary-color: {{ $theme['colors']['secondary'] ?? '#64748B' }};
            --surface-color: {{ $theme['colors']['surface'] ?? '#F8FAFC' }};
            --text-color: {{ $theme['colors']['text'] ?? '#0F172A' }};
        }
        
        body {
            color: var(--text-color);
            font-family: '{{ $theme['typography']['font_body'] ?? 'Inter' }}', sans-serif;
        }

        .bg-primary { background-color: var(--primary-color); }
        .text-primary { color: var(--primary-color); }
        .bg-secondary { background-color: var(--secondary-color); }
        .text-secondary { color: var(--secondary-color); }
        .bg-surface { background-color: var(--surface-color); }
        
        /* Custom spacing tokens */
        .section-padding {
            @php
                $padding = match($theme['spacing']['section_padding'] ?? 'comfortable') {
                    'compact' => 'py-8',
                    'comfortable' => 'py-20',
                    'spacious' => 'py-32',
                    default => 'py-20'
                };
            @endphp
        }
    </style>
    
    @if(($theme['typography']['font_heading'] ?? 'Inter') !== 'Inter')
        <link href="https://fonts.googleapis.com/css2?family={{ urlencode($theme['typography']['font_heading']) }}&display=swap" rel="stylesheet">
    @endif
</head>
<body class="antialiased">
    <main>
        {{ $slot }}
    </main>
</body>
</html>
