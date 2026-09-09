# Documento de Requerimientos y Especificaciones de Software (SRS)
**Proyecto:** SaaSiFy
**Tipo de Sistema:** Plataforma SaaS Multi-inquilino (Multitenancy)
**Patrón de Arquitectura:** Monolito Modular Estricto (Domain-Driven Design / Clean Architecture)

---

## 1. Stack Tecnológico Base y Dependencias Verificadas

| Categoría | Tecnología / Librería | Versión / Restricción | Propósito / Responsabilidad |
| :--- | :--- | :--- | :--- |
| **Core** | PHP | `^8.3` (CI pinea 8.5) | Lenguaje base del backend |
| **Framework** | Laravel | `13.x` (`^13.30`) | Framework de la aplicación |
| **UI Stack** | Livewire + Flux | Livewire 4.4 / Flux 2.18 | Interfaz reactiva en servidor y suite de componentes |
| **Auth Base** | Laravel Fortify | `1.37` | Autenticación headless (login, 2FA, reseteo) |
| **Frontend/CSS**| Tailwind CSS + Vite Plus | Tailwind 4 / Vite Plus 0.3 | Estilos y compilación de assets |
| **Multitenancy**| `stancl/tenancy` | Compatible L13 | Aislamiento de inquilinos, ruteo por dominio/subdominio |
| **Data Contracts**| `spatie/laravel-data` | `^4.23` | DTOs tipados, validación y mutación de datos de entrada |
| **Feature Flags**| `laravel/pennant` | Paquete oficial | Gating de funcionalidades por plan o por Tenant |
| **RBAC** | `spatie/laravel-permission` | Compatible L13 | Roles y permisos tanto a nivel Central como de Workspace |
| **Bases de Datos**| SQLite / PostgreSQL | Local/Test: SQLite (`:memory:`)<br>Prod: `pgsql` | Motores de base de datos aislados o particionados |
| **Calidad y QA** | Pest + Larastan + Pint | Pest 5 / Larastan Nivel 7 / Pint | Suite de testing, análisis estático y formateo PSR-12 |

---

## 2. Directrices Arquitectónicas y Estructura de Módulos

El sistema se organiza bajo un esquema de **Monolito Modular Estricto**:

```text
app/Modules/
├── Platform/   → Componentes transversales: Contracts, Events globales, UI core, Tenancy runtime
├── Central/    → Administración del Producto: Auth administrativa, Billing, Provisioning, Catálogo
└── Tenant/     → Operación del Workspace: Access (RBAC), Workspace branding, Compliance, Features
```

### Estructura Interna Obligatoria por Módulo
```text
app/Modules/{Module}/
├── Domain/           → Models (Eloquent), ValueObjects, Enums, Exceptions (Reglas puras)
├── Application/      → Actions (Casos de uso), DTOs (spatie/laravel-data), Contracts internos
├── Infrastructure/   → Persistence (Repositorios), ExternalServices (Stripe, APIs), Adapters
├── Interface/        → Http (Controllers, Requests, Resources), Livewire Components, Console
├── Database/         → Migrations, Seeders, Factories (Aisladas al módulo)
├── Resources/        → Views (Blade/Flux), Lang
├── Routes/           → web.php, api.php
├── Events/           → Eventos propios del módulo
└── Listeners/        → Listeners que reaccionan a eventos propios o de Platform
```

### Reglas Críticas de Comunicación
1. **Eventos y DTOs:** La comunicación cruzada se realiza preferentemente emitiendo eventos (`Platform/Events/`) con payloads de `spatie/laravel-data`.
2. **Sin fuga de modelos Eloquent:** Está terminantemente prohibido importar `Domain/Models/` de un módulo en otro. Si el módulo `Tenant` requiere validar un plan de `Central`, debe consultar una interfaz en `Platform/Contracts/` o consumir un DTO expuesto por una `Action`.
3. **Migraciones descentralizadas:** Cada módulo ejecuta sus migraciones de forma independiente (`$this->loadMigrationsFrom(...)`).

---

## 3. Matriz de Casos de Uso
**TL;DR:** Matriz exhaustiva de casos de uso funcionales, técnicos y de gobernanza para arquitecturas SaaS multi-tenant desacopladas, categorizados bajo estándar de ingeniería de sistemas por actor, precondiciones, flujo crítico y postcondición de aislamiento.

---

### Casos de Uso: Plano Central (Host / Plataforma)

#### 0. Gobernanza y Configuración de la Plataforma (HOST)

* **UC-C-00: IAM y Control de Acceso Administrativo (Host)**
* **Estado: ✅ IMPLEMENTADO**
* **Actor:** Super Admin / Billing Ops / SRE.
* **Precondición:** El usuario pertenece al personal interno de la plataforma.
* **Flujo Principal:**
  1. El personal accede a los portales de administración central autenticándose mediante credenciales fuertes.
  2. El sistema aplica validaciones de Doble Factor de Autenticación (2FA) o enrolamiento de Passkeys (WebAuthn) para prevenir acceso no autorizado.
  3. Los intentos fallidos y bloqueos son auditados.
* **Postcondición:** Sesión generada bajo el guard `central` con segregación absoluta de los tenants.

* **UC-C-17: Configuración Global y Branding de Plataforma**
* **Estado: ✅ IMPLEMENTADO**
* **Actor:** Super Admin.
* **Precondición:** El usuario tiene permiso `branding:manage` (Gate).
* **Flujo Principal:**
  1. El administrador ingresa a `PlatformBranding` y actualiza el nombre de la plataforma, color primario y URL del logo (con subida de archivo a Storage `public`).
  2. El componente normaliza los códigos hexadecimales y delega a `CentralBranding::set()`.
  3. Spatie ActivityLog registra el evento `branding_updated` detallando las propiedades alteradas.
* **Postcondición:** Todo el sistema centraliza la nueva identidad visual y el cambio queda sellado forensemente en el historial.

