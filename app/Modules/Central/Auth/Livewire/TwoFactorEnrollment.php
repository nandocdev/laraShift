<?php

declare(strict_types=1);

namespace App\Modules\Central\Auth\Livewire;

use App\Modules\Central\Auth\Actions\EnrollCentral2FAAction;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.central')]
class TwoFactorEnrollment extends Component
{
    public bool $showingQrCode = false;
    public string $secret = '';
    public string $qrCodeUrl = '';
    public string $code = '';
    public array $recoveryCodes = [];

    public function initiate(EnrollCentral2FAAction $action): void
    {
        $data = $action->initiate(auth('central')->user());
        
        $this->secret = $data['secret'];
        
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);
        $this->qrCodeUrl = $writer->writeString($data['qr_code_url']);
        
        $this->showingQrCode = true;
    }

    public function confirm(EnrollCentral2FAAction $action): void
    {
        $this->validate([
            'code' => 'required|string|size:6',
        ]);

        $success = $action->confirm(
            auth('central')->user(),
            $this->secret,
            $this->code
        );

        if ($success) {
            $this->recoveryCodes = auth('central')->user()->twoFactorAuth->recovery_codes;
            $this->showingQrCode = false;
            session()->flash('status', __('2FA enabled successfully. Please save your recovery codes.'));
        } else {
            $this->addError('code', __('Invalid verification code.'));
        }
    }

    public function render(): View
    {
        return view('auth::livewire.two-factor-enrollment', [
            'enabled' => auth('central')->user()->hasTwoFactorEnabled(),
        ]);
    }
}
