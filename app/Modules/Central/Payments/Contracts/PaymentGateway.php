<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Contracts;

use App\Modules\Shared\Contracts\PaymentGatewayContract;

/**
 * @deprecated Utilizar App\Modules\Shared\Contracts\PaymentGatewayContract.
 * Este archivo se mantiene temporalmente como alias para evitar roturas en imports existentes.
 * Será eliminado en la siguiente iteración de limpieza.
 */
interface PaymentGateway extends PaymentGatewayContract {}
