# PRD Tenant

**Metadata**

| Campo    | Valor                                                                                     |
| -------- | ----------------------------------------------------------------------------------------- |
| Owner    | TBD                                                                                       |
| Created  | 2026-06-01                                                                                |
| Updated  | 2026-06-02                                                                                |
| Status   | Draft v2                                                                                  |
| Contexto | Bounded context Tenant: identidad, settings, auditoría, cuotas e integraciones del tenant |

---

## Architecture Constraints

- Single PostgreSQL Database (as mandated in ARCHITECTURE.md)
- Tenant isolation via PostgreSQL Row Level Security (RLS) + Eloquent Scopes
- tenant_id (UUID) mandatory in all tenant-scoped tables
- Auth de tenant completamente separado de auth central
- Cuotas validadas contra Redis — DB como fallback, no como fuente primaria
- Audit logs append-only en la tabla audit_logs (tenant-aware)

---

## 1. Identity

### Overview

IAM del tenant: autenticación, roles, permisos, invitaciones y claves API. Control de acceso granular dentro del contexto del tenant.

### Business Goal

Proveer control de acceso por tenant con roles configurables. Soportar invitaciones con ciclo de vida controlado. Claves API para integración programática.

### Personas

| Persona       | Descripción                                             |
| ------------- | ------------------------------------------------------- |
| Tenant Admin  | Gestiona usuarios, roles y permisos dentro de su tenant |
| Tenant Member | Usuario regular con permisos según su rol               |
| Developer     | Genera y gestiona claves API para integraciones         |

### User Stories

| ID      | Historia                                                                           |
| ------- | ---------------------------------------------------------------------------------- |
| US-T101 | Como Tenant Admin, quiero invitar usuarios por email con un rol pre-asignado.      |
| US-T102 | Como Tenant Admin, quiero crear roles personalizados con permisos específicos.     |
| US-T103 | Como Tenant Admin, quiero revocar acceso de un usuario inmediatamente.             |
| US-T104 | Como Developer, quiero generar claves API con scopes limitados para integraciones. |
| US-T105 | Como Tenant Admin, quiero requerir MFA para todos los usuarios del tenant.         |

### Acceptance Criteria

**US-T101 — Invitaciones**
- Invite enviado por email en < 30s con token único.
- Token de invite expira en 48h. Expirado retorna `410` con opción de reenvío.
- Invite aceptado crea usuario con rol pre-asignado. Usuario existente (mismo email) es vinculado al tenant con el rol indicado.
- Máximo de invites pendientes simultáneos: configurable por plan (default: 10).

**US-T102 — Roles y permisos**
- [x] Roles predefinidos no eliminables: `admin`, `member`. Protegidos a nivel de modelo.
- [x] Roles personalizados: crear, editar, eliminar. Eliminación de rol con usuarios asignados retorna `409 Conflict` — debe reasignarse primero.
- [x] Permisos por resource:action (e.g. `team:read`, `roles:manage`).
- [x] Cambio de permisos efectivo en < 5s (flushing explícito de cache de Spatie).

**US-T103 — Revocación de acceso**
- Usuario revocado no puede autenticarse en el tenant. Sesiones activas invalidadas en < 60s.
- Datos del usuario preservados en DB (soft delete). No reasignable a otro tenant con el mismo email sin flujo de reactivación.

**US-T104 — API Keys**
- Key generada como `tnt_{random_32_bytes_hex}`. Almacenada como hash SHA-256. Mostrada en texto plano solo una vez al creador.
- Scopes configurables al crear la key. Sin opción de ampliar scopes post-creación (crear nueva key).
- Key revocable en cualquier momento. Revocación efectiva en < 5s.
- Máximo de keys activas por tenant: 10 (configurable por plan).