#### 1. Gestión del Ciclo de Vida y Provisioning (LIFECYCLE)

* **UC-C-01: Onboarding y Aprovisionamiento Transaccional Asíncrono**
* **Actor:** Motor de Signup (`CreateTenantAction`) / `ProvisionTenantJob` (pipeline asíncrono reanudable).
* **Precondición:** `CreateTenantData` validado (name, slug, email, plan_id). Para planes de pago, el checkout ocurre después; para planes trial/gratuitos, el aprovisionamiento se activa directamente.
* **Flujo Principal:**
  1. `CreateTenantAction` abre una transacción DB y crea el registro maestro en `tenants` con `UUIDv4` + slug único + `status = 'provisioning'`. Si el slug ya existe en estado `failed`, lo reutiliza (retry idempotente). Race condition de slug concurrente atrapado con `UniqueConstraintViolationException`.
  2. Dentro de la misma transacción, `ReserveTenantDomainAction` registra el subdominio `{slug}.{central_domain}` en la tabla `domains` de stancl/tenancy.
  3. Al hacer commit (`DB::afterCommit`), se despacha `ProvisionTenantJob` — la falla del job nunca hace rollback del registro del tenant.
  4. `ProvisionTenantJob` implementa `TenantAware` + `RehydratesTenantContext`, con 3 reintentos y backoff [10s, 60s]. El job ejecuta `ProvisionTenantPipeline`, que procesa pasos reanudables registrados en `provisioning_logs (tenant_id, step, status)`:
     - **Paso `db_schema`:** `SetupTenantCoreDataAction` ejecuta `TenantDataSeeder` bajo el contexto RLS del tenant — crea el registro inicial en `tenant_settings` (colores, timezone, locale, currency, mfa_required).
     - **Paso `infrastructure`:** `ProvisionInfrastructureAction` llama a `RailwayService::provisionDomain()` para DNS/CDN/Load Balancer del dominio primario.
     - **Paso `admin_user`:** Despacha el evento `TenantProvisioned` con email, nombre y password del admin. El listener `CreateInitialAdminUser` (Tenant/Access) corre dentro de `$tenant->run()`, crea los roles sistema (`admin`, `member`) mediante `EnsureTenantRolesExist`, crea el `User` con `firstOrCreate` (idempotente ante retries), le asigna el rol `admin` con `setPermissionsTeamId`, y envía `WelcomeTenantNotification` vía mail (queued).
  5. Al completar todos los pasos, el pipeline actualiza `status → active` + `provisioned_at = now()` e invalida la caché de colas de Horizon (`horizon_tenant_queues`). Registra en `activity_log` con log name `provisioning`.
  6. Si `failed()` del job se dispara (agotados los 3 reintentos), actualiza `status → failed` y registra en `activity_log`. `ProvisioningReconcileCommand` (`provisioning:reconcile`) re-despacha el job para tenants en estado `failed` o `provisioning` estancados (configurados por `provisioning.stale_provisioning_minutes`).
  7. Para planes de pago: `finalStatus = 'pending_payment'`. El tenant queda en ese estado hasta que `FulfillSubscription` (listener de `PaymentApproved`) lo activa, asignando `status → active` y creando el registro en `subscriptions`.
  8. Tenants en `pending_payment` que no pagaron dentro de la ventana configurada (`provisioning.pending_payment_expiry_hours`) son expirados por `ProvisioningReconcileCommand` → `status = 'expired'` + `OnboardingExpiredNotification`.

* **Postcondición:** El tenant queda en `status = active` (trial/free) o `pending_payment` (pago requerido), con subdominio reservado, roles base creados, usuario admin notificado y actividad auditada en `activity_log`.
* **Excepción:** Cualquier paso fallido registra el error en `provisioning_logs[status=failed]` y relanza la excepción. El pipeline es reanudable: en retry, los pasos con `status = completed` se saltan. La transacción de creación del tenant se revierte solo si falla antes del commit (no existe estado inconsistente post-commit sin job).
* **Componentes involucrados:**
  - `Central/Provisioning`: `CreateTenantAction`, `ProvisionTenantPipeline`, `SetupTenantCoreDataAction`, `ReserveTenantDomainAction`, `ProvisionTenantJob`, `ProvisioningReconcileCommand`
  - `Central/Operations`: `ProvisionInfrastructureAction`, `RailwayService`
  - `Central/Billing`: `FulfillSubscription` (listener de `PaymentApproved`)
  - `Platform/Events`: `TenantProvisioned`
  - `Tenant/Access`: `CreateInitialAdminUser` (listener), `EnsureTenantRolesExist`


* **UC-C-02: Scoring Antifraude y Aislamiento en Cuarentena**
* **Estado: ✅ IMPLEMENTADO**
* **Actor:** `RegisterTenant` (Livewire) → `FraudScoringAction` → `ProvisionTenantPipeline` → `QuarantineTenantAction`.
* **Precondición:** Formulario de registro validado en todos los pasos del wizard.
* **Flujo Principal:**
  1. En `RegisterTenant::register()`, antes de llamar a `CreateTenantAction`, se invoca `FraudScoringAction::evaluate(request(), email)` de forma **síncrona**. Evalúa señales sobre el email y la IP del request:
     - Email en dominio desechable conocido → +60 pts
     - Gmail+ alias trick (`user+tag@gmail.com`) → +30 pts
     - TLD sospechosa (`.xyz`, `.top`, `.click`, etc.) → +20 pts
     - Parte local del email < 3 caracteres → +15 pts
     - IP en lista de prefijos bloqueados (`config('fraud.blocked_ip_prefixes')`) → +50 pts
  2. Si `score ≥ 80` (configurable por `FRAUD_QUARANTINE_THRESHOLD`), `finalStatus = 'quarantine'`. El payload de señales se serializa en `CreateTenantData::fraud_signals_payload` como array plano (safe para queue serialization).
  3. `CreateTenantAction` crea el tenant con `status = 'provisioning'` y despacha `ProvisionTenantJob` con `fraudSignalsPayload` adjunto.
  4. El job reconstruye el `FraudSignals` VO y lo pasa a `ProvisionTenantPipeline::execute()`. El pipeline termina con `tenant->update(['status' => 'quarantine'])`.
  5. Un paso adicional `quarantine_notify` en el pipeline invoca `QuarantineTenantAction`: activa `read_only = true`, registra en `activity_log`, y despacha `SecOpsAlertNotification` (queued mail) al email configurado en `FRAUD_SECOPS_EMAIL`.
  6. El middleware `EnsureTenantIsActive` bloquea con `HTTP 403` cualquier request al subdominio del tenant en cuarentena. Los workers de ese tenant corren en cola `low` priority via `TenantQueueManager`.
  7. En `RegisterTenant`, si el tenant fue puesto en cuarentena, se muestra un mensaje neutro ("bajo revisión") y se redirige al home central — sin revelar el motivo al atacante.
  8. El Agente Central puede revisar y cambiar el estado en `ManageTenant` (`quarantine` es ahora un status válido en el enum).
