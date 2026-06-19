<?php

declare(strict_types=1);

namespace App\Modules\Shared\Contracts;

use App\Modules\Central\Payments\Enums\PaymentContext;

/**
 * Contrato para handlers post-pago.
 * Cada implementación maneja un contexto específico (subscription, service_order, etc.).
 *
 * @see PaymentContext
 */
interface PaymentHandlerContract
{
    /**
     * Qué contexto de pago maneja este handler.
     */
    public function supports(): PaymentContext;

    /**
     * Ejecutado cuando el pago es aprobado exitosamente.
     *
     * @param string $tenantId    Tenant propietario del pago
     * @param string $displayId   Referencia interna (invoice_id, order_id, etc.)
     * @param float  $amount      Monto aprobado por la pasarela
     * @param array  $metadata    Datos adicionales del contexto original
     */
    public function onApproved(string $tenantId, string $displayId, float $amount, array $metadata): void;

    /**
     * Ejecutado cuando el pago falla o es rechazado.
     *
     * @param string $tenantId    Tenant propietario del pago
     * @param string $displayId   Referencia interna
     * @param string $reason      Motivo del fallo
     * @param array  $metadata    Datos adicionales del contexto original
     */
    public function onFailed(string $tenantId, string $displayId, string $reason, array $metadata): void;
}