**US-T105 — MFA obligatorio**
- Si `mfa_required = true` en settings del tenant: usuario sin MFA enrollado es redirigido a enrollment al login.
- Acceso bloqueado hasta completar enrollment. No hay bypass.
- Enforcement efectivo en la siguiente sesión tras activar el setting.

### Data Model

```sql
-- En schema del tenant (tenant_{slug})

users (
  id              UUID PRIMARY KEY,
  email           VARCHAR(255) UNIQUE NOT NULL,
  name            VARCHAR(255) NOT NULL,
  password_hash   VARCHAR(255) NOT NULL,
  role_id         UUID REFERENCES roles(id),
  is_active       BOOLEAN DEFAULT TRUE,
  mfa_enrolled    BOOLEAN DEFAULT FALSE,
  last_login_at   TIMESTAMP NULL,
  created_at      TIMESTAMP NOT NULL,
  updated_at      TIMESTAMP NOT NULL
)

roles (
  id           UUID PRIMARY KEY,
  tenant_id    UUID REFERENCES tenants(id),
  name         VARCHAR(100) NOT NULL,
  guard_name   VARCHAR(100) NOT NULL,
  is_system    BOOLEAN DEFAULT FALSE,              -- admin, member no eliminables
  created_at   TIMESTAMP NOT NULL,
  UNIQUE (tenant_id, name, guard_name)
)

permissions (
  id           UUID PRIMARY KEY,
  name         VARCHAR(100) NOT NULL,
  guard_name   VARCHAR(100) NOT NULL,
  created_at   TIMESTAMP NOT NULL,
  UNIQUE (name, guard_name)
)

invitations (
  id           UUID PRIMARY KEY,
  email        VARCHAR(255) NOT NULL,
  role_id      UUID REFERENCES roles(id),
  token_hash   VARCHAR(255) NOT NULL,
  invited_by   UUID REFERENCES users(id),
  accepted_at  TIMESTAMP NULL,
  expires_at   TIMESTAMP NOT NULL,
  created_at   TIMESTAMP NOT NULL,
  INDEX (token_hash),
  INDEX (email, accepted_at)
)

api_keys (
  id           UUID PRIMARY KEY,
  name         VARCHAR(100) NOT NULL,
  key_hash     VARCHAR(255) UNIQUE NOT NULL,       -- SHA-256
  scopes       JSONB NOT NULL,
  created_by   UUID REFERENCES users(id),
  last_used_at TIMESTAMP NULL,
  revoked_at   TIMESTAMP NULL,
  created_at   TIMESTAMP NOT NULL
)

user_mfa (
  id                  UUID PRIMARY KEY,
  user_id             UUID REFERENCES users(id) UNIQUE,
  method              ENUM('totp') NOT NULL,
  secret              TEXT NOT NULL,               -- cifrado en reposo
  recovery_codes_hash TEXT NOT NULL,
  enrolled_at         TIMESTAMP NOT NULL
)
```

### Events

```
TenantUserInvited(tenant_id, inviter_id, email, role_id)
TenantUserJoined(tenant_id, user_id, via_invite_id)
TenantUserRevoked(tenant_id, user_id, revoked_by)
TenantRoleCreated(tenant_id, role_id)
TenantRoleUpdated(tenant_id, role_id, changed_permissions)
TenantApiKeyCreated(tenant_id, key_id, scopes)
TenantApiKeyRevoked(tenant_id, key_id)
```

### API

