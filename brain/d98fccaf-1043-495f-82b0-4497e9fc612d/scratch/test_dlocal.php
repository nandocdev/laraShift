<?php

require '/home/nandocdev/Projects/SaaS/laraShift/vendor/autoload.php';
$app = require_once '/home/nandocdev/Projects/SaaS/laraShift/bootstrap/app.php';

use Illuminate\Contracts\Console\Kernel;

$app->make(Kernel::class)->bootstrap();

use App\Modules\Central\Payments\DTOs\PaymentData;
use App\Modules\Central\Payments\Enums\PaymentContext;
use App\Modules\Central\Payments\Services\Gateways\DlocalGateway;

$gateway = app(DlocalGateway::class);

$paymentData = new PaymentData(
    context: PaymentContext::Subscription,
    amount: 10.00,
    description: 'Test Subscription',
    displayId: 'SUB-TEST12',
    email: 'test@example.com',
    tenantId: 'test-tenant-id',
    slug: 'checkout_test_slug',
    customFieldValues: [
        'document' => '12345678', // Uruguayan CI dummy
        'name' => 'René Castillo',
        'country' => 'UY',
    ]
);

echo "Sending direct payment request to dLocal...\n";
try {
    $token = 'CV-e6d0bed2-30aa-47d6-93d5-1759f27d1108'; // Using the token provided by the user
    $apiKey = config('payments.dlocal.login');
    echo 'API Key / Login: '.$apiKey."\n";
    $result = $gateway->processDirectPayment($paymentData, $apiKey, $token);
    echo 'Result status: '.var_export($result, true)."\n";
} catch (Exception $e) {
    echo 'Error: '.$e->getMessage()."\n";
}
