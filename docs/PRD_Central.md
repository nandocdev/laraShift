# PRD Central

**Metadata**

| Campo    | Valor                                                                                                            |
| -------- | ---------------------------------------------------------------------------------------------------------------- |
| Owner    | TBD                                                                                                              |
| Created  | 2026-06-01                                                                                                       |
| Updated  | 2026-06-02                                                                                                       |
| Status   | Draft v2                                                                                                         |
| Contexto | Bounded context Central: auth global, provisioning de tenants, facturación, soporte e infraestructura compartida |

---

## Architecture Constraints

- Modular Monolith — sin microservicios, sin RPC entre módulos
- Single DB (PostgreSQL) con schema `central_*`
- Tenant isolation via middleware + scoped queries — nunca cross-tenant data leak
- Redis para rate limits, sesiones y cuotas
- Queue isolation por tenant para evitar noisy neighbor
- Auth scoping estricto: central users != tenant users

---

## 1. Auth

### Overview

Autenticación y seguridad para operadores y administradores globales. Soporta sesiones con 2FA, políticas de acceso y auditoría.

### Business Goal

Proteger operaciones administrativas críticas. Minimizar superficie de ataque. Proveer controles operacionales (invalidación de sesiones, auditoría, lockouts).

### Personas

| Persona                | Descripción                                             |
| ---------------------- | ------------------------------------------------------- |
| Operador Central       | Soporte / ops — acceso a herramientas de administración |
| Global Admin           | Gestión de tenants y facturación                        |
| Usuario Administrativo | Gestión de producto                                     |

### User Stories

| ID     | Historia                                                                                                    |
| ------ | ----------------------------------------------------------------------------------------------------------- |
| US-001 | Como Operador, quiero iniciar sesión con credenciales + 2FA para acceder al dashboard central.              |
| US-002 | Como Global Admin, quiero invalidar todas las sesiones de un usuario para forzar reautenticación inmediata. |
| US-003 | Como Operador, quiero consultar historial de logins y cambios de sesión para auditoría.                     |
| US-004 | Como Usuario, quiero recuperar acceso mediante forgot/reset password con expiración y notificación.         |
| US-005 | Como Admin, quiero imponer políticas de contraseña y límites de sesiones concurrentes.                      |

### Acceptance Criteria

**US-001 — Login + 2FA**
- Login exitoso con credenciales válidas + TOTP: respuesta en p95 < 300ms.
- Credenciales inválidas: respuesta con código `401` y mensaje genérico (no revelar si el email existe).
- MFA faltante o inválido: respuesta `403` con instrucción de enrollment.
- Token emitido: short-lived JWT (15 min) + refresh token (7 días) almacenado como hash en DB.

**US-002 — Revocación de sesiones**
- Revocación efectiva en < 60s: sesión revocada no puede usar refresh token.
- Endpoint requiere rol `global_admin`; intento sin rol retorna `403`.
- Evento `CentralSessionRevoked` emitido con `reason` obligatorio.

**US-003 — Audit logs**
- Consulta por rango de fechas retorna resultados en p95 < 200ms (índice en `created_at`).
- Cada entrada incluye: `user_id`, `action`, `ip`, `user_agent`, `metadata` (JSON), `created_at`.
- Logs son append-only — sin UPDATE ni DELETE sobre `central_audit_logs`.

**US-004 — Forgot/Reset password**
- Token de reset expira en 1h. Uso del token lo marca como `used_at`; segundo intento retorna `410`.
- Email enviado en < 30s tras la solicitud (job en queue de alta prioridad).
- Reset exitoso invalida todas las sesiones activas del usuario.

**US-005 — Políticas de contraseña y sesiones**
- Mínimo 12 caracteres. Validación con Fortify.
- Bloqueo tras 5 intentos fallidos en 15 minutos: `locked_until = now() + 15min`.
- Máximo de sesiones concurrentes configurable por rol (default: 3). Sesión más antigua revocada al exceder.

### Data Model