* **Postcondición:** Tenant en `status = quarantine` + `read_only = true`. Acceso al workspace bloqueado (HTTP 403). Workers degradados a cola `low`. SecOps notificado con score y señales detalladas.
* **Excepción:** Si `FraudScoringAction` lanza excepción (improbable — es stateless/in-memory), el registro falla antes de crear el tenant. No hay efecto parcial.
* **Componentes involucrados:**
  - `Central/Growth/Domain/ValueObjects/FraudSignals` — VO con score + signals + ip + email
  - `Central/Growth/Application/Actions/FraudScoringAction` — evaluación síncrona, sin dependencias externas
  - `Central/Growth/Application/Actions/QuarantineTenantAction` — aplica `read_only` + activity log + notificación
  - `Central/Growth/Infrastructure/Notifications/SecOpsAlertNotification` — mail queued al equipo SecOps
  - `Central/Growth/Interface/Livewire/RegisterTenant` — punto de inyección del scoring
  - `Central/Provisioning/DTOs/CreateTenantData` — campo `fraud_signals_payload` para transportar al job
  - `Central/Provisioning/Jobs/ProvisionTenantJob` — transporta `fraudSignalsPayload` como array
  - `Central/Provisioning/Actions/ProvisionTenantPipeline` — paso `quarantine_notify` reanudable
  - `Platform/Tenancy/Interface/Http/Middleware/EnsureTenantIsActive` — bloqueo HTTP 403 para `quarantine`
  - `Central/Operations/Application/Services/TenantQueueManager` — cola `low` para `quarantine`
  - `Central/Provisioning/Livewire/ManageTenant` — permite cambiar `quarantine` → otro status
  - `config/fraud.php` — umbrales, email SecOps, listas de dominios/IPs extendibles via `.env`


* **UC-C-03: Suspensión Preventiva por Infracción de Términos o Impago**
* **Estado: ✅ IMPLEMENTADO**
* **Actor:** Sistema de Cobros (Dunning Engine) / Agente de Plataforma.
* **Precondición:** Agotamiento de reintentos de cobro (3 intentos fallidos) o abuso flagrante de cuota.
* **Flujo Principal:**
  1. El sistema (e.g. `ChargeSubscriptionAction`, o el comando de reconciliación) actualiza el campo `status` del tenant a `suspended`. Se despacha el evento `TenantSuspendedByDunning` y se registra en `activity_log`.
  2. A nivel de infraestructura, `TenantQueueManager` degrada todos los workers encolados de ese tenant a la cola `low` automáticamente.
  3. Todo tráfico HTTP entra por el middleware `EnsureTenantIsActive`. Al detectar `status === 'suspended'`, intercepta de inmediato la petición antes de evaluar las rutas públicas (como `tenant.home`).
  4. Si la ruta interceptada no es de login/facturación:
     - Si el usuario está **autenticado**, es redirigido forzosamente a la vista de facturación (`tenant.billing.plans`) para regularizar su pago.
     - Si el usuario es **visitante/público**, el middleware rechaza la petición con un `abort(402, 'Payment Required')` (vista pública de advertencia).
* **Postcondición:** Todo request a base de datos de dicho tenant (fuera de facturación/login) es rechazado en la capa de middleware. No se requiere purgar sesiones de la BD ni invalidar cachés complejos, ya que la barrera del middleware neutraliza efectivamente cualquier sesión abierta.


* **UC-C-04: Purga Dura y Derecho al Olvido (GDPR/Compliance)**
* **Estado: ✅ IMPLEMENTADO**
* **Actor:** Job Programado (`DeleteTenantAction` con `$hardDelete = true`).
* **Precondición:** Tenant eliminado vía petición que requiere borrado físico permanente.
* **Flujo Principal:**
  1. `DeleteTenantAction` despacha `PurgeTenantJob` a la cola por defecto.
  2. El job limpia los registros de Rate Limiting del tenant en Redis invocando `RateLimiter::clear()`.
  3. Ejecuta `PurgeTenantDataAction` para hacer un delete en cascada físico de todas las tablas relacionales vinculadas al tenant (pagos, logs, facturas, usuarios) y finalmente borra el registro de `tenants`.
  4. Borra físicamente todos los directorios de Storage (`local` y `public`) correspondientes a `tenant{id}`.
  5. Se registra la acción final `tenant_purged_from_infrastructure` en el Log central.
* **Postcondición:** El tenant se marca como purgado, sus archivos de S3/Local desaparecen, la BD central se limpia de sus FKs, y es completamente irrecuperable de acuerdo al derecho al olvido. No se genera evento hacia "Sistemas BI/DW" (rechazado por [PO/SCOPE]: sobreingeniería prematura sin sistema real).



---

