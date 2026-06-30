<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Livewire;

use App\Modules\Central\Support\Models\SupportTicket;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.central')]
class TicketList extends Component
{
    use WithPagination;

    public string $filterStatus = '';

    public string $filterPriority = '';

    public string $search = '';

    public function render(): View
    {
        $query = SupportTicket::with(['tenant', 'assignedTo', 'creator'])
            ->latest();

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterPriority) {
            $query->where('priority', $this->filterPriority);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('subject', 'like', "%{$this->search}%")
                    ->orWhere('description', 'like', "%{$this->search}%");
            });
        }

        return view('support::pages.ticket-list', [
            'tickets' => $query->paginate(20),
        ]);
    }
}