```sql
central_users (
  id              UUID PRIMARY KEY,
  email           VARCHAR(255) UNIQUE NOT NULL,
  name            VARCHAR(255) NOT NULL,
  password_hash   VARCHAR(255) NOT NULL,           -- argon2id
  is_global_admin BOOLEAN DEFAULT FALSE,
  locked_until    TIMESTAMP NULL,
  created_at      TIMESTAMP NOT NULL,
  updated_at      TIMESTAMP NOT NULL
)

central_sessions (
  id           UUID PRIMARY KEY,
  user_id      UUID REFERENCES central_users(id),
  token_hash   VARCHAR(255) NOT NULL,              -- hash del refresh token
  issued_at    TIMESTAMP NOT NULL,
  expires_at   TIMESTAMP NOT NULL,
  ip           INET NOT NULL,
  user_agent   TEXT,
  revoked_at   TIMESTAMP NULL,
  INDEX (user_id, revoked_at)
)

central_2fa (
  id                  UUID PRIMARY KEY,
  user_id             UUID REFERENCES central_users(id) UNIQUE,
  method              ENUM('totp', 'webauthn') NOT NULL,
  secret              TEXT NOT NULL,               -- cifrado en reposo
  recovery_codes_hash TEXT NOT NULL,               -- JSON array de hashes
  enrolled_at         TIMESTAMP NOT NULL
)

central_audit_logs (
  id         UUID PRIMARY KEY,
  user_id    UUID REFERENCES central_users(id),
  action     VARCHAR(100) NOT NULL,
  metadata   JSONB,
  ip         INET,
  created_at TIMESTAMP NOT NULL,
  INDEX (user_id, created_at),
  INDEX (created_at)
  -- Sin UPDATE, sin DELETE. Append-only.
)
```

### Events

```
CentralUserLoggedIn(user_id, session_id, ip, user_agent)
CentralUserLoggedOut(user_id, session_id)
CentralSessionRevoked(user_id, session_id, reason)
CentralPasswordResetRequested(user_id, token_id)
CentralPasswordResetCompleted(user_id)
Central2FAEnrolled(user_id, method)
```

### API

| Método | Endpoint                   | Descripción                                |
| ------ | -------------------------- | ------------------------------------------ |
| POST   | `/central/login`           | Credenciales → short-lived token + refresh |
| POST   | `/central/2fa/verify`      | Verificar TOTP                             |
| POST   | `/central/logout`          | Revocar sesión actual                      |
| POST   | `/central/sessions/revoke` | Revocar sesión(s) por id/user (admin)      |
| POST   | `/central/password/forgot` | Solicitar reset                            |
| POST   | `/central/password/reset`  | Aplicar nuevo password                     |
| GET    | `/central/audit/logs`      | Consultar audit logs (roles autorizados)   |

### Security

- Rate limit: 100 req/min por IP global; 10 req/min por cuenta en `/login`.
- CAPTCHA activable tras N fallos configurables (default: 3).
- Passwords: hash argon2id. Nunca almacenar tokens en texto plano — solo hashes.
- Refresh tokens: rotación en cada uso. Token anterior invalidado.
- 2FA secrets: cifrados en reposo (AES-256-GCM). Recovery codes: hash bcrypt individual.
- Sessions: `Secure`, `HttpOnly`, `SameSite=Strict` en cookies.

### Tests

- **Unit**: validación de password policy, generación/verificación TOTP, lógica de lockout.
- **Integration**: flujo completo login → 2FA → refresh → revocación. Forgot/reset end-to-end con mail stub.
- **Security**: brute-force simulation (debe activar lockout en intento 5), token reuse (debe retornar 410), session revocation (token inválido en < 60s).

### Métricas & SLAs

| Métrica                                          | Target           |
| ------------------------------------------------ | ---------------- |
| Login p95 (sin 2FA externo)                      | < 300ms          |
| Revocación efectiva de sesión                    | < 60s            |
| Alerta por bloqueo masivo (>10 usuarios en 5min) | < 5min detección |
| Disponibilidad                                   | 99.9% mensual    |

### Dependencias