#### 2. Monetización, Facturación y Planes (BILLING)

* **UC-C-05: Modificación Global de Catálogo y Feature Packaging**
* **Estado: ✅ IMPLEMENTADO**
* **Actor:** Billing Ops / Administrador de Producto.
* **Precondición:** Nuevas capacidades desarrolladas que deben empaquetarse en un tier comercial.
* **Flujo Principal:**
  1. El operador ingresa a `ManagePlan` (Livewire) y define el nombre, slug, precio mensual y anual.
  2. Establece límites numéricos (quotas como branches, staff, bookings) y marca checkboxes para habilitar módulos específicos (Feature Catalog integration).
  3. Ingresa el `metered_price` (precio marginal por unidad/token extra) que se guarda estructurado en el JSON de `features`.
  4. Ingresa manualmente el `stripe_id` correspondiente al producto en Stripe (correlación).
  5. Al guardar, se ejecuta `UpsertPlan`, persistiendo en BD y purgando el caché `tenant:{id}:features` de todos los tenants afectados para actualización inmediata.
* **Postcondición:** El nuevo plan queda disponible inmediatamente en el selector de planes.
* **[PO/SCOPE Veto]:** Se descarta la "sincronización atómica con Stripe/Paddle vía API" como sobreingeniería. El sistema actual usa una integración de checkout hospedado (dLocal o manual) y correlación de IDs. Programar un CRUD completo hacia la API de Stripe cuando la fuente de verdad comercial suele residir en el dashboard del Gateway no aporta valor al estado actual del MVP.


* **UC-C-06: Ingesta de Consumo Agregado y Facturación Medida (Metered Billing)**
* **Estado: ✅ IMPLEMENTADO**
* **Ubicación Real:** `app/Modules/Platform/Metering/` (Transversal a la Plataforma. Originalmente descrito en Central/Billing, lo cual fue un error de trazabilidad ya que el consumo se emite desde el Tenant).
* **Actor:** Aplicación (Tiempo Real) / Worker Cron (`AggregateUsageJob`).
* **Precondición:** Módulos de aplicación llamando a la acción `RecordUsage` con la métrica y cantidad consumida.
* **Flujo Principal:**
  1. El sistema inserta un registro atómico y síncrono en `UsageEvent` en PostgreSQL por cada evento.
  2. Incrementa de forma asíncrona/rápida una llave en Redis para evaluación inmediata de cuotas.
  3. Al cierre de ciclo, el job `AggregateUsageJob` (ejecutado bajo contexto `TenantAware`) agrupa los eventos del periodo.
  4. Los consolida en el modelo `UsageRollup`.
  5. Si el medidor es facturable, reporta el valor al proveedor de cobros mediante la interfaz `MeterBillingProvider` y marca el rollup como `billed_at`.
* **Postcondición:** PostgreSQL preserva el histórico inmutable evento por evento para auditoría.
* **Componentes Involucrados:** `Application/Actions/RecordUsage.php`, `Application/Jobs/AggregateUsageJob.php`, `Contracts/MeterBillingProvider.php`, Models (`UsageEvent`, `UsageRollup`).
* **[PO/SCOPE Veto]:** Se descarta el "buffer temporal en Redis con MULTI/EXEC para volcado diferido" documentado originalmente. Escribir asíncronamente a BD conlleva riesgo de pérdida de datos de facturación ante un reinicio del worker de Redis. La inserción directa a Postgres está perfectamente capacitada para el volumen actual del MVP y garantiza durabilidad (Ledger approach).

* **UC-C-07: Orquestación del Motor de Dunning y Periodos de Gracia**
* **Estado: ✅ IMPLEMENTADO**
* **Actor:** Webhook Handler de Pasarela de Pagos.
* **Precondición:** Recepción de un evento `invoice.payment_failed` a través del `WebhookController`.
* **Flujo Principal:**
  1. El sistema despacha el evento `PaymentFailed` tras verificar la firma criptográfica.
  2. El listener `HandlePaymentFailure` intercepta el evento. Si el contador de reintentos (`attemptCount`) es menor a 3, cambia el estado del tenant a `past_due`.
  3. Si alcanza 3 reintentos fallidos, cambia el estado a `suspended` (gatillando el UC-C-03).
  4. Mientras el estado sea `past_due`, el middleware `EnsureTenantIsActive` permite el acceso al workspace.
  5. El layout del workspace inyecta el `subscription-banner` en color naranja advirtiendo sobre el periodo de gracia y proveyendo un enlace a la actualización de pago.
* **Postcondición:** El workspace permanece operativo temporalmente, advirtiendo al usuario de la falla.
* **[PO/SCOPE Veto]:** Se descarta "Programar reintentos inteligentes en 24/72h" y "Enviar correo transaccional" desde el backend de Laravel. Ambas funciones son responsabilidades nativas del Motor de Dunning de la pasarela de pagos (Stripe Smart Retries / dLocal). Reimplementarlas en la aplicación es reinventar la rueda (sobreingeniería pura) y desincroniza la fuente de verdad. Se asume que el Gateway está configurado para manejar la cadencia de reintentos y notificaciones de correo.



---

#### 3. Soporte e Impersonación Cero-Confianza (GOVERNANCE & SUPPORT)

* **UC-C-08: Inicio de Sesión de Soporte Asistido (Login As Tenant)**
* **Estado: ✅ IMPLEMENTADO**
* **Actor:** Support Agent / Super Admin.
* **Precondición:** El agente cuenta con el permiso `support:impersonate`.
* **Flujo Principal:**
  1. El agente selecciona el tenant en el panel Host (`TenantList` Livewire) y solicita una sesión de soporte llenando el *Ticket ID* obligatorio y una *justificación textual* (mínimo 20 caracteres).
  2. El sistema emite un token de sesión especial (`SupportSession`) firmado, con un TTL estricto de 30 minutos sin posibilidad de renovación (`ImpersonateTenantAction`).
  3. Registra el evento usando Spatie ActivityLog (`activity('support')->log('impersonation_started')`), capturando el ID del agente, sesión y razón.
  4. Abre el dashboard del tenant en una pestaña nueva mediante inyección de script, forzando la visualización de un banner persistente en el layout (`impersonation-banner`).
