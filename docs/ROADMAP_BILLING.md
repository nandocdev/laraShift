# Roadmap de implementación — Billing Core rebuild (`docs/BILLING.md`)

## TL;DR

7 fases (+ 1b de Catalog), orden Clave → dLocal → scheduler/dunning → RLS/tests → UI → Stripe diferido.
Fase 0 no es opcional: cierra las 3 ambigüedades detectadas en la revisión antes de que
alguien las resuelva mal en producción. Nada de Fase 2 en adelante empieza sin que Fase 0
y Fase 1/1b tengan la suite verde en CI (SQLite) más los tests `RLSEnforce` verdes contra
Postgres real — ver política de testing en Fase 5.

---

## Fase 0 — Cerrar ambigüedades antes de escribir el schema (bloqueante)

No se migra nada hasta resolver esto por escrito en el propio `BILLING.md`.

- [x] **Definir la columna real para `provider_customer_id`.**
      → Resuelto en `BILLING.md` §7 ("Regla de resolución exacta"): `external_reference` con `context` como discriminador, orden display_id → customer → fallback → evento crudo.
      Decidir si `payment_references.external_reference` hace doble función (order ref +
      customer ref, distinguido por `context`) o si falta una columna dedicada. Documentar
      la regla de resolución exacta: dado un payload de webhook, qué campo se lee y en qué
      orden de fallback.
- [x] **Especificar el mecanismo de renovación de Clave.**
      → Resuelto en `BILLING.md` §9 ("Renovación Clave por link"): `GenerateRenewalCheckoutAction` + `RenewalCheckoutLinkNotification`, trigger T-7 días.
      Nombrar la Action (`GenerateRenewalCheckoutAction` o equivalente) y la Notification
      que dispara el scheduler cuando una suscripción Clave se acerca a
      `current_period_end`. Sin esto, Fase 3 (scheduler) no tiene qué implementar para el
      proveedor primario.
- [x] **Separar el umbral de dunning por tipo de renovación.**
      → Resuelto en `BILLING.md` §9 ("Dunning separado"): `failed_attempts` solo MIT (3 intentos); track link con `renewal_link_*` (3+4 días).
      Definir explícitamente: intentos/plazos para MIT (silencioso, dLocal) vs. intentos/
      plazos para renovación por link (requiere acción del cliente, Clave). Un solo
      `failed_attempts int` para ambos casos va a suspender clientes de Clave que
      simplemente no revisaron el correo a tiempo.
- [x] (Opcional, no bloqueante) Renombrar `Plan.features.gateway_ids` a una columna propia
      (`provider_refs jsonb`) si el equipo decide que vale la pena antes de tener el primer
      consumidor real del campo.

**Exit criteria:** `BILLING.md` actualizado con las 3 decisiones anteriores documentadas
como texto normativo, no como nota de revisión. Sin esto, Fase 1 no arranca.

---

## Fase 1 — Schema + contratos (sin lógica de negocio todavía)

Objetivo: que el dominio compile y las tablas existan, sin ningún adapter conectado a un
gateway real todavía.

- [x] Migraciones: `subscriptions`, `payments`, `payment_attempts`, `payment_webhooks`,
      `payment_gateway_events`, `payment_references`, `invoices` (+ `plans` adelantada de 1b por el FK) — con RLS
      `ENABLE + FORCE` y políticas `tenant_id = app.current_tenant_id()` desde el primer
      commit, no como paso posterior.
- [x] Enums: `SubscriptionStatus`, `PaymentStatus`, `BillingEventType`, `BillingCapability`,
      `PaymentMethodType`.
- [x] Contratos en `Platform/Contracts/Billing/`: `BillingManager`, `CheckoutProvider`,
      `SubscriptionProvider` (con `chargeRecurring`), `PaymentProvider`, `WebhookProvider`.
      Todos firmando con `TenantContract` y `PlanRef`, nunca con modelos concretos.
- [x] DTOs `spatie/laravel-data`: `CheckoutSessionData`, `PlanRef`, `BillingEventData`,
      `DirectPaymentData`, `ProviderSubscriptionRef`, `ProviderPaymentRef`.
- [x] Excepciones de dominio: `RecurringBillingNotSupported`, `WebhookVerificationFailed`.

**Exit criteria:**

- `pint --parallel --test` limpio (es lo que `composer ci:check` enforcea hoy; si se
  instala Larastan después, se añade `phpstan analyse` aquí sin reescribir la fase).