- Email gateway (reset password, alertas)
- Redis (rate limits, sesiones cache)
- Laravel Fortify (políticas de password)
- Logging/Telemetry (audit persistence)

### Edge Cases

| Caso                | Mitigación                                                                                  |
| ------------------- | ------------------------------------------------------------------------------------------- |
| Brute force         | Lockout temporal + CAPTCHA + IP throttling + alerta                                         |
| Session hijacking   | Refresh token rotation + detección de uso concurrente + revocación forzada                  |
| Expired reset token | One-time token + expiración 1h + mensaje claro al usuario                                   |
| MFA perdido         | Recovery codes. Si agotados: flujo de aprobación manual por ops con audit entry obligatorio |

---

## 2. Provisioning

### Overview

Creación y ciclo de vida de tenants: onboarding, activación, suspensión, archivado y eliminación. El proceso es atómico con rollback automático en fallo.

### Business Goal

Reducir errores de provisioning. Garantizar consistencia de estado en toda la infraestructura (DB, storage, subdominio, billing). Proveer rollback seguro ante fallos parciales.

### Personas

| Persona      | Descripción                                                  |
| ------------ | ------------------------------------------------------------ |
| Prospecto    | Usuario que completa el registro público                     |
| Global Admin | Opera lifecycle manual de tenants desde el dashboard central |

### User Stories

| ID     | Historia                                                                                                           |
| ------ | ------------------------------------------------------------------------------------------------------------------ |
| US-101 | Como Prospecto, quiero registrarme y que el tenant sea creado automáticamente con acceso inmediato.                |
| US-102 | Como Global Admin, quiero activar, suspender o archivar un tenant desde el dashboard.                              |
| US-103 | Como Global Admin, quiero eliminar permanentemente un tenant con purga de datos.                                   |
| US-104 | Como sistema, necesito que un provisioning fallido a mitad del proceso haga rollback sin dejar recursos huérfanos. |

### Acceptance Criteria

**US-101 — Onboarding automático**
- Provisioning completo (DB schema, subdominio reservado, welcome email, plan activo) en < 30s p95.
- Subdominio validado contra blacklist (palabras reservadas: `www`, `api`, `admin`, `central`, `app`) antes de reservar.
- Slug único: colisión retorna error `409` con sugerencias alternativas.
- Tenant en estado `provisioning` durante el proceso. Estado final: `active` o rollback a `failed` con log de causa.

**US-102 — Lifecycle manual**
- Suspensión: tenant pasa a `suspended` en < 5s. Acceso de usuarios tenant retorna `503` con mensaje de suspensión.
- Activación desde `suspended`: tenant operativo en < 10s.
- Archivado: datos preservados, acceso bloqueado. Estado `archived`. No facturable.

**US-103 — Eliminación**
- Hard delete disponible solo para Global Admin con confirmación explícita (escribir slug).
- Purga: DB schema, archivos en storage, subdominio liberado, billing cancelado.
- Purga completada en background job. Log de eliminación en `central_audit_logs` preservado permanentemente.

**US-104 — Rollback**
- Si cualquier paso del provisioning falla, estado revertido a `failed` y recursos parcialmente creados son limpiados.
- Recursos huérfanos verificados en job de reconciliación diario.

### Data Model

```sql
tenants (
  id              UUID PRIMARY KEY,
  slug            VARCHAR(63) UNIQUE NOT NULL,     -- subdominio
  name            VARCHAR(255) NOT NULL,
  status          ENUM('provisioning','active','suspended','archived','failed') NOT NULL,
  plan_id         UUID REFERENCES plans(id),
  provisioned_at  TIMESTAMP NULL,
  suspended_at    TIMESTAMP NULL,
  archived_at     TIMESTAMP NULL,
  created_at      TIMESTAMP NOT NULL,
  updated_at      TIMESTAMP NOT NULL,
  INDEX (status),
  INDEX (slug)
)

provisioning_logs (
  id          UUID PRIMARY KEY,
  tenant_id   UUID REFERENCES tenants(id),
  step        VARCHAR(100) NOT NULL,               -- 'db_schema', 'storage', 'subdomain', etc.
  status      ENUM('pending','completed','failed') NOT NULL,
  error       TEXT NULL,
  executed_at TIMESTAMP NOT NULL
)
```

