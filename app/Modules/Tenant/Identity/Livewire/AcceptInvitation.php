<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Livewire;

use App\Modules\Tenant\Identity\Actions\AcceptInvitationAction;
use App\Modules\Tenant\Identity\Models\Invitation;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.auth')]
class AcceptInvitation extends Component
{
    public string $token = '';
    public Invitation $invitation;

    #[Validate('required|string|min:2|max:255')]
    public string $name = '';

    #[Validate('required|string|min:12')]
    public string $password = '';

    #[Validate('required|same:password')]
    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $tokenHash = hash('sha256', $token);

        $this->invitation = Invitation::where('token_hash', $tokenHash)
            ->whereNull('accepted_at')
            ->first();

        if (! $this->invitation) {
            abort(404, __('Invitation not found.'));
        }

        if ($this->invitation->expires_at->isPast()) {
            abort(410, __('This invitation has expired.'));
        }

        $this->name = $this->invitation->email; // Default to email as name
    }

    public function accept(AcceptInvitationAction $action): void
    {
        $this->validate();

        $user = $action->execute(new \App\Modules\Tenant\Identity\DTOs\UserAcceptanceData(
            token: $this->token,
            name: $this->name,
            password: $this->password
        ));

        auth()->login($user);

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render(): View
    {
        return view('identity::livewire.accept-invitation');
    }
}
