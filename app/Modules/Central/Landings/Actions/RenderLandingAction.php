<?php

declare(strict_types=1);

namespace App\Modules\Central\Landings\Actions;

use App\Modules\Central\Landings\Models\Landing;
use Illuminate\Support\Facades\Blade;

final readonly class RenderLandingAction
{
    /**
     * Renders a landing page to a complete HTML string.
     */
    public function execute(Landing $landing): string
    {
        $theme = $landing->theme;

        // Filter out blocks with invalid types to prevent LFI / View Injection
        $blocks = collect($landing->blocks)
            ->filter(fn ($block) => preg_match('/^[a-zA-Z0-9\-]+$/', $block['type'] ?? ''))
            ->sortBy('order');

        $html = Blade::render(
            '<x-landing-layout :theme="$theme" :title="$title">
                @foreach($blocks as $block)
                    @if($block[\'visible\'] ?? true)
                        @include("landings::blocks." . $block[\'type\'], [
                            \'config\' => $block[\'config\'] ?? [],
                            \'styles\' => $block[\'styles\'] ?? [],
                            \'variant\' => $block[\'variant\'] ?? \'default\'
                        ])
                    @endif
                @endforeach
            </x-landing-layout>',
            [
                'theme' => $theme,
                'title' => $landing->title,
                'blocks' => $blocks,
            ]
        );

        return $html;
    }
}
