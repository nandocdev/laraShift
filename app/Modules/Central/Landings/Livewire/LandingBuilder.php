<?php

declare(strict_types=1);

namespace App\Modules\Central\Landings\Livewire;

use App\Modules\Central\Landings\Actions\PublishLandingAction;
use App\Modules\Central\Landings\Actions\SaveLandingAction;
use App\Modules\Central\Landings\Models\Landing;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class LandingBuilder extends Component
{
    public Landing $landing;

    public array $blocks = [];

    public array $theme = [];

    public string $title = '';

    public function mount(Landing $landing): void
    {
        if ($landing->tenant_id !== tenant('id')) {
            abort(404);
        }

        $this->landing = $landing;
        $this->blocks = $landing->blocks ?? [];
        $this->theme = $landing->theme ?? [];
        $this->title = $landing->title ?? '';
    }

    public function save(SaveLandingAction $action, array $blocks, array $theme): void
    {
        $action->execute($this->landing, $blocks, $theme);

        $this->blocks = $blocks;
        $this->theme = $theme;

        $this->dispatch('landing-saved');
    }

    public function publish(): void
    {
        $this->landing->update([
            'blocks' => $this->blocks,
            'theme' => $this->theme,
        ]);

        $publisherId = auth('central')->check() ? auth('central')->id() : null;

        app(PublishLandingAction::class)->execute(
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