* **Postcondición:** Todas las acciones ejecutadas por el agente quedan registradas a través del middleware `AuditImpersonationActions`.
* **[PO/SCOPE Veto]:** Se descarta "enviar una alerta push/email al Owner del tenant exigiendo aprobación explícita" (doble custodia) como sobreingeniería. Actualmente no existe un tier 'Enterprise' formalizado con soporte de doble custodia, y desarrollar una máquina de estados asíncrona de aprobación para el acceso de soporte es prematuro. La auditoría estricta y el logging resuelven el compliance para el estado actual.


* **UC-C-09: Terminación Forzada de Impersonación y Auditoría Forense**
* **Estado: ✅ IMPLEMENTADO**
* **Actor:** Sistema (Expiración de TTL) / Agente de Soporte.
* **Precondición:** Sesión de soporte en curso.
* **Flujo Principal:**
  1. El agente presiona el botón "Terminar Sesión" o el contador del token expira (30 minutos estrictos).
  2. En cada petición, el middleware `AuditImpersonationActions` intercepta la sesión. Si detecta que expiró (`expires_at->isPast()`) o fue cerrada, revoca inmediatamente los identificadores, cierra la sesión y expulsa al agente con un error HTTP 401.
  3. Tras el cierre, el sistema compila un reporte de auditoría consultando `Spatie ActivityLog` (`properties->support_session_id`) para recuperar cada mutación ejecutada durante la sesión.
  4. Se envía una notificación (`ImpersonationEndedNotification`) por correo al Owner con el desglose de acciones inyectado dinámicamente en el cuerpo del mensaje.
* **Postcondición:** Cero retención de privilegios y registro forense inmutable almacenado e informado.
* **[PO/SCOPE Veto]:** Se descarta "El Owner del tenant presiona el botón Terminar Acceso". Esto implicaría desarrollar todo un módulo de gestión de sesiones activas en el dashboard del cliente, lo cual es sobreingeniería masiva para el volumen de tickets de soporte actual. El TTL estricto de 30 minutos y la notificación post-sesión cubren la necesidad de seguridad del Owner sin inflar el alcance del MVP.



---

#### 4. Resiliencia, Tráfico y Límites (OPERATIONS & SRE)

* **UC-C-10: Mitigación de Vecinos Ruidosos (Adaptive Throttling)**
* **Estado: ✅ IMPLEMENTADO**
* **Actor:** Middleware de Gateway (`ApplyTenantRateLimits`).
* **Precondición:** Un tenant dispara scripts masivos contra la API o la aplicación web superando su cuota.
* **Flujo Principal:**
  1. El `TenantRateLimiter` extrae el límite de requests por minuto (`rate_limit_rpm`) desde la configuración del plan del tenant activo.
  2. El algoritmo en base a Redis (vía el facade `RateLimiter`) procesa la petición; si detecta saturación, retorna de inmediato un código HTTP 429 (`Too Many Requests`).
  3. La respuesta 429 inyecta los headers `Retry-After`, `X-RateLimit-Limit` y `X-RateLimit-Remaining`.
* **Postcondición:** La plataforma desecha la carga excedente en la capa de gateway (Redis) protegiendo el pool de conexiones de base de datos.
* **[PO/SCOPE Veto]:** Se descarta "degradar dinámicamente la prioridad de los workers en segundo plano a una cola low-priority" tras detectar saturación de base de datos. Esta es una feature de observabilidad profunda (SRE) que requiere interceptar el dispatcher de jobs, calcular latencias de DB por tenant en tiempo real y re-encolar. Resulta en sobreingeniería crítica para el alcance actual. El rate limit estricto a nivel HTTP (Edge) soluciona el 95% de los problemas de vecinos ruidosos deteniendo el tráfico abusivo antes de que llegue a generar workers.


* **UC-C-11: Despliegue Canario y Activación Selectiva de Feature Flags**
* **Estado: 🚫 VETADO (YAGNI)**
* **Actor:** Administrador de Plataforma / Release Manager.
* **Precondición:** Módulo en versión beta que no debe exponerse a la totalidad de los tenants.
* **Flujo Principal:** (Propuesto originalmente con Redis `SISMEMBER` y reglas condicionales).
* **[PO/SCOPE Veto]:** Se veta absolutamente el desarrollo de un motor de Feature Flags propio o la instalación de `laravel/pennant` en este momento. La precondición habla de un "módulo en versión beta", el cual *no existe* actualmente. Construir infraestructura de despliegue canario (evaluadores de reglas, UI en el Host, middlewares) para features hipotéticos es el epítome de la sobreingeniería. Cuando exista un módulo real que requiera rollout progresivo, se instalará Laravel Pennant; hasta entonces, este caso de uso queda congelado.

* **UC-C-12: Orquestación del Ciclo de Vida del Tenant**
* **Estado: ✅ IMPLEMENTADO**
* **Actor:** Sistema (Triggers automáticos) / Super Admin.
* **Alcance:** Ejecución de la máquina de estados. Se ha implementado a través del middleware `EnsureTenantIsActive`, el cual intercepta y diferencia estados críticos (`suspended`, `quarantine`, `pending_payment`, `archived`, `maintenance_mode`). Las compensaciones y rollbacks de aprovisionamiento están resguardadas por transacciones de base de datos (`DB::transaction` en `CreateTenantAction`).
* **Aislamiento/Gobernanza:** La interceptación ocurre en capa de middleware en cada request, garantizando aislamiento inmediato sin depender de rutinas asíncronas.
* **[PO/SCOPE Veto]:** Se descarta "expulsar sesiones en Redis" de forma literal (borrado físico de la clave de sesión). Para lograrlo, se requeriría un mapa inverso de `tenant_id` hacia múltiples `session_ids` en Redis, introduciendo latencia de escritura. El middleware `EnsureTenantIsActive` ya bloquea el request en el siguiente ciclo HTTP con código 402, 403 o 503 dependiendo del estado, logrando el mismo objetivo (expulsión funcional) con nula sobrecarga arquitectónica.


