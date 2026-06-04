<?php

declare(strict_types=1);

namespace App\Modules\Central\Landings\Livewire;

use App\Modules\Central\Landings\Models\Landing;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class LandingBuilder extends Component
{
    public Landing $landing;

    /**
     * Component State
     */
    public array $blocks = [];
    public array $theme = [];
    public string $title = '';

    public function mount(Landing $landing): void
    {
        $this->landing = $landing;
        $this->blocks = $landing->blocks ?? [];
        $this->theme = $landing->theme ?? [];
        $this->title = $landing->title ?? '';
    }

    public function save(array $blocks, array $theme): void
    {
        // Checksum validation would go here in production
        
        $this->landing->update([
            'blocks' => $blocks,
            'theme' => $theme,
        ]);

        $this->blocks = $blocks;
        $this->theme = $theme;

        $this->dispatch('landing-saved');
    }

    public function publish(): void
    {
        // Ensure we save current state before publishing
        $this->landing->update([
            'blocks' => $this->blocks,
            'theme' => $this->theme,
        ]);

        // Only track publisher if they are a central user (platform admin)
        // because the DB column is constrained to 'central_users'
        $publisherId = auth('central')->check() ? auth('central')->id() : null;

        app(\App\Modules\Central\Landings\Actions\PublishLandingAction::class)->execute(
            $this->landing,
            $publisherId
        );

        $this->dispatch('landing-published');
    }

    public function render(): View
    {
        return view('landings::livewire.landing-builder');
    }
}