| Método | Endpoint                           | Descripción                 |
| ------ | ---------------------------------- | --------------------------- |
| POST   | `/auth/login`                      | Login de usuario del tenant |
| POST   | `/auth/logout`                     | Cerrar sesión               |
| POST   | `/auth/2fa/verify`                 | Verificar TOTP              |
| POST   | `/team/invitations`                | Enviar invite               |
| GET    | `/team/invitations`                | Listar invites pendientes   |
| DELETE | `/team/invitations/{id}`           | Cancelar invite             |
| POST   | `/team/invitations/{token}/accept` | Aceptar invite              |
| GET    | `/team/members`                    | Listar miembros             |
| PATCH  | `/team/members/{id}`               | Cambiar rol                 |
| DELETE | `/team/members/{id}`               | Revocar acceso              |
| GET    | `/settings/roles`                  | Listar roles                |
| POST   | `/settings/roles`                  | Crear rol                   |
| PATCH  | `/settings/roles/{id}`             | Editar permisos             |
| DELETE | `/settings/roles/{id}`             | Eliminar rol                |
| GET    | `/settings/api-keys`               | Listar API keys             |
| POST   | `/settings/api-keys`               | Crear API key               |
| DELETE | `/settings/api-keys/{id}`          | Revocar API key             |

### Edge Cases

| Caso                                    | Mitigación                                                                                      |
| --------------------------------------- | ----------------------------------------------------------------------------------------------- |
| Invite a email existente en otro tenant | Permitido — usuario puede pertenecer a múltiples tenants con roles distintos                    |
| Leak de invite token                    | TTL 48h + one-time use. Token comprometido: admin puede cancelar invite                         |
| Cross-tenant auth                       | Middleware valida que el usuario pertenece al tenant del subdominio activo. 404 si no pertenece |
| Rol eliminado con usuarios activos      | `409` con lista de usuarios afectados. Admin debe reasignar antes                               |

---

## 2. Settings

### Overview

Configuración por tenant: branding, localización y configuración de email saliente. Sin custom domains en v1 — complejidad SSL/DNS no justificada sin demanda validada.

### Business Goal

Permitir personalización básica del tenant (nombre, logo, idioma, zona horaria, email). Suficiente para white-label funcional sin infraestructura DNS propia.

### Personas

| Persona      | Descripción                                  |
| ------------ | -------------------------------------------- |
| Tenant Admin | Configura branding y preferencias del tenant |

### User Stories

| ID      | Historia                                                                          |
| ------- | --------------------------------------------------------------------------------- |
| US-T201 | Como Tenant Admin, quiero configurar el nombre, logo y colores de mi tenant.      |
| US-T202 | Como Tenant Admin, quiero configurar la zona horaria, idioma y moneda del tenant. |
| US-T203 | Como Tenant Admin, quiero configurar un SMTP personalizado para emails salientes. |

### Acceptance Criteria