* **UC-C-13: Ciclo de Vida de Suscripciones y Cambios de Plan**
* **Estado: ⚠️ PARCIAL / VETADO (Downgrades Complejos)**
* **Actor:** Billing Ops / Sistema de Suscripciones.
* **Alcance:** Gestión de transiciones comerciales básicas.
* **Implementado:** Cancelaciones programadas al fin de ciclo (`cancel at period end`) vía el action `CancelSubscription`, apoyado nativamente por Laravel Cashier y sincronizado vía webhooks.
* **[PO/SCOPE Veto]:** Se veta la implementación de "Downgrades automáticos con validación de cuotas excedidas" y "Cambios automatizados con liquidación de crédito (Proration)". Construir un motor genérico que contabilice el uso real de recursos del tenant (ej. usuarios, almacenamiento, proyectos) para validar si "cabe" en un plan inferior antes de permitir el swap es una sobreingeniería colosal para esta etapa. En un SaaS MVP, la práctica estándar es requerir que el cliente contacte a soporte para realizar un downgrade, permitiendo a Billing Ops hacer el swap y la liquidación de crédito manualmente en el dashboard de Stripe. La complejidad de proration automático queda fuera del MVP.


* **UC-C-14: Conciliación Financiera, Disputas y Fallos de Pago**
* **Estado: ⚠️ PARCIAL / VETADO (Contabilidad Compleja)**
* **Actor:** Billing Ops / Webhook Engine.
* **Alcance:** Control de eventos transaccionales fuera del flujo nominal.
* **Implementado:** Captura de webhooks de fallos de cobro (`HandlePaymentFailure`) y conciliación de facturas locales vs pasarela (`SyncInvoices`).
* **[PO/SCOPE Veto]:** Se descarta "gestión de disputas, emisión de reembolsos con reducción proporcional de límites y detección de discrepancias contables". Delegamos el 100% de la gestión de disputas (chargebacks) y reembolsos al dashboard nativo de Stripe/DLocal. Intentar construir un motor ERP/Financiero de doble entrada (Dual-entry ledger) que calcule matemáticamente cuánto almacenamiento quitarle a un tenant si se le reembolsa el 30% de su pago es una locura arquitectónica para un MVP. Si un usuario gana una disputa o exige reembolso, Soporte gestiona la devolución en Stripe y cancela la suscripción del tenant manualmente. Nada más.


* **UC-C-15: Incidentes, Mantenimiento Global y Resiliencia**
* **Estado: ✅ IMPLEMENTADO / VETADO (Failover a nivel App)**
* **Actor:** SRE / Incident Commander.
* **Alcance:** Puesta en marcha de modos de mantenimiento y read-only.
* **Implementado:** 
  1. **Mantenimiento Selectivo / Read-Only:** El middleware `EnsureTenantIsActive` ya soporta las banderas `maintenance_mode` (retorna 503) y `read_only` (bloquea peticiones no-GET con 403) a nivel individual por tenant.
  2. **Mantenimiento Global:** Se apoya en el comando nativo de Laravel (`php artisan down`) que interrumpe el enrutamiento central.
* **[PO/SCOPE Veto]:** Se descarta "mantenimiento por cluster" y "ejecuta protocolos de failover o recuperación de datos". El código de la aplicación (Laravel) no debe responsabilizarse de orquestar failovers de bases de datos o recuperación de desastres (DR). Esa es una competencia exclusiva de la capa de infraestructura (ej. AWS RDS Multi-AZ, Terraform, Kubernetes). Programar scripts de conmutación por error dentro del monolito PHP es un anti-patrón de DevOps y está completamente fuera del alcance del MVP.


* **UC-C-16: Observabilidad, Telemetría Cruzada y Auditoría Forense**
* **Estado: ✅ IMPLEMENTADO / VETADO (Métricas APM In-house)**
* **Actor:** SRE / Agente de Soporte Nivel 3.
* **Alcance:** Trazabilidad, logs y auditoría administrativa.
* **Implementado:** 
  1. **Agregación de logs con correlación:** Se creó el listener `SetTenantLogContext` (suscrito al evento `TenancyInitialized`) que inyecta el `tenant_id` y `tenant_slug` en el Facade `Context` de Laravel 11. Esto garantiza que todos los logs y jobs en segundo plano queden automáticamente taggeados, facilitando el rastro distribuido (correlación).
  2. **Auditoría Forense Inmutable:** Completamente cubierto por Spatie ActivityLog. Todas las acciones administrativas y de soporte (`ImpersonateTenantAction`) quedan selladas con el ID del agente y sesión, listas para auditorías SOC2.
* **[PO/SCOPE Veto]:** Se descarta tajantemente la "Ingesta de métricas de salud por tenant (p95/p99 de queries, concurrencia de conexiones de BD, uso de storage)". Desarrollar un APM in-house embebido en Laravel para calcular latencia p99 de bases de datos o monitorear la concurrencia TCP es el mayor anti-patrón de un MVP. Esas métricas se delegan a un APM externo especializado (como Datadog, New Relic o, en su defecto, Laravel Pulse en una fase posterior). El framework no debe dedicarse a autoevaluar su propia latencia p99 a nivel de red/conexiones.

---

### Casos de Uso: Plano del Tenant (Workspace / Cliente)

#### 1. Identidad, Acceso y Seguridad del Workspace (IAM)

