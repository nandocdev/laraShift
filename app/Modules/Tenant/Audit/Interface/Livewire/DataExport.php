<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Audit\Interface\Livewire;

use App\Modules\Tenant\Audit\Application\Actions\ExportTenantData;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class DataExport extends Component
{
    public bool $exporting = false;

    public function export(ExportTenantData $action): void
    {
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
        return view('audit::livewire.data-export');
    }
}
