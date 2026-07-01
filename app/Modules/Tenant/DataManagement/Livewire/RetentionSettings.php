<?php

declare(strict_types=1);

namespace App\Modules\Tenant\DataManagement\Livewire;

use App\Modules\Tenant\DataManagement\Actions\GetRetentionPolicyAction;
use App\Modules\Tenant\DataManagement\Actions\UpdateRetentionPolicyAction;
use App\Modules\Tenant\DataManagement\DTOs\RetentionPolicyData;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class RetentionSettings extends Component
{
    public int $audit_logs = 365;

    public int $notifications = 180;

    public int $activity_log = 365;

    public int $exports = 30;

    public int $backups = 7;

    public function mount(GetRetentionPolicyAction $action): void
    {
        $policy = $action->execute();

        $this->audit_logs = $policy->audit_logs;
        $this->notifications = $policy->notifications;
        $this->activity_log = $policy->activity_log;
        $this->exports = $policy->exports;
        $this->backups = $policy->backups;
    }

    public function save(UpdateRetentionPolicyAction $action): void
    {
        $this->validate([
            'audit_logs' => 'required|integer|min:30|max:3650',
            'notifications' => 'required|integer|min:30|max:3650',
            'activity_log' => 'required|integer|min:30|max:3650',
            'exports' => 'required|integer|min:1|max:365',
            'backups' => 'required|integer|min:1|max:90',
        ]);

        $data = new RetentionPolicyData(
            audit_logs: $this->audit_logs,
            notifications: $this->notifications,
            activity_log: $this->activity_log,
            exports: $this->exports,
            backups: $this->backups,
        );

        $action->execute($data);

        $this->dispatch('notify', message: __('Retention policies updated.'));
    }

    public function render(): View
    {
        return view('data-management::livewire.retention-settings');
    }
}
