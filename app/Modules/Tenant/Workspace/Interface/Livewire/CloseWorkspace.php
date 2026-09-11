<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Workspace\Interface\Livewire;

use App\Modules\Tenant\Workspace\Application\Actions\RequestWorkspaceClosure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class CloseWorkspace extends Component
{
    public string $confirmSlug = '';

    public string $password = '';

    public string $code = '';

    public bool $closed = false;

    public function close(RequestWorkspaceClosure $action): void
    {
        $this->validate([
            'confirmSlug' => 'required|string',
            'password' => 'required|string',
            'code' => 'nullable|string|max:10',
        ]);

        if ($this->confirmSlug !== tenant()->slug) {
            $this->addError('confirmSlug', __('Type the workspace slug to confirm.'));

            return;
        }

        try {
            $action->execute(auth()->user(), tenant(), $this->password, $this->code ?: null);
        } catch (AuthorizationException) {
            abort(403);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ((array) $messages as $message) {
                    $this->addError($field, $message);
                }
            }

            return;
        } finally {
            $this->reset('password', 'code');
        }

        $this->closed = true;
    }

    public function render(): View
    {
        return view('workspace::livewire.close-workspace', [
            'mfaEnabled' => (bool) auth()->user()?->mfa_enabled,
            'centralUrl' => config('app.url'),
        ]);
    }
}
