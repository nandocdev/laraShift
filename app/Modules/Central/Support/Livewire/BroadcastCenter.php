<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Livewire;

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Support\Actions\SendBroadcastAction;
use App\Modules\Central\Support\Models\Broadcast;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.central')]
class BroadcastCenter extends Component
{
    use WithPagination;

    // Form state
    public string $title = '';
    public string $body = '';
    public string $filterType = 'all';
    public string $filterValue = '';
    public array $channels = ['email'];

    public function send(SendBroadcastAction $action): void
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'filterType' => 'required|in:all,plan,status',
            'channels' => 'required|array|min:1',
        ]);

        try {
            $action->execute(new \App\Modules\Central\Support\DTOs\BroadcastData(
                title: $this->title,
                body: $this->body,
                filterType: $this->filterType,
                filterValue: $this->filterValue ?: null,
                channels: $this->channels
            ));

            $this->reset(['title', 'body', 'filterType', 'filterValue', 'channels']);
            session()->flash('status', __('Broadcast sent successfully.'));
        } catch (\Exception $e) {
            $this->addError('title', $e->getMessage());
        }
    }

    public function render(): View
    {
        return view('support::pages.broadcast-center', [
            'broadcasts' => Broadcast::with('creator')->latest()->paginate(10),
            'plans' => Plan::where('is_active', true)->get(),
        ]);
    }
}
