# Especificación del Módulo: Billing (Central)

**Metainformación**

| Campo | Valor |
| --- | --- |
| **Responsable** | [@nandocdev / Equipo Core] |
| **Contexto (Bounded Context)** | CENTRAL |
| **Estado** | Implementado |
| **Fecha de Creación** | 2026-06-08 |

---

## 1. Visión General y Objetivo de Negocio
Este módulo gestiona la infraestructura de facturación para los inquilinos (tenants) de la plataforma. Permite la suscripción a planes, la facturación multi-gateway (Stripe, PagueLo Fácil, dLocal), la sincronización de facturas y la gestión de suscripciones.

* **Propósito:** Automatizar el ciclo de vida de cobros y suscripciones, garantizando la consistencia financiera entre gateways de pago externos y los registros internos.
* **Lo que este módulo NO hace (Non-goals):** No gestiona la lógica de facturación B2C interna de los clientes de los inquilinos, solo la facturación SaaS B2B de LaraShift hacia los inquilinos.

## 2. Restricciones Arquitectónicas y Aislamiento
* **Aislamiento de Datos:** Las suscripciones, facturas y planes se almacenan en el contexto Central (Base de datos principal) ya que el sistema operativo de facturación es global de la plataforma. Sin embargo, las consultas a estas tablas siempre están filtradas por `tenant_id` para garantizar la segregación de datos en vistas de usuario.
* **Colas (Queues):** Las operaciones pesadas como la sincronización de facturas (`SyncTenantInvoicesJob`) son asíncronas para no bloquear la UI.
* **Integración de Gateways:** Se utiliza un patrón `Manager` (`BillingManager`) para abstraer las implementaciones específicas de los proveedores de pago, cumpliendo con el principio de inversión de dependencia.

## 3. Casos de Uso (Use Cases) y Criterios de Aceptación

| ID | Persona | Historia | Criterios de Aceptación (Acceptance Criteria) |
| --- | --- | --- | --- |
| `UC-01` | Tenant | Como inquilino, quiero suscribirme a un plan para usar la plataforma. | - Selección de plan<br>- Creación de sesión de checkout<br>- Activación automática tras pago exitoso. |
| `UC-02` | Admin | Como administrador, quiero sincronizar facturas desde los gateways. | - Sincronización asíncrona<br>- Manejo de formatos de proveedores (Stripe/dLocal/Clave). |
| `UC-03` | Tenant | Como inquilino, quiero descargar una factura pro-forma. | - Generación de PDF en base a branding centralizado. |

## 4. Modelo de Datos (Persistencia)

| Tabla | Campos Principales | Índices Necesarios | Reglas RLS |
| --- | --- | --- | --- |
| `plans` | `id`, `slug`, `price_monthly`, `features` | `slug` | N/A (Global) |
| `subscriptions` | `id`, `tenant_id`, `status`, `provider_id` | `tenant_id` | N/A (Admin context) |
| `invoices` | `id`, `tenant_id`, `status`, `amount` | `tenant_id` | N/A (Admin context) |

## 5. Contratos de Acción (Actions & DTOs)

| Acción (Clase PHP) | DTO de Entrada (Input) | Retorno (Output) | Descripción |
| --- | --- | --- | --- |
| `CancelSubscriptionAction` | `Tenant`, `string`, `bool` | `void` | Cancela suscripción en el gateway y localmente. |
| `CreateCheckoutSessionAction`| `Tenant`, `string` | `string` | Inicia sesión de pago. |
| `DeletePlanAction` | `Plan` | `void` | Elimina plan si no está en uso. |
| `GenerateInvoicePdfAction` | `Invoice` | `string` (PDF) | Genera PDF de factura. |
| `RegisterPaymentMethodAction`| `Tenant`, `token`, `slug` | `Subscription` | Vincula método de pago y activa la suscripción. |
| `SetupTenantPaymentProviderAction`| `Tenant` | `TenantPaymentProvider` | Configura el gateway para tenant. |
| `SyncInvoicesAction` | `Tenant` | `int` | Sincroniza facturas desde gateway. |
| `SyncSubscriptionAction` | `Tenant` | `void` | Sincroniza estado de suscripción. |
| `UpsertPlanAction` | `PlanData` | `Plan` | Crea/actualiza planes. |

## 6. Eventos y Notificaciones (Events)
* `SubscriptionCreated`: Disparado cuando el webhook notifica una nueva suscripción.
* `PaymentSucceeded`: Disparado al confirmar un pago exitoso.
* `PaymentFailed`: Disparado al detectar falla de pago para procesos de dunning.

## 7. Casos Extremos y Riesgos (Edge Cases)

| Escenario de Falla | Mitigación / Respuesta del Sistema |
| --- | --- |
| Gateway de pago inactivo | El sistema utiliza el `BillingManager` para fallar hacia un driver de reserva. |
| Webhook duplicado | `PaymentGatewayEvent` utiliza `gateway_event_id` como clave única. |
| Desincronización de facturas | `SyncInvoicesAction` corre diariamente/bajo demanda para reconciliar el estado. |

## 8. Estrategia de Pruebas
* [ ] **Integración de Gateways:** Mockear respuestas de Stripe/Clave para validar el registro de suscripciones.
* [ ] **Atomicidad:** Verificar que `RegisterPaymentMethodAction` y `SetupTenantPaymentProviderAction` operan de forma consistente.
* [ ] **PDFs:** Verificar que la generación de facturas utiliza el branding correcto.