- Factories (`PaymentFactory`, `SubscriptionFactory`) funcionando en SQLite `:memory:`.
- Test de arquitectura: `Platform` no importa nada de `Central`; ningún archivo en
  `Domain/`/`Application/` contiene `if ($gateway === '...')`.
- Cero líneas de integración real con Clave o dLocal todavía — esta fase es puro andamiaje.

---

## Fase 1b — Catalog mínimo (bloquea a Fase 2, no salteable)

`BILLING.md` §13 asume `Catalog/Plan` reconstruido (`gateway_ids`, `PlanManager::find()`),
pero ninguna fase lo construía. Esta fase cierra ese hueco.

- [x] Migración `plans`: `id uuid PK`, `slug UNIQUE`, `name`, `price_monthly/yearly int`
      (centavos), `currency char(3)`, `interval`, `features jsonb`
      (`display_features`, `gateway_ids`, `quotas`), `is_active bool`, timestamps +
      `SoftDeletes`. RLS `ENABLE + FORCE` desde el primer commit.
- [x] `PlanManager::find(slug): Plan` y `PlanManager::getProviderRef($plan, $gateway)`:
      lee `features.gateway_ids[$gateway]` con fallback a `provider_plan_id`. Sin caché
      de modelos hidratados (rompe con `cache.serializable_classes=false`).
- [x] `ResolveTenantFeatures` mínimo: `hasFeature($tenant, $key)` + caché
      `tenant:{id}:features`. Sin overrides por tenant en esta fase (YAGNI hasta el
      segundo caso real).
- [x] `tenants.billing_gateway` (`clave|dlocal|stripe`, default `clave`) y estados
      `pending_payment`/`past_due` reintroducidos en `tenants.status` (migración).
- [x] Variables `.env.example`: `BILLING_GATEWAY_DEFAULT=clave` (las credenciales
      `CLAVE_*`/`DLOCAL_*` llegan con su fase).

**Exit criteria:**

- `PlanSeeder` con 1 plan `free` + 1 plan pago en `database/seeders/`.
- Test: `getProviderRef` resuelve por gateway y cae al fallback; `hasFeature` tras
  cambio de plan invalida caché.
- `BILLING.md` §13 marcado como implementado, no como supuesto.

---

## Fase 2 — Clave (redirect), fase 1 real del negocio

Es el proveedor que ya opera en producción. Se reimplementa primero porque es el único
con tráfico real hoy; cualquier regresión acá es visible de inmediato.

> Supuesto a confirmar antes de arrancar: si no hay forma de obtener fixtures reales
> sanitizados del gateway ni tráfico verificable, reescribir esta justificación y
> arrancar con dLocal sandbox en su lugar. El orden Clave-primero solo vale si Clave
> es observable.

- [x] `ClaveGateway` implementando `CheckoutProvider` (`buildCheckoutUrl` server-side).
- [x] `ClaveGateway->supports()`: `Checkout=true`, `DirectPayment=false`,
      `Subscriptions=false` — explícito, no inferido.
- [x] Ruta de callback de retorno (`handleReturn`): **UX-only**, no muta estado. Verificar
      con test que un callback repetido o manipulado no cambia ningún registro.
- [x] `WebhookController` / `PaguelofacilCallbackController`: `verify()` síncrono (HMAC),
      401 sin tocar DB ante firma inválida.
- [x] Resolución de tenant en el webhook usando la decisión de Fase 0 (columna definida).
- [x] Idempotencia: `payment_gateway_events UNIQUE(gateway, gateway_event_id)` +
      `payment_webhooks.gateway_reference UNIQUE`.
- [x] `lockForUpdate` + recuperación de `23505` en creación de `payments` (doble submit).
- [x] `PaymentApproved` → `FulfillSubscription` Action: crea `Subscription` + activa tenant.
      **Este es el único punto donde se crea un `Subscription` para un tenant Clave** —
      nunca vía `subscribe()` (ver Fase 0, punto de `supports(Subscriptions)`).
- [x] `GenerateRenewalCheckoutAction` (definida en Fase 0): genera nuevo checkout al
      acercarse `current_period_end` para suscripciones Clave. Se implementa acá aunque se
      dispare desde el scheduler en Fase 4 — el código debe existir y tener tests propios.