* **UC-T-01: Asignación Granular de Roles y Creación de Permisos Personalizados**
* **Actor:** Tenant Admin / Owner.
* **Precondición:** Tenant suscrito a un plan que habilite RBAC avanzado o Custom Roles.
* **Flujo Principal:**
1. El administrador ingresa al submódulo de roles del workspace.
2. Crea un nuevo perfil (ej. "Auditor Financiero") y selecciona permisos booleanos específicos (`billing.read`, `invoices.export`, `documents.read`).
3. Asigna este rol a usuarios específicos del equipo mediante un formulario seguro.
4. El sistema persiste la asignación asegurando que la clave foránea respete el `tenant_id` actual.


* **Postcondición:** Los usuarios afectados ven restringida su interfaz y cualquier request a endpoints no autorizados devuelve HTTP 403.


* **UC-T-02: Configuración de Single Sign-On Corporativo (SAML 2.0 / OIDC)**
* **Actor:** IT Admin del Workspace.
* **Precondición:** Plan Enterprise activo; el cliente posee un Identity Provider (IdP) como Okta o Microsoft Entra ID.
* **Flujo Principal:**
1. El administrador pega la URL de metadatos de su IdP, el Entity ID y el certificado X.509 en la pantalla de configuración de SSO.
2. El sistema valida la estructura del certificado y genera la Assertion Consumer Service (ACS) URL propia del tenant.
3. El administrador activa el toggle "Forzar SSO obligatorio para el dominio corporativo (`@empresa.com`)".
4. El sistema ejecuta una prueba de autenticación de ida y vuelta; al ser exitosa, bloquea el login tradicional con contraseña para los correos bajo ese dominio.


* **Postcondición:** Todos los accesos de ese dominio son delegados al IdP corporativo del cliente.


* **UC-T-03: Auditoría y Revocación Remota de Sesiones Activas**
* **Actor:** Tenant Admin / Usuario Final.
* **Precondición:** Sesiones concurrentes iniciadas en múltiples navegadores o dispositivos.
* **Flujo Principal:**
1. El usuario o el admin accede al panel de seguridad de cuenta/workspace.
2. El sistema lista las sesiones activas leyendo las claves de sesión en Redis filtradas por `tenant_id` y `user_id`, mostrando IP, navegador y hora de último acceso.
3. El administrador selecciona "Cerrar sesión en todos los demás dispositivos".
4. El backend destruye las claves de sesión correspondientes en Redis inmediatamente.


* **Postcondición:** Cualquier request subsiguiente desde los dispositivos revocados es forzada al flujo de login.



---

#### 2. Personalización, Red y Conectividad (BRANDING & INTEGRATIONS)

* **UC-T-04: Vinculación de Dominio Personalizado (CNAME) y Aprovisionamiento TLS**
* **Actor:** Tenant Admin.
* **Precondición:** El cliente posee su propio dominio (`app.empresa.com`).
* **Flujo Principal:**
1. El admin ingresa `app.empresa.com` en los ajustes del workspace.
2. El sistema genera un registro de validación DNS (ej. `CNAME` apuntando a `cname.saas.com` y un `TXT` de verificación de propiedad).
3. El cliente configura los registros en su proveedor DNS y pulsa "Verificar".
4. El backend de Laravel consulta los servidores DNS autoritativos; al validar coincidencia, registra el hostname en el proxy reverso/API edge.
5. Se orquesta la emisión automática de un certificado SSL/TLS con Let's Encrypt para ese hostname.


* **Postcondición:** Los usuarios del tenant pueden acceder a su workspace desde `app.empresa.com` con cifrado HTTPS válido y transparente.


* **UC-T-05: Emisión, Rotación y Restricción de API Keys del Workspace**
* **Actor:** Desarrollador del Tenant / Admin.
* **Precondición:** El tenant requiere automatizar acciones contra la API pública del SaaS.
* **Flujo Principal:**
1. El usuario pulsa "Crear API Key", introduce una descripción (ej. "Integración ERP") y selecciona scopes limitados (`invoices:read`, `users:write`).
2. El sistema genera una clave aleatoria criptográfica prefijada (`sk_live_...`).
3. Computa el hash SHA-256 de la clave y lo guarda en la tabla `api_keys` con el `tenant_id` y scopes.
4. Muestra la clave en texto plano al usuario **una sola vez**, advirtiendo que no podrá ser recuperada.


* **Postcondición:** Las llamadas entrantes con el header `Authorization: Bearer sk_live_...` autentican directamente contra el contexto de ese tenant.


* **UC-T-06: Configuración de Webhooks Salientes con Firma Criptográfica**
* **Actor:** Desarrollador del Tenant / Integrador.
* **Precondición:** Endpoint HTTPS propio del cliente listo para recibir eventos.
* **Flujo Principal:**
1. El usuario registra la URL de destino (`[https://erp.empresa.com/webhooks](https://erp.empresa.com/webhooks)`) y selecciona los eventos a escuchar (`user.created`, `document.processed`).
2. El sistema genera un secreto compartido (`whsec_...`).
3. Al ocurrir una mutación en el workspace, un worker toma el payload, calcula la firma HMAC-SHA256 usando el secreto y envía la petición POST con el header `X-Signature-SHA256`.
4. El sistema registra el status HTTP recibido y programa hasta 5 reintentos con retroceso exponencial si la respuesta es distinta de 2xx.


* **Postcondición:** El cliente recibe telemetría asíncrona verificable de sus eventos internos.



---

#### 3. Autogestión Financiera del Cliente (SELF-SERVICE BILLING)

* **UC-T-07: Actualización de Plan (Upgrade Inmediato con Prorrateo)**
* **Actor:** Tenant Admin / Billing Contact.
* **Precondición:** Suscripción activa en plan "Starter" requiriendo pasar a "Pro" por límite de asientos.
* **Flujo Principal:**
1. El admin entra a la pestaña de facturación y selecciona el plan superior.
2. El frontend consume un endpoint que calcula el prorrateo exacto del ciclo en curso con la pasarela de pagos.
3. El admin confirma el cargo diferencial.
4. El backend invoca la API de la pasarela, cobra la diferencia prorrateada y actualiza atómicamente el `plan_id` en la tabla `tenants`.
5. El caché de Redis para el tenant refresca los nuevos límites de inmediato.


