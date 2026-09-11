<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Compliance\Interface\Livewire;

use App\Modules\Tenant\Compliance\Application\Actions\ExportTenantData;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class DataExport extends Component
{
    use AuthorizesRequests;

    public bool $exporting = false;

    public function export(ExportTenantData $action): void
    {
        $this->authorize('settings:manage');

        $this->exporting = true;

        try {
            $action->execute(auth()->id());
            session()->flash('status', __('Data export queued successfully. You will receive an email shortly.'));
        } catch (\Exception $e) {
            $this->addError('export', $e->getMessage());
        } finally {
            $this->exporting = false;
        }
    }

    public function render(): View
    {
        $this->authorize('settings:manage');

        return view('compliance::livewire.data-export');
    }
}