- [x] Variables `.env.example`: `CLAVE_*` (merchant, secret, webhook secret, entorno).
      Sin credenciales reales en el repo ni en CI — solo fixtures sanitizados.

**Exit criteria:**

- Test de doble submit: dos requests concurrentes de checkout → un solo `payments` row.
- Test de webhook duplicado: mismo `gateway_event_id` dos veces → una sola transición de
  estado.
- Test de aislamiento: `gateway_reference`/`display_id` de un tenant no es aceptado a
  nombre de otro.
- Test de ciclo de vida: `pending_payment` expira vía `provisioning:reconcile` y
  `EnsureTenantIsActive` permite solo checkout/login en ese estado (implementación
  mínima acá, verificación completa en Fase 6).
- Flujo completo `pending_payment → active` cubierto por Pest, sin mocks de red externa
  (usar fixtures de payload real de Clave, sanitizados).

---

## Fase 3 — dLocal (Smart Fields + MIT)

- [ ] `DlocalGateway` implementando `CheckoutProvider` (redirect) y `PaymentProvider`
      (`chargeDirect` server-side con token de Smart Fields — el PAN nunca toca el
      backend).
- [ ] Credencial separada de browser (`DLOCAL_JS_API_KEY`) vs. API server-side
      (`DLOCAL_LOGIN`) — no reusar una para la otra.
- [ ] `DlocalGateway->supports()`: `Checkout=true`, `DirectPayment(Card)=true`,
      `Subscriptions(Card)=true`, `Subscriptions(Cash)=false`.
- [ ] `chargeRecurring()` real contra la API de MIT de dLocal.
- [ ] `DlocalWebhookController`: mismo pipeline de verify → resolver tenant → idempotencia
      → dispatch job que ya se construyó en Fase 2, reutilizado sin duplicar lógica.
- [ ] Test explícito: `DlocalProvider->supports(Subscriptions, forMethod: Cash)` es
      consultado por la UI antes de ofrecer auto-renovación — si no se consulta, es un bug
      de UI, no de gateway.
- [ ] Variables `.env.example`: `DLOCAL_*` (login, trans key, secret, webhook secret,
      `DLOCAL_JS_API_KEY` separada para browser). Sin credenciales reales en CI.

**Exit criteria:**

- Test de creación de suscripción MIT con token mock (nunca credencial real en CI).
- Test de `chargeRecurring` fallido → `failed_attempts++` → `PastDue` (vía evento, no
  timeout — este es el camino "Fuente 1" del dual-trigger).
- Ningún archivo de `Domain/` referencia `Dlocal` directamente (solo `Infrastructure/`).

---

## Fase 4 — BillingScheduler + dunning unificado

Ahora que existen los dos caminos de renovación (MIT silencioso para dLocal, checkout-link
para Clave), se conectan al reloj.

- [x] `billing:process-recurring` (04:00 diario): para cada `Subscription` vencida,
      rama por gateway — `chargeRecurring()` si `supports(Subscriptions, method)`,
      `GenerateRenewalCheckoutAction` si no.
- [x] `billing:reconcile` (03:00 diario): timeout-based `PastDue` — "Fuente 2" del
      dual-trigger, para suscripciones sin pago registrado en el período vigente
      independientemente de si hubo evento de fallo.
- [x] Dunning con umbrales **separados** por tipo de renovación (definidos en Fase 0):
      intentos/plazos de MIT ≠ intentos/plazos de link-based.
- [x] 3er intento fallido (o vencimiento de plazo de link) → `tenant.status = suspended` +
      `activity('billing')` — reutilizando `EnsureTenantIsActive`, sin máquina de estados
      paralela.
- [x] `TenantQueueManager`: verificar que `suspended/past_due` efectivamente degrada a cola
      `low` (test de integración, no solo revisión de código).

**Exit criteria:**

- Test de escenario completo: suscripción dLocal MIT falla 3 veces → `suspended`.
- Test de escenario completo: suscripción Clave sin click en el link de renovación tras el
  plazo definido → `past_due` → `suspended`, con umbral distinto al de dLocal (verificar
  que son literalmente números diferentes en el test, no el mismo valor reusado).
- Reactivación: pago regularizado → `active`, sin importar por qué gateway llegó.

---

## Fase 5 — RLS real + suite de aislamiento

Esta fase se puede paralelizar con 3/4, pero **no se puede dar el proyecto por cerrado sin
ella corriendo en CI, no solo escrita.**