### Events

```
TenantProvisioningStarted(tenant_id, slug, plan_id)
TenantProvisioningCompleted(tenant_id)
TenantProvisioningFailed(tenant_id, step, error)
TenantActivated(tenant_id)
TenantSuspended(tenant_id, reason)
TenantArchived(tenant_id)
TenantDeleted(tenant_id, deleted_by)
```

### API

| Método | Endpoint                                 | Descripción                               |
| ------ | ---------------------------------------- | ----------------------------------------- |
| POST   | `/central/tenants`                       | Iniciar provisioning (onboarding)         |
| GET    | `/central/tenants/{id}`                  | Consultar estado y detalles               |
| PATCH  | `/central/tenants/{id}/status`           | Cambiar status (activate/suspend/archive) |
| DELETE | `/central/tenants/{id}`                  | Hard delete con confirmación              |
| GET    | `/central/tenants/{id}/provisioning-log` | Log de pasos de provisioning              |

### Edge Cases

| Caso                  | Mitigación                                                                                |
| --------------------- | ----------------------------------------------------------------------------------------- |
| Slug duplicado        | Validación pre-insert + constraint UNIQUE. Sugerencias generadas en la respuesta de error |
| Provisioning parcial  | FSM con steps atómicos. Rollback handler por cada step. Job de reconciliación diario      |
| Storage huérfano      | Reconciliation job detecta buckets/directorios sin tenant activo y notifica               |
| Welcome email fallido | Reintentos (3x con backoff). Fallo no bloquea activación del tenant                       |

---

## 3. Billing

### Overview

Gestión de planes, suscripciones, cobros recurrentes, facturación y manejo de fallos de pago (dunning). Integración con Stripe (global) y dLocal (LATAM).

### Business Goal

Soportar modelos de suscripción con cobros recurrentes. Manejar fallos de pago con dunning automatizado. Generar facturas descargables.

### Personas

| Persona      | Descripción                                             |
| ------------ | ------------------------------------------------------- |
| Global Admin | Gestión de planes, revisión de suscripciones y facturas |
| Tenant Admin | Gestión de su propia suscripción y métodos de pago      |

### User Stories

| ID     | Historia                                                                                   |
| ------ | ------------------------------------------------------------------------------------------ |
| US-201 | Como Tenant Admin, quiero suscribirme a un plan y que el cobro se procese automáticamente. |
| US-202 | Como Tenant Admin, quiero ver y descargar mis facturas históricas.                         |
| US-203 | Como sistema, ante un fallo de pago necesito ejecutar dunning y notificar al tenant.       |
| US-204 | Como Global Admin, quiero ver el estado de suscripción de cualquier tenant.                |
| US-205 | Como Tenant Admin, quiero cambiar mi método de pago.                                       |

### Acceptance Criteria

**US-201 — Checkout y suscripción**
- [x] Checkout completado: tenant en plan activo en < 10s tras confirmación de pago.
- [x] Webhook de Stripe procesado con idempotencia: evento duplicado no crea suscripción duplicada. Clave de idempotencia: `stripe_event_id`.
- [x] Suscripción almacenada con `external_id` (Stripe subscription ID) para reconciliación.

**US-202 — Facturas**
- [x] Factura disponible en < 60s tras cierre del período de facturación (Sincronización implementada).
- [x] Descarga en PDF. Incluye: número de factura, período, líneas de concepto, total, estado.
- [x] Historial paginado, respuesta en p95 < 200ms.

**US-203 — Dunning**
- [x] Primer fallo: reintento automático en 3 días. Notificación por email al tenant.
- [x] Segundo fallo: reintento en 5 días. Segunda notificación con advertencia de suspensión.
- [x] Tercer fallo: tenant pasa a `suspended`. Email final con instrucciones de recuperación (Suspensión automática implementada).
- [x] Recovery: tenant paga deuda pendiente → estado `active` restaurado en < 5min.

