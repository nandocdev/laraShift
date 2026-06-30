<?php

declare(strict_types=1);

namespace App\Modules\Central\Monitoring\Livewire;

use App\Modules\Shared\Models\Activity;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.central')]
class LogViewer extends Component
{
    use WithPagination;

    public string $filterLog = '';

    public string $search = '';

    public function render(): View
    {
        $query = Activity::with('causer')->latest();

        if ($this->filterLog) {
            $query->where('log_name', $this->filterLog);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('description', 'like', "%{$this->search}%")
                    ->orWhere('log_name', 'like', "%{$this->search}%");
            });
        }

        $logNames = Activity::select('log_name')->distinct()->pluck('log_name');

        return view('monitoring::pages.log-viewer', [
            'logs' => $query->paginate(50),
            'logNames' => $logNames,
        ]);
    }
}