* **Postcondición:** El workspace dispone de las nuevas capacidades y asientos sin interrupción de servicio.


* **UC-T-08: Configuración de Umbrales de Alerta de Consumo Preventivo**
* **Actor:** Tenant Admin.
* **Precondición:** Plan sujeto a costos variables o límites duros de almacenamiento/tokens.
* **Flujo Principal:**
1. El usuario define una regla interna: *"Notificar por email cuando el storage alcance el 80% y cuando el consumo de tokens supere los $100 USD"*.
2. El sistema guarda estos umbrales en la tabla `tenant_alert_rules`.
3. Durante el pipeline de agregación horaria, si el consumo computado excede la regla, el sistema despacha una notificación de advertencia al equipo de finanzas del tenant.


* **Postcondición:** El tenant previene bloqueos de servicio o cargos no contemplados sin requerir intervención de soporte central.



---

#### 4. Operaciones de Datos, Soberanía y Offboarding (DATA LIFECYCLE)

* **UC-T-09: Exportación Masiva Autolimpiable de Datos (Cumplimiento Portabilidad)**
* **Actor:** Tenant Admin / Data Protection Officer.
* **Precondición:** El usuario tiene permisos para exportar la base documental del workspace.
* **Flujo Principal:**
1. El usuario solicita una exportación completa de los datos en formato JSON/CSV desde el panel de ajustes.
2. El sistema encola un background job asignándole el `tenant_id`.
3. El worker, forzado bajo la política RLS del tenant, lee todas las tablas asociadas y comprime los datos junto con los archivos del S3 en un archivo ZIP cifrado.
4. Sube el paquete a un bucket temporal y genera una URL prefirmada con caducidad estricta de 24 horas.
5. Envía un correo con el link de descarga al usuario solicitante.


* **Postcondición:** Tras 24 horas, una regla de ciclo de vida de almacenamiento purga físicamente el archivo ZIP del bucket temporal.


* **UC-T-10: Solicitud de Cierre Definitivo de Cuenta y Purga Self-Service**
* **Actor:** Tenant Owner.
* **Precondición:** El usuario es el titular absoluto del workspace y no tiene facturas en disputa.
* **Flujo Principal:**
1. El Owner accede al área de peligro (*Danger Zone*) y solicita la rescisión del servicio.
2. El sistema exige reautenticación mediante contraseña y código 2FA/MFA.
3. Se solicita la descarga opcional de un backup final de los datos.
4. El sistema cancela la suscripción en la pasarela de pagos al término del periodo facturado, bloquea nuevos registros y marca el workspace con `deleted_at = NOW()`.
5. Programa la tarea destructiva física para ejecución tras la ventana de gracia legal de 30 días.


* **Postcondición:** El tenant queda inaccesible para sus usuarios de forma irreversible y la infraestructura central programa la limpieza total de sus recursos.

* **UC-T-11: Ciclo de Vida de Miembros del Workspace**
* **Actor:** Tenant Admin.
* **Alcance:** Proceso de invitación mediante tokens criptográficos de un solo uso con caducidad; activación, desactivación inmediata y baja definitiva de usuarios. Reasignación mandatoria de recursos huérfanos (documentos, registros, pipelines) antes de la eliminación del usuario para evitar inconsistencias referenciales.
* **Aislamiento:** Todo query forzado bajo RLS; el usuario solo existe dentro del alcance del `tenant_id` contextual.


* **UC-T-12: Recuperación de Acceso, MFA y Gestión de Credenciales**
* **Actor:** Usuario del Tenant.
* **Alcance:** Recuperación de cuenta por reseteo seguro de contraseña vía email, enrolamiento y desafío de MFA (TOTP / WebAuthn), generación y consumo de códigos de respaldo (*backup codes*), y rotación de credenciales con revocación simultánea de tokens de sesión activos.


* **UC-T-13: Transparencia Financiera, Consumo e Histórico de Invoices**
* **Actor:** Tenant Admin / Contacto de Facturación.
* **Alcance:** Vista detallada de consumo medido en tiempo real (storage, API calls, tokens de IA) vs. cuotas contratadas; historial de cargos, estado de cobro, descarga de facturas fiscales en PDF y actualización autónoma de métodos de pago (tarjeta/SEPA) delegada a portales seguros.


* **UC-T-14: Gestión del Ciclo de Vida de Integraciones (Ecosistema)**
* **Actor:** Tenant Admin / Desarrollador del Workspace.
* **Alcance:** Conexión y autorización OAuth con herramientas de terceros (Slack, Google Workspace, CRMs); almacenamiento cifrado en reposo de access/refresh tokens del tenant; monitorización del estado de salud de la sincronización y rotación/reautorización ante expiración de credenciales externas.


* **UC-T-15: Ingesta, Validación y Migración Masiva de Datos**
* **Actor:** Tenant Admin / Integrador.
* **Alcance:** Subida de archivos planos (CSV/JSON/XLSX) para carga masiva de entidades; validación estricta de esquema y duplicados en memoria; preview de transformación previo a confirmación; procesamiento asíncrono en lotes bajo transacción RLS; reporte de inconsistencias por fila y mecanismo de rollback atómico.


* **UC-T-16: Administración y Políticas del Workspace**
* **Actor:** Tenant Admin.
* **Alcance:** Configuración central de parámetros operacionales del cliente: metadatos de identidad (nombre, slug, branding básico), preferencias de localización (zona horaria por defecto, moneda contable, idioma base), políticas de retención de registros y reglas de caducidad automática de sesiones inactivas.