**US-204 — Vista admin**
- [x] Lista de suscripciones con filtro por estado (`active`, `past_due`, `suspended`, `cancelled`).
- [x] Respuesta paginada en p95 < 300ms.

**US-205 — Método de pago**
- [x] Actualización procesada vía Stripe Setup Intent. Nunca almacenar datos de tarjeta en la DB propia.
- [x] Método actualizado visible en < 30s.

### Data Model

```sql
plans ( [x] Implementado )
subscriptions ( [x] Extendido con gateway/external_id )
invoices ( [x] Implementado )
payment_gateway_events ( [x] Implementado para idempotencia )
```

### Events

```
SubscriptionCreated(tenant_id, subscription_id, plan_id, gateway)
SubscriptionUpdated(tenant_id, subscription_id, old_plan_id, new_plan_id)
SubscriptionCancelled(tenant_id, subscription_id, reason)
PaymentSucceeded(tenant_id, invoice_id, amount, currency)
PaymentFailed(tenant_id, invoice_id, attempt_number)
TenantSuspendedByDunning(tenant_id, invoice_id)
TenantReactivatedAfterPayment(tenant_id, invoice_id)
```

### API

| Método | Endpoint                                     | Descripción                                      |
| ------ | -------------------------------------------- | ------------------------------------------------ |
| GET    | `/central/plans`                             | Listar planes activos                            |
| POST   | `/central/billing/checkout`                  | Iniciar checkout (Setup Intent / Payment Intent) |
| GET    | `/central/billing/subscriptions/{tenant_id}` | Estado de suscripción                            |
| POST   | `/central/billing/subscriptions/{id}/cancel` | Cancelar suscripción                             |
| GET    | `/central/billing/invoices`                  | Listar facturas del tenant                       |
| GET    | `/central/billing/invoices/{id}/pdf`         | Descargar PDF                                    |
| POST   | `/central/webhooks/stripe`                   | Handler de webhooks Stripe                       |
| POST   | `/central/webhooks/dlocal`                   | Handler de webhooks dLocal                       |

### Edge Cases

| Caso                         | Mitigación                                                                  |
| ---------------------------- | --------------------------------------------------------------------------- |
| Webhook duplicado            | `payment_gateway_events.gateway_event_id` UNIQUE. Procesamiento idempotente |
| Gateway timeout              | Webhook como source of truth. Nunca asumir éxito por timeout                |
| Suscripción desincronizada   | Job de reconciliación diaria contra Stripe API                              |
| Fallo durante cambio de plan | Transacción DB atómica. Stripe es fuente de verdad                          |

---

## 4. Support

### Overview

Herramientas para que el equipo central asista a tenants: impersonation auditada, notas internas y broadcasts. Sin overrides de quota arbitrarios.

### Business Goal

Permitir asistencia segura y rastreable a tenants. Toda acción de soporte debe ser auditable y reversible.

### Personas

| Persona          | Descripción                                     |
| ---------------- | ----------------------------------------------- |
| Operador Central | Ejecuta impersonation y agrega notas de soporte |
| Global Admin     | Aprueba impersonation y envía broadcasts        |

### User Stories

| ID     | Historia                                                                                                 |
| ------ | -------------------------------------------------------------------------------------------------------- |
| US-301 | Como Operador, quiero hacer impersonation de un tenant con razón registrada para diagnosticar problemas. |
| US-302 | Como Global Admin, quiero enviar un broadcast a todos o a un subconjunto de tenants.                     |
| US-303 | Como Operador, quiero agregar notas internas a la cuenta de un tenant.                                   |

### Acceptance Criteria

**US-301 — Impersonation**
- [x] Impersonation requiere campo `reason` obligatorio (mínimo 20 caracteres). Request sin reason retorna `400`.
- [x] Sesión de impersonation tiene TTL máximo de 2 horas. Expiración automática.
- [x] Toda acción durante impersonation registrada en `central_audit_logs` con `impersonated_by` en metadata.
- [x] Tenant recibe notificación por email tras finalizar sesión de impersonation (post-session, no en tiempo real para no alertar innecesariamente).