**US-T201 — Branding**
- [x] Logo: JPG/PNG, máximo 2MB. Almacenado en storage isolado (`tenant_{id}/branding`).
- [x] Color primario: hex válido (#RRGGBB). Aplicado dinámicamente en UI.
- [x] Cambios de branding reflejados en la UI en < 5s (flushing de cache e invalidación Livewire).

**US-T202 — Localización**
- [x] Zona horaria: lista cerrada IANA.
- [x] Idioma: soporte para `es`, `en`.
- [x] Moneda: códigos ISO 4217 con advertencia para suscripciones activas.

**US-T203 — SMTP**
- [x] Credenciales SMTP almacenadas cifradas (AES-256-GCM).
- [x] Test de conexión con envío de email real. Resultado instantáneo.
- [x] Fallo de SMTP: flag `smtp_verified` controla el estado de uso. Fallback global activo por diseño.

### Data Model

```sql
-- En schema del tenant

tenant_settings (
  id               UUID PRIMARY KEY,
  tenant_id        UUID NOT NULL UNIQUE,           -- referencia a central.tenants
  name             VARCHAR(255) NOT NULL,
  logo_path        TEXT NULL,
  primary_color    VARCHAR(7) NULL,                -- #RRGGBB
  timezone         VARCHAR(100) NOT NULL DEFAULT 'America/Panama',
  locale           VARCHAR(10) NOT NULL DEFAULT 'es',
  currency         VARCHAR(3) NOT NULL DEFAULT 'USD',
  mfa_required     BOOLEAN DEFAULT FALSE,
  smtp_host        VARCHAR(255) NULL,
  smtp_port        INTEGER NULL,
  smtp_user        VARCHAR(255) NULL,
  smtp_password    TEXT NULL,                      -- cifrado
  smtp_from_email  VARCHAR(255) NULL,
  smtp_from_name   VARCHAR(255) NULL,
  smtp_verified    BOOLEAN DEFAULT FALSE,
  updated_at       TIMESTAMP NOT NULL
)
```

### Events

```
TenantSettingsUpdated(tenant_id, changed_fields[])
TenantSmtpConfigured(tenant_id, from_email)
TenantMfaRequirementChanged(tenant_id, mfa_required)
```

### Edge Cases

| Caso                                    | Mitigación                                                                                      |
| --------------------------------------- | ----------------------------------------------------------------------------------------------- |
| SMTP inválido guardado                  | Test obligatorio antes de guardar (o guardar + flag `smtp_verified = false` hasta test exitoso) |
| Logo demasiado grande                   | Validación en frontend + backend. Rechazo con mensaje claro antes de upload                     |
| Cambio de moneda con suscripción activa | Warning explícito: moneda de billing es la de la suscripción en Stripe, no este setting         |

---

## 3. Audit

### Overview

Registro inmutable de acciones dentro del tenant. Exportable. Base para compliance y recuperación de errores operacionales.

### Business Goal

Proveer trazabilidad de acciones de usuarios y sistema dentro del tenant. Permitir exportación para compliance o diagnóstico. Sin GDPR compliance en v1 — añadir cuando haya clientes en jurisdicción UE.

### Personas

| Persona      | Descripción                          |
| ------------ | ------------------------------------ |
| Tenant Admin | Consulta y exporta logs de auditoría |

### User Stories

| ID      | Historia                                                                                           |
| ------- | -------------------------------------------------------------------------------------------------- |
| US-T301 | Como Tenant Admin, quiero ver un log de todas las acciones de usuarios en mi tenant.               |
| US-T302 | Como Tenant Admin, quiero exportar el audit log en CSV para un rango de fechas.                    |
| US-T303 | Como sistema, necesito registrar automáticamente cambios críticos (users, roles, settings, datos). |

### Acceptance Criteria

**US-T301 — Consulta de logs**
- [x] Lista paginada (50 por página) con filtros: `user_id`, `action`, `date_range`.
- [x] Respuesta en p95 < 200ms con índices en PostgreSQL.
- [x] Cada entrada muestra: usuario, acción, recurso afectado, IP, timestamp.

**US-T302 — Exportación**
- [x] Export CSV de hasta 90 días de datos con validación de rango.
- [x] Export procesado en background job (`ExportAuditLogsJob`).
- [x] Notificación por email con enlace firmado seguro (24h de validez).

**US-T303 — Registro automático**
- [x] Acciones auditadas automáticamente: login/logout, IAM, settings.
- [x] Registro desacoplado via Event Subscribers.
- [x] Logs son append-only. Sin endpoints de modificación/eliminación.

### Data Model

```sql
-- En schema del tenant

audit_logs (
  id          UUID PRIMARY KEY,
  user_id     UUID NULL,                           -- NULL para acciones de sistema
  action      VARCHAR(100) NOT NULL,               -- 'user.created', 'role.updated', etc.
  resource    VARCHAR(100) NULL,                   -- 'users', 'roles', 'settings'
  resource_id UUID NULL,
  metadata    JSONB NULL,                          -- before/after state para cambios críticos
  ip          INET NULL,
  created_at  TIMESTAMP NOT NULL,
  INDEX (user_id, created_at),
  INDEX (created_at),
  INDEX (action, created_at)
)
```

### Edge Cases

| Caso                                   | Mitigación                                                                                   |
| -------------------------------------- | -------------------------------------------------------------------------------------------- |
| Export grande (90 días, tenant activo) | Job asíncrono con progreso. Límite de 90 días. Stream a S3/storage antes de notificar        |
| Tampering de logs                      | Append-only en DB. Sin endpoint de DELETE. Permisos de DB restrictivos para tabla audit_logs |

---

## 4. Quotas

### Overview

Control y enforcement de límites de uso por tenant según su plan. Notificaciones antes de alcanzar el límite. Sin overage billing en v1.

### Business Goal

Prevenir abuso y uso que exceda el plan contratado. Notificar al tenant antes de que se bloquee. Proveer visibilidad de consumo en tiempo real.

### Personas

| Persona      | Descripción                              |
| ------------ | ---------------------------------------- |
| Tenant Admin | Monitorea uso y recibe alertas de cuota  |
| Sistema      | Enforcea límites antes de crear recursos |

### User Stories

| ID      | Historia                                                                                   |
| ------- | ------------------------------------------------------------------------------------------ |
| US-T401 | Como sistema, necesito bloquear operaciones cuando el tenant alcanza su límite de plan.    |
| US-T402 | Como Tenant Admin, quiero ver mi consumo actual vs. mi límite en el dashboard.             |
| US-T403 | Como Tenant Admin, quiero recibir notificación cuando llegue al 80% y al 100% de mi cuota. |

### Acceptance Criteria

**US-T401 — Enforcement**
- Check de cuota ejecutado en Redis antes de crear recurso. Si límite alcanzado: `429` con mensaje descriptivo y el límite del plan.
- Check de cuota: p95 < 5ms (Redis lookup, no query a DB).
- Fallback si Redis no disponible: permitir operación con log de warning (fail open — ver Infrastructure de Central).

**US-T402 — Dashboard de uso**
- Consumo actual vs. límite por dimensión: usuarios, API calls/mes, storage.
- Datos actualizados en tiempo real (Redis counter). Máximo lag: 30s.
- Respuesta del endpoint de uso en p95 < 100ms.

**US-T403 — Alertas**
- Notificación por email al admin del tenant al cruzar 80% de cualquier cuota.
- Segunda notificación al cruzar 100% con instrucciones de upgrade.
- Notificaciones no repetitivas: máximo 1 email por umbral por período de facturación.

### Data Model

```sql
-- En Redis (no en DB — source of truth para enforcement)
-- Key: quota:{tenant_id}:{metric}
-- Value: integer counter
-- TTL: reset mensual via scheduled job

-- En DB (para histórico y reconciliación)
-- En schema central:

quota_snapshots (
  id          UUID PRIMARY KEY,
  tenant_id   UUID REFERENCES tenants(id),
  metric      VARCHAR(100) NOT NULL,               -- 'users', 'api_calls', 'storage_bytes'
  value       BIGINT NOT NULL,
  period      VARCHAR(7) NOT NULL,                 -- '2026-06' (YYYY-MM)
  captured_at TIMESTAMP NOT NULL,
  INDEX (tenant_id, metric, period)
)
```

### Edge Cases

| Caso                            | Mitigación                                                                          |
| ------------------------------- | ----------------------------------------------------------------------------------- |
| Race condition en límite        | Redis INCR atómico. Sin race condition por diseño                                   |
| Quota desync (Redis vs DB)      | Job de reconciliación diario compara Redis counter vs DB count real                 |
| Stale cache tras cambio de plan | Invalidación explícita de Redis key al cambiar plan. Nuevo límite efectivo en < 30s |

---

## 5. Integrations

### Overview

Webhooks salientes para notificar sistemas externos sobre eventos del tenant. Sin integraciones inbound en v1.

### Business Goal

Permitir que sistemas externos del cliente reaccionen a eventos del tenant. Base para automatizaciones sin construir integraciones específicas.

### Personas

| Persona      | Descripción                             |
| ------------ | --------------------------------------- |
| Developer    | Configura webhooks y monitorea entregas |
| Tenant Admin | Habilita/deshabilita integraciones      |

### User Stories

| ID      | Historia                                                                                         |
| ------- | ------------------------------------------------------------------------------------------------ |
| US-T501 | Como Developer, quiero registrar un endpoint para recibir webhooks de eventos del tenant.        |
| US-T502 | Como Developer, quiero ver el historial de entregas de webhooks y reintentar fallidos.           |
| US-T503 | Como sistema, los webhooks deben ser firmados para que el receptor pueda verificar autenticidad. |

### Acceptance Criteria

**US-T501 — Registro de webhooks**
- Endpoint registrado con: URL (HTTPS obligatorio), lista de eventos suscritos, estado (active/inactive).
- Máximo de endpoints por tenant: 5 (configurable por plan).
- Al guardar: test de conectividad enviando evento `webhook.test`. Sin bloqueo en caso de fallo — solo warning.

**US-T502 — Historial y reintentos**
- Historial de últimas 200 entregas por endpoint. Incluye: evento, timestamp, HTTP status de respuesta, latencia.
- Reintento manual disponible para cualquier entrega con status != 2xx.
- Reintento automático: 3 intentos con backoff exponencial (5min, 15min, 60min). Tras 3 fallos: endpoint marcado `degraded`. Tras 10 fallos consecutivos: endpoint deshabilitado con notificación al admin.

**US-T503 — Firma de webhooks**
- Cada request firmado con HMAC-SHA256 del payload usando `webhook_secret` del endpoint.
- Firma en header `X-Webhook-Signature: sha256={hex_digest}`.
- Secret visible solo al momento de creación. Rotable con nuevo secret. Período de gracia de 24h con doble firma durante rotación.

### Data Model

```sql
-- En schema del tenant

webhook_endpoints (
  id              UUID PRIMARY KEY,
  url             TEXT NOT NULL,                   -- HTTPS obligatorio
  secret_hash     TEXT NOT NULL,                   -- para verificación, no para reenvío
  secret_encrypted TEXT NOT NULL,                  -- para firmar salientes
  events          JSONB NOT NULL,                  -- ['order.created', 'user.invited', ...]
  status          ENUM('active','inactive','degraded') NOT NULL DEFAULT 'active',
  consecutive_failures INTEGER DEFAULT 0,
  created_at      TIMESTAMP NOT NULL,
  updated_at      TIMESTAMP NOT NULL
)

webhook_deliveries (
  id              UUID PRIMARY KEY,
  endpoint_id     UUID REFERENCES webhook_endpoints(id),
  event_type      VARCHAR(100) NOT NULL,
  payload         JSONB NOT NULL,
  attempt         INTEGER NOT NULL DEFAULT 1,
  http_status     INTEGER NULL,
  response_time_ms INTEGER NULL,
  error           TEXT NULL,
  delivered_at    TIMESTAMP NULL,
  created_at      TIMESTAMP NOT NULL,
  INDEX (endpoint_id, created_at)
)
```

### Events (emitidos por el tenant hacia endpoints externos)

```
-- Eventos del sistema que se entregan a los webhooks registrados:
user.invited
user.joined
user.revoked
order.created          -- ejemplo de evento de dominio (adaptar al producto)
settings.updated
subscription.changed
```

### Edge Cases

| Caso                 | Mitigación                                                                                                                |
| -------------------- | ------------------------------------------------------------------------------------------------------------------------- |
| Endpoint no responde | Backoff + deshabilitación tras 10 fallos consecutivos + notificación al admin                                             |
| Replay attack        | Incluir `timestamp` y `event_id` en payload. Receptor puede implementar deduplicación por `event_id`                      |
| Entrega duplicada    | `webhook_deliveries` guarda `event_id` + `endpoint_id`. Entrega duplicada detectable por el receptor con `event_id` único |

---

## Links

- Diagramas: `docs/*.mermaid`
- ADRs: `docs/adr/`