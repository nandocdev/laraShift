<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Notifications\Livewire;

use App\Modules\Tenant\Notifications\Actions\DeleteNotificationTemplateAction;
use App\Modules\Tenant\Notifications\Actions\UpsertNotificationTemplateAction;
use App\Modules\Tenant\Notifications\DTOs\UpsertNotificationTemplateData;
use App\Modules\Tenant\Notifications\Models\NotificationTemplate;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ManageNotificationTemplates extends Component
{
    public ?NotificationTemplate $editing = null;

    public bool $isEditing = false;

    public string $key = '';

    public string $channel = 'email';

    public string $subject = '';

    public string $body = '';

    public bool $is_active = true;

    public function mount(?NotificationTemplate $template = null): void
    {
        if ($template && $template->exists) {
            $this->editing = $template;
            $this->isEditing = true;
            $this->key = $template->key;
            $this->channel = $template->channel;
            $this->subject = $template->subject ?? '';
            $this->body = $template->body ?? '';
            $this->is_active = $template->is_active;
        }
    }

    public function save(UpsertNotificationTemplateAction $action): void
    {
        $this->validate([
            'key' => 'required|string|max:100',
            'channel' => 'required|in:email,in-app',
            'subject' => 'nullable|string|max:255',
            'body' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $data = new UpsertNotificationTemplateData(
            key: $this->key,
            channel: $this->channel,
            subject: $this->subject ?: null,
            body: $this->body ?: null,
            is_active: $this->is_active,
        );

        $action->execute($data);

        $this->resetForm();
        $this->dispatch('notify', message: __('Notification template saved.'));
    }

    public function edit(NotificationTemplate $template): void
    {
        $this->mount($template);
    }

    public function delete(string $id, DeleteNotificationTemplateAction $action): void
    {
        $action->execute($id);
        $this->dispatch('notify', message: __('Notification template deleted.'));
    }

    public function resetForm(): void
    {
        $this->reset(['editing', 'isEditing', 'key', 'channel', 'subject', 'body', 'is_active']);
    }

    public function render(): View
    {
        return view('notifications::livewire.manage-templates', [
            'templates' => NotificationTemplate::where('tenant_id', tenant('id'))->latest()->get(),
        ]);
    }
}