**US-302 — Broadcast**
- [x] Broadcast enviado a tenants filtrados por: `all`, `plan_id`, `status`.
- [x] Entrega vía email y/o banner en-app (configurable por broadcast).
- [x] Job de broadcast en queue separada. No bloquea requests normales.
- [x] Log de broadcast con: creador, timestamp, filtro, total de destinatarios.

**US-303 — Notas de soporte**
- [x] Nota vinculada a `tenant_id` y `central_user_id` (autor).
- [x] Notas visibles solo para operadores centrales — nunca al tenant.
- [x] Sin eliminación de notas (append-only para integridad de historial).

### Data Model

```sql
support_sessions ( [x] Implementado )
support_notes ( [x] Implementado )
broadcasts ( [x] Implementado )
```

### Events

```
SupportSessionStarted(operator_id, tenant_id, reason)
SupportSessionEnded(operator_id, tenant_id, duration_seconds)
BroadcastSent(broadcast_id, created_by, recipient_count)
```

### Edge Cases

| Caso                                   | Mitigación                                                                                           |
| -------------------------------------- | ---------------------------------------------------------------------------------------------------- |
| Impersonation abuse                    | TTL 2h + audit log inmutable + notificación post-sesión al tenant                                    |
| Broadcast a todos los tenants con typo | Preview con conteo de destinatarios antes de enviar. Confirmación requerida para `filter_type = all` |

---

## 5. Infrastructure

### Overview

Telemetría, colas, cache y observabilidad del contexto central. Foco en isolación de recursos entre tenants y detección de degradación.

### Business Goal

Mantener operabilidad del sistema. Prevenir que un tenant impacte a otros (noisy neighbor). Detectar y alertar sobre degradación antes de que afecte SLAs.

### Functional Requirements

**Queue isolation**
- Cada tenant tiene su propia cola de jobs (prefijo `tenant.{slug}.`).
- Jobs de tenants en `past_due` o `suspended` en cola de baja prioridad.
- Horizon para monitoreo y gestión de colas.

**Rate limiting**
- Rate limits aplicados por tenant y por plan desde Redis.
- Límites configurables por plan en `plans.features` (JSONB).
- Respuesta `429` con header `Retry-After` cuando se excede el límite.

**Cache**
- Cache de sesiones y cuotas en Redis con TTL explícito.
- Cache poisoning mitigado: validación de datos al leer de cache antes de usar.
- Invalidación explícita al cambiar plan o status de tenant.

**Observability**
- Health endpoint `/central/health` con estado de dependencias (DB, Redis, Queue).
- Logging estructurado (JSON) con `tenant_id` en contexto cuando aplica.
- Alertas en: error rate > 1% en 5min, queue depth > 1000 jobs, Redis memory > 80%.

### Acceptance Criteria

| Requisito                                            | Threshold                                                   |
| ---------------------------------------------------- | ----------------------------------------------------------- |
| Rate limit response con `429`                        | < 5ms overhead sobre request normal                         |
| Health check response                                | < 100ms                                                     |
| Queue isolation: job de tenant A no impacta tenant B | Jobs en colas separadas verificado por tests de integración |
| Cache miss no degrada p95                            | p95 con cache miss <= p95 sin cache + 50ms                  |
| Alerta por queue depth > 1000                        | Notificación en < 2min                                      |

### Edge Cases

| Caso                | Mitigación                                                                                                           |
| ------------------- | -------------------------------------------------------------------------------------------------------------------- |
| Redis outage        | Rate limits y cuotas fallan open (permiten tráfico) con log de warning. No fallan closed para evitar outage completo |
| Queue contaminación | Colas por tenant. Job sin `tenant_id` va a cola `default` — nunca a cola de tenant                                   |
| Noisy tenant        | Throttling por tenant desde Redis. Queue de baja prioridad para tenants problemáticos                                |