- [ ] Confirmar (no asumir) que el tag `RLSEnforce` corre en un stage del pipeline contra
      Postgres real — mostrar el archivo de configuración de CI que lo invoca. Si no
      existe ese stage, crearlo antes de continuar.
- [ ] `CrossTenantLeakTest` para cada tabla nueva: tenant A no lee/ejecuta nada de tenant B,
      incluyendo jobs despachados y queries con `withoutGlobalScopes`.
- [ ] Verificar `SET LOCAL app.tenant_id` dentro de transacción explícita en cada punto de
      entrada (HTTP, Job, Console command) — un test que abra dos "sesiones" de tenant
      distintas en el mismo proceso (simulando reuso de worker Octane) y confirme que no
      hay fuga de contexto.
- [ ] `TenantAware` + `RehydrateTenantContext` en `ProcessPaymentWebhookJob` y
      `ChargeSubscriptionJob`: test de que un job sin contexto de tenant lanza excepción
      explícita, nunca se ejecuta en silencio.

**Exit criteria:**

- Pipeline de CI con dos stages visibles: rápido (SQLite, PRs) y `RLSEnforce` (Postgres,
  pre-merge a `main` o nightly — decisión del equipo, pero debe ejecutarse en algún punto
  automatizado, no manualmente).
- Cero tests marcados `RLSEnforce` en estado skip/pending al cierre de la fase.

---

## Fase 6 — UI Livewire + Flux

- [ ] `SelectPlan`: consulta `supports(Subscriptions, forMethod)` antes de mostrar toggle
      de auto-renovación; si es `false`, muestra flujo de checkout único + explica que la
      renovación futura llegará por link/email.
- [ ] `ManageBilling`, `HostedCheckout`, `UpdatePaymentMethod`, `SubscriptionList`,
      `TenantInvoiceList` — componentes de solo estado de UI, sin queries complejas
      (delegadas a `Application/Queries/`).
- [ ] Vistas `/billing/success` y `/billing/cancel` — estáticas, sin lógica de mutación.
- [ ] `EnsureTenantIsActive` allowlist verificada para `pending_payment` y `suspended`
      (permite login + rutas de billing en dunning, bloquea el resto).

**Exit criteria:**

- Flujo de UI probado end-to-end (Livewire test) para Clave y dLocal por separado.
- Ningún componente Livewire crea modelos directamente — todo pasa por Actions.
- `CU.md` actualizado: UCs de billing reintroducidos solo para lo implementado
  (los UCs eliminados en el strip no vuelven solos).

---

## Fase 7 — Stripe (diferido, solo si aparece demanda real)

No arranca sin una señal de negocio concreta (cliente/mercado que lo exija). Cuando
arranque:

- [ ] Cashier vive exclusivamente en `Central/Billing/Infrastructure/Gateways/Stripe/`.
      `grep -R "Cashier" Application/ Domain/` debe dar vacío — verificar en CI, no de
      memoria.
- [ ] Trait `Billable` nunca en `Provisioning\Models\Tenant`. Adapter por composición.
- [ ] `chargeRecurring` del adapter Stripe lanza `LogicException` explícita (reloj propio).
- [ ] Columnas `stripe_id`/`pm_*` en `tenants` se agregan en esta fase, no antes.

**Exit criteria:** el resto del sistema (Fases 1–6) no requiere ningún cambio para que
Stripe entre. Si requiere cambios, es una señal de que el desacoplamiento de fases
anteriores falló y hay que revisar antes de continuar con Stripe.

---

## Dependencias entre fases

```text
Fase 0 (bloqueante)
   │
   ▼
Fase 1 (schema + contratos)
   │
   ▼
Fase 1b (Catalog mínimo — bloquea a Fase 2)
   │
   ▼
Fase 2 (Clave) ──────────────┐
   │                          │
   ▼                          │
Fase 3 (dLocal)                │
   │                          │
   ▼                          │
Fase 4 (Scheduler + dunning) ◄┘
   │
   ├──► Fase 5 (RLS + tests) [paralelizable con Fase 3/4]
   │
   ▼
Fase 6 (UI)
   │
   ▼
Fase 7 (Stripe, diferido — sin fecha)
```

## Regla de cierre por fase

Ninguna fase se marca completa sin su "Exit criteria" verificado por tests que corren en
CI. Una fase "terminada" con tests pendientes o skip no está terminada — es una fase que
va a fallar en producción con otro nombre.
