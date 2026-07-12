**Antes de listar casos de uso, hay una decisión arquitectónica que determina cómo se particionan** — te la planteo primero porque si no la resolvemos ahora vas a terminar duplicando lógica de resolución de tenant en cada Job.

## El problema de fondo: un solo webhook global, multi-contexto

dLocal te da **una única `notification_url`** por cuenta/entorno. No hay forma de que dLocal sepa si el pago pertenece a:

- **Central** → cobro de suscripción de un tenant hacia la plataforma
- **Tenant** → cobro de un cliente final dentro de un módulo de negocio del tenant (Orders, Invoicing, etc.)

El webhook llega a una ruta pública sin subdominio, sin sesión, sin tenant resuelto. Tu `TenantContext` (scoped) todavía no existe en ese punto del ciclo de vida. Esto significa que **no puedes confiar en RLS + tenant_id de la tabla de negocio para encontrar el pago** — necesitas una tabla de resolución que viva fuera del alcance tenant, consultable sin contexto.

Propuesta: una tabla central (sin RLS, o con rol bypass), algo como:

```
payment_references
- id
- external_reference   (dLocal payment_id)
- order_id              (tu order_id enviado a dLocal)
- context               enum: central | tenant
- tenant_id             nullable (null si context=central)
- owner_type / owner_id (polimórfico: Subscription, Invoice, Order...)
```

Esta tabla la escribes **en el momento en que creas el pago** (antes de llamar a dLocal), no cuando llega el webhook. Así el flujo del webhook es determinístico: firma → busca en `payment_references` → sabe si debe hidratar `TenantContext` o quedarse en Central → despacha al Job correcto.

Esto no viola "Shared nunca contiene reglas de negocio" porque `payment_references` es infraestructura de enrutamiento del gateway, no una entidad de dominio. Vive en `Shared/Integrations/Dlocal`, no en Billing ni en Tenant.

---

## Casos de uso — Central (Billing: plataforma cobra al tenant)

| #   | Caso de uso                                                    | Notas                                                                        |
| --- | -------------------------------------------------------------- | ---------------------------------------------------------------------------- |
| C1  | Iniciar cobro de suscripción (alta o renovación)               | Genera `payment_references` con `context=central`, `tenant_id=null`          |
| C2  | Reintentar cobro fallido (dunning)                             | Reutiliza Action de C1, no duplica lógica de creación de pago                |
| C3  | Cobro por upgrade/downgrade con prorrateo                      | Monto calculado antes de llegar al gateway — el gateway no sabe de prorrateo |
| C4  | Reembolsar un cobro a nivel plataforma                         | Requiere Policy de admin (soporte/billing), no cualquier rol                 |
| C5  | Procesar webhook → actualizar `Subscription`/`Invoice` central | Vive en `Central\Billing\Jobs`, no en Shared                                 |
| C6  | Consultar estado de un pago on-demand                          | Para soporte: "¿por qué no se activó la suscripción?"                        |
| C7  | Cancelar autorización no capturada                             | Solo si usas flujo `AUTHORIZE` + `CAPTURE` para trials con hold              |

## Casos de uso — Tenant (el tenant cobra a SUS clientes finales)

Asumo que esto aplica a módulos de negocio futuros (Orders, Invoicing, CRM con cobros) que corren **dentro** de un tenant y necesitan procesar pagos de sus propios clientes — no del tenant hacia la plataforma. Si esto no es lo que tenías en mente, dime y ajustamos.

| #   | Caso de uso                                                                                                 | Notas                                                                                    |
| --- | ----------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------- |
| T1  | Crear cobro para un pedido/factura del cliente final                                                        | Debe correr con `TenantContext` ya hidratado (viene de un request normal, no de webhook) |
| T2  | Reembolsar un cobro a un cliente final                                                                      | Policy: solo dentro del tenant dueño del pago                                            |
| T3  | Procesar webhook → resolver tenant, re-hidratar `TenantContext`, actualizar `Order`/`Invoice` tenant-scoped | El caso crítico — ver flujo abajo                                                        |
| T4  | Consultar estado de pago desde el panel Tenant                                                              | Solo lectura, scoped por RLS normal                                                      |
| T5  | Capturar autorización previamente creada                                                                    | Ej. reservas con hold de tarjeta                                                         |

## Flujo del webhook único (resuelve C5 y T3 sin duplicar código)

```
POST /webhooks/dlocal  (ruta global, sin middleware de tenant)
  → verificar firma HMAC (falla = 401, sin tocar nada más)
  → persistir payload crudo (idempotencia por external_reference)
  → responder 200 inmediatamente
  → despachar Job: ResolveDlocalWebhookJob (dispatch síncrono ligero, no hace el trabajo pesado)
       → busca en payment_references por external_reference
       → si context=central → despacha Central\Billing\Jobs\ProcessBillingPaymentJob
       → si context=tenant  → hidrata TenantContext(tenant_id) vía binding scoped()
                             → despacha Tenant\Invoicing\Jobs\ProcessCustomerPaymentJob
                               (implementa TenantAware, como ya definimos)
```

`ResolveDlocalWebhookJob` es el **único** punto en `Shared/Integrations/Dlocal` que sabe que existe "central" y "tenant" — y solo para enrutar, no para procesar. El procesamiento real (actualizar estado, disparar `SubscriptionActivated` o `InvoicePaid`) vive en Actions de cada módulo consumidor, cada uno con su propia Policy de aislamiento.

---

**Qué necesito confirmar antes de seguir:**