---

## 6. Features

### Overview
Gestión granular de funcionalidades y acceso a módulos. Permite definir qué características están disponibles por plan y aplicar excepciones (overrides) por tenant de forma dinámica y desacoplada del billing.

### Business Goal
Habilitar o deshabilitar funcionalidades sin necesidad de despliegues de código. Permitir ventas personalizadas, accesos beta, periodos de prueba de features específicas y control de acceso dinámico por soporte.

### Personas
| Persona      | Descripción                                                                         |
| ------------ | ----------------------------------------------------------------------------------- |
| Global Admin | Define el catálogo de features, las asigna a planes y gestiona overrides por tenant |

### User Stories
| ID     | Historia                                                                                            |
| ------ | --------------------------------------------------------------------------------------------------- |
| US-401 | Como Global Admin, quiero definir una nueva funcionalidad en el catálogo global.                    |
| US-402 | Como Global Admin, quiero asignar funcionalidades a un plan específico.                             |
| US-403 | Como Global Admin, quiero conceder o denegar una funcionalidad a un tenant (override).              |
| US-404 | Como Desarrollador, quiero verificar el acceso a una feature mediante una API simple (Redis-first). |

### Acceptance Criteria

**US-401 — Catálogo global**
- Feature creada con key única (formato `modulo.accion`).
- Atributo `is_active` controla la disponibilidad global inmediata.

**US-402 — Configuración de Plan**
- Asignación M:N entre planes y features.
- Cambios reflejados en tenants del plan tras invalidación de caché automático (< 60s).

**US-403 — Overrides por Tenant**
- Soporta tipo `allow` (concede) y `deny` (prohíbe).
- Soporta expiración (`expires_at`). Al expirar, la resolución vuelve a la base del plan.
- Override registrado con motivo (`reason`) para auditoría y `created_by`.

**US-404 — Resolución de Features**
- Resolución jerárquica: Override (Deny > Allow) -> Plan Base.
- Rendimiento: Resolución vía Redis-first cache en < 10ms.
- API fluida: `tenant()->hasFeature('key')`.

### Data Model

```sql
features (
  id          UUID PRIMARY KEY,
  key         VARCHAR(100) UNIQUE NOT NULL,        -- e.g., 'crm.pipeline'
  name        VARCHAR(255) NOT NULL,
  description TEXT,
  module      VARCHAR(100),
  is_active   BOOLEAN DEFAULT TRUE,
  created_at  TIMESTAMP NOT NULL
)

plan_features (
  plan_id    UUID REFERENCES plans(id),
  feature_id UUID REFERENCES features(id),
  PRIMARY KEY (plan_id, feature_id)
)

tenant_feature_overrides (
  id          UUID PRIMARY KEY,
  tenant_id   UUID REFERENCES tenants(id),
  feature_id  UUID REFERENCES features(id),
  type        ENUM('allow', 'deny') NOT NULL,
  reason      TEXT,
  expires_at  TIMESTAMP NULL,
  created_by  UUID REFERENCES central_users(id),
  created_at  TIMESTAMP NOT NULL,
  UNIQUE (tenant_id, feature_id)
)
```

### Events
```
FeatureCreated(feature_id, key)
FeatureAssignedToPlan(plan_id, feature_id)
TenantFeatureOverrideCreated(tenant_id, feature_id, type)
TenantFeatureOverrideExpired(tenant_id, feature_id)
FeatureCacheInvalidated(tenant_id)
```

### API
| Método | Endpoint                                  | Descripción                       |
| ------ | ----------------------------------------- | --------------------------------- |
| GET    | `/central/features`                       | Listar catálogo global            |
| POST   | `/central/features`                       | Crear nueva feature               |
| POST   | `/central/plans/{id}/features`            | Asignar feature a plan            |
| POST   | `/central/tenants/{id}/features/override` | Aplicar override a tenant         |
| GET    | `/central/tenants/{id}/features`          | Ver features efectivas del tenant |

