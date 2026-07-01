<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Services;

use App\Modules\Central\Payments\Enums\PaymentContext;
use App\Modules\Shared\Contracts\PaymentHandlerContract;
use Illuminate\Support\Facades\Log;

/**
 * [REALIZACIÓN DE CASO DE USO - RUP]
 * Caso de Uso: Despachar resultado de pago al handler correcto según contexto.
 *
 * Actúa como mediador entre el motor de pagos (PaymentVerifier/ProcessDirectPaymentAction)
 * y los handlers de negocio (SubscriptionPaymentHandler, ServiceOrderPaymentHandler, etc.).
 */
final class PaymentHandlerDispatcher
{
    /** @var array<string, PaymentHandlerContract> */
    private array $handlers = [];

    /**
     * @param  iterable<PaymentHandlerContract>  $handlers  Inyectados vía tagged binding
     */
    public function __construct(iterable $handlers)
    {
        foreach ($handlers as $handler) {
            $this->handlers[$handler->supports()->value] = $handler;
        }
    }

    /**
     * Despacha el resultado del pago al handler registrado para el contexto dado.
     *
     * @param  PaymentContext  $context  Contexto del pago (subscription, service_order, etc.)
     * @param  string  $tenantId  Tenant propietario del pago
     * @param  string  $displayId  Referencia interna del pago
     * @param  float  $amount  Monto procesado
     * @param  bool  $success  Si el pago fue exitoso
     * @param  array  $metadata  Datos adicionales para el handler
     */
    public function dispatch(
        PaymentContext $context,
        string $tenantId,
        string $displayId,
        float $amount,
        bool $success,
        array $metadata = [],
    ): void {
        $handler = $this->handlers[$context->value] ?? null;

        if (! $handler) {
            Log::warning('PaymentHandlerDispatcher: no handler registered for context', [
                'context' => $context->value,
                'displayId' => $displayId,
                'tenantId' => $tenantId,
            ]);

            return;
        }

        if ($success) {
            $handler->onApproved($tenantId, $displayId, $amount, $metadata);
        } else {
            $reason = $metadata['error_message'] ?? $metadata['reason'] ?? 'Unknown error';
            $handler->onFailed($tenantId, $displayId, $reason, $metadata);
        }
    }

    /**
     * Verifica si existe un handler registrado para el contexto dado.
     */
    public function hasHandler(PaymentContext $context): bool
    {
        return isset($this->handlers[$context->value]);
    }
}
