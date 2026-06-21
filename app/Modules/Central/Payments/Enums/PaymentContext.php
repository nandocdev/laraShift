<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Enums;

/**
 * Discriminador de contexto de pago.
 * Determina qué handler post-pago procesa el resultado.
 */
enum PaymentContext: string {
    /** Central: cobro de membresías/suscripciones recurrentes */
    case Subscription = 'subscription';

    /** Tenant: cobro por servicio único del cliente del tenant */
    case ServiceOrder = 'service_order';

    /** Pago directo de factura sin suscripción asociada */
    case Invoice = 'invoice';
}