### Edge Cases
| Caso                         | Mitigación                                                          |
| ---------------------------- | ------------------------------------------------------------------- |
| Feature retirada globalmente | `is_active = false` invalida accesos incluso con overrides `allow`. |
| Cache poisoning              | Priming valida integridad del JSON en Redis antes de usarlo.        |
| Race condition en downgrade  | Purga de caché inmediata al cambiar de plan para bloquear accesos.  |

---

## 7. Landing Pública (Comercial)

### Overview

Página pública comercial destinada a convertir visitantes en prospectos y clientes. Debe comunicar propuesta de valor, planes, beneficios clave, confianza (testimonios/logos) y llamadas a la acción claras (signup/contact). La landing será la entrada pública al producto y el punto de captación principal para marketing.

### Business Goal

- Atraer y convertir tráfico orgánico y pagado en registros de prueba y leads cualificados.
- Comunicar diferenciadores clave frente a competidores SaaS locales e internacionales.

### Público Objetivo

- Dueños de producto / CTOs de pymes y startups.
- Equipos de soporte/ops que buscan multitenancy y control centralizado.
- Resellers/partners interesados en desplegar instancias para clientes.

### Contenido Requerido

- **Hero**: título claro, subtítulo de 1 línea y CTA principal (`Comenzar prueba gratuita`).
- **Beneficios clave**: 3–5 bullets con pain→solution.
- **Planes y precios**: tabla resumida con CTA por plan.
- **Casos de uso / features**: highlights vinculados a `Features` del PRD.
- **Testimonios / logos**: validación social.
- **FAQ**: preguntas frecuentes sobre seguridad, datos y facturación.
- **Footer legal**: enlaces a `Términos`, `Privacidad`, `Contacto` y `Status`.

### Requisitos Funcionales

- Formulario de captura de leads con campos: `nombre`, `email`, `empresa`, `roles`, `plan interés`.
- Integración con CRM/Marketing (webhook/segment) y con la cola de emails para nurturing.
- Soporte para registro directo de tenant (onboarding) con validación de slug y bloqueo de palabras reservadas.
- Soporte i18n (ES/EN) y detección automática de idioma con fallback a ES.
- Medición y tracking: Google Analytics / GA4, UTM parsing, conversión por CTA y eventos en el backend (`LandingLeadCaptured`).

### SEO y Performance

- Meta tags dinámicos y Open Graph para compartir.
- Sitemap actualizado y robots.txt configurado.
- Tiempo de carga objetivo: First Contentful Paint < 1s en conexión 3G lenta (optimización de assets y lazy-loading).
- Rendimiento móvil prioritario y accesibilidad WCAG AA.

### Seguridad y Compliance

- ReCaptcha (v3 o equivalente) en formularios para prevenir spam.
- Consentimiento GDPR en captura de datos, checkbox explícito y registro del consent_id.
- TLS obligatorio, CSP básico y encabezados de seguridad por defecto.

### Acceptance Criteria

- Hero + CTA visibles y testeables (A/B) en versión móvil y desktop.
- Formulario almacena leads en DB y dispara evento `LandingLeadCaptured` con payload mínimo `{email,name,company,locale}`.
- Registro directo de tenant desde landing valida `slug` contra blacklist y crea provisioning job en queue en < 30s p95.
- Páginas indexables: tags SEO presentes y sitemap listado.

### Métricas & SLAs

- Conversion rate (visit→lead) objetivo: > 3% primer trimestre.
- Tiempo de carga p95: < 2s en desktop, <3s en móvil.
- Disponibilidad de página: 99.9% mensual.

### Implementación / Notas Técnicas

- Entrega como contenido estático cacheado por CDN (Cloudflare o equivalente) con fallback a SSR para contenido dinámico (prices, plan availability).
- Assets optimizados (webp, critical CSS inlining) y prerender de rutas principales.
- Endpoints para capture de leads protegidos por rate-limits y captchas.
- Soporte para experimentos A/B y feature flags (usar `features` module para toggles).

## Links

- Diagramas: `docs/*.mermaid`
- ADRs: `docs/adr/`

