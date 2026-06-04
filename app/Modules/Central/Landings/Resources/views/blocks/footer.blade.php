@props([
    'config' => [],
    'styles' => [],
    'variant' => 'simple',
])

<footer class="bg-gray-900 text-white py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if($variant === 'simple')
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-4 md:mb-0">
                    @if($config['logo_url'] ?? null)
                        <img src="{{ $config['logo_url'] }}" alt="{{ $config['logo_alt'] ?? '' }}" class="h-8 w-auto">
                    @else
                        <span class="text-xl font-bold">LaraShift</span>
                    @endif
                </div>
                
                <div class="flex space-x-6">
                    @foreach($config['legal_links'] ?? [] as $link)
                        <a href="{{ $link['url'] }}" class="text-gray-400 hover:text-white text-sm">
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </div>
                
                <div class="mt-4 md:mt-0 text-gray-400 text-sm">
                    {{ $config['copyright_text'] ?? '© ' . date('Y') . ' All rights reserved.' }}
                </div>
            </div>
        @elseif($variant === 'multi-column')
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-8">
                <div class="col-span-2">
                    @if($config['logo_url'] ?? null)
                        <img src="{{ $config['logo_url'] }}" alt="{{ $config['logo_alt'] ?? '' }}" class="h-8 w-auto mb-4">
                    @else
                        <span class="text-xl font-bold mb-4 block">LaraShift</span>
                    @endif
                    <p class="text-gray-400 text-sm max-w-xs">
                        Making business automation accessible for everyone.
                    </p>
                </div>
                
                @foreach($config['columns'] ?? [] as $column)
                    <div class="col-span-1">
                        <h3 class="text-sm font-semibold uppercase tracking-wider mb-4">{{ $column['title'] }}</h3>
                        <ul class="space-y-2">
                            @foreach($column['links'] ?? [] as $link)
                                <li>
                                    <a href="{{ $link['url'] }}" class="text-gray-400 hover:text-white text-sm transition">
                                        {{ $link['label'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
            
            <div class="border-t border-gray-800 mt-12 pt-8 flex flex-col md:flex-row justify-between items-center text-gray-400 text-sm">
                <div>{{ $config['copyright_text'] ?? '© ' . date('Y') . ' All rights reserved.' }}</div>
                <div class="flex space-x-6 mt-4 md:mt-0">
                    @foreach($config['legal_links'] ?? [] as $link)
                        <a href="{{ $link['url'] }}" class="hover:text-white">{{ $link['label'] }}</a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</footer>
