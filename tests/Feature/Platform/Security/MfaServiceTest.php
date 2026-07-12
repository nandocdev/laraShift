<?php

declare(strict_types=1);

use App\Modules\Platform\Security\Mfa\MfaService;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->mfa = new MfaService(new Google2FA);
});

test('generates recovery codes', function () {
    $codes = $this->mfa->generateRecoveryCodes();

    expect($codes)->toHaveCount(8);
});
