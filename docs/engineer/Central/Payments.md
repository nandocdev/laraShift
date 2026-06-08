# Especificación del Módulo: Payments (Central)

**Metainformación**

| Campo | Valor |
| --- | --- |
| **Responsable** | [@nandocdev / Equipo Core] |
| **Contexto (Bounded Context)** | CENTRAL |
| **Estado** | Implementado |
| **Fecha de Creación** | 2026-06-08 |

---

## 1. Visión General y Objetivo de Negocio
Este módulo actúa como la capa de integración con pasarelas de pago de terceros (PagueLo Fácil, dLocal, etc.). Proporciona una interfaz unificada para iniciar sesiones de pago y manejar webhooks.

* **Propósito:** Ofrecer un motor de pagos abstracto que facilite la integración de diferentes gateways para B2C/B2B.
* **Lo que este módulo NO hace (Non-goals):** No gestiona la lógica de suscripciones de alto nivel (eso reside en `Central/Billing`).

## 2. Restricciones Arquitectónicas y Aislamiento
* **Aislamiento de Datos:** Todos los modelos (`Payment`, `PaymentAttempt`, `PaymentWebhook`) utilizan `BelongsToTenant` para asegurar que el aislamiento de datos se mantenga, incluso si los datos se almacenan en la base de datos central.
* **Webhooks:** Los endpoints de webhook están desprotegidos de middleware de sesión, pero requieren validación estricta de firma (HMAC).

## 3. Casos de Uso (Use Cases) y Criterios de Aceptación

| ID | Persona | Historia | Criterios de Aceptación (Acceptance Criteria) |
| --- | --- | --- | --- |
| `UC-01` | Sistema | Como sistema, quiero iniciar un checkout seguro. | - Generar URL firmada para iframe<br>- Persistir intento de pago. |
| `UC-02` | Sistema | Como sistema, quiero procesar webhooks de pagos. | - Verificar firma<br>- Idempotencia<br>- Reconciliar estado con `Payment`. |

## 4. Modelo de Datos (Persistencia)

| Tabla | Campos Principales | Índices Necesarios | Reglas RLS |
| --- | --- | --- | --- |
| `payments` | `id`, `tenant_id`, `amount`, `status`, `gateway` | `tenant_id`, `display_id` | Acceso restringido al tenant activo |
| `payment_attempts`| `id`, `tenant_id`, `payment_id`, `payload` | `payment_id` | Acceso restringido al tenant activo |
| `payment_webhooks`| `id`, `tenant_id`, `gateway_reference` | `tenant_id`, `gateway_reference` | Acceso restringido al tenant activo |

## 5. Contratos de Acción (Actions & DTOs)

| Acción (Clase PHP) | DTO de Entrada (Input) | Retorno (Output) | Descripción |
| --- | --- | --- | --- |
| `HandleWebhookAction` | `rawPayload`, `sig`, `secret` | `void` | Valida y procesa webhooks. |
| `InitiateCheckoutAction`| `PaymentData` | `CheckoutSession` | Inicia sesión de checkout. |
| `LoadMerchantAction` | `apiKey` | `MerchantData` | Carga configuración del comerciante. |

## 6. Eventos y Notificaciones (Events)
* `CheckoutSessionCreated`: Disparado al iniciar un pago.
* `PaymentApproved`: Disparado al recibir confirmación de éxito.
* `PaymentDeclined`: Disparado al recibir confirmación de rechazo.

## 7. Casos Extremos y Riesgos (Edge Cases)

| Escenario de Falla | Mitigación / Respuesta del Sistema |
| --- | --- |
| Webhook no firmado | `WebhookVerificationException` (abortar procesado). |
| Ejecución duplicada | Idempotencia mediante `gateway_reference` único. |
| **Spoofing de Tenant** | Validación prioritaria de `tenantId` desde el payload firmado, no del query string. |
| **Exposición PII/PCI** | Enmascaramiento de campos sensibles (`cardNumber`, `cvv`) antes de persistir webhooks. |
| **Ataque DoS vía Webhooks** | Rate Limiting implementado en rutas de webhooks (`throttle:webhooks`). |
| **Gestión de Secretos** | Se recomienda el uso de Vaults (AWS Secrets Manager) para `webhook_secret` y `api_key`. |

## 8. Estrategia de Pruebas
* [ ] **Validación de Webhooks:** Verificar validación de firmas HMAC.
* [ ] **Reconciliación:** Probar que estados de pago no regresan a `pending` si ya están en terminal.
* [ ] **Enmascaramiento:** Verificar que los campos sensibles aparecen como `****` en la base de datos.
* [ ] **Rate Limiting:** Validar que tras 60 peticiones/minuto se retorna HTTP 429.
