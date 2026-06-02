# PRD Central

**Metadata**

- Owner: TBD
- Created: 2026-06-01
- Status: Draft

## Overview

Resumen ejecutivo del bounded context Central: autenticación global, provisioning de tenants, facturación, soporte e infraestructura compartida.

## Architecture Constraints

- Single-DB
- Modular Monolith
- Tenant Isolation
- RLS + Scopes
- middleware order
- tenant-aware queues
- Redis-first quotas
- storage isolation
- auth scoping
- 404 cross-tenant

---

## 1. Auth

### Overview

Autenticación y seguridad para el área central (admin/dashboard, superadmins, sesiones globales). Soporta sesiones, 2FA y políticas de acceso centralizadas.

### Business Goal

Proteger el acceso a operaciones administrativas críticas, minimizar riesgo de compromiso y ofrecer controles operacionales (invalidación de sesiones, auditoría, políticas de concurrencia).

### Personas

- Operador central (soporte/ops)
- Global admin (gestión de tenants y facturación)
- Usuario administrativo (gestión de producto)

### User Stories (priorizadas)

- US-001: Como Operador, quiero iniciar sesión en el dashboard central con 2FA para acceder a herramientas de administración.
- US-002: Como Global Admin, quiero invalidar todas las sesiones de un usuario para forzar reautenticación.
- US-003: Como Operador, quiero ver el historial de logins y cambios de sesión para auditoría.
- US-004: Como Usuario, quiero recuperar acceso mediante flujo de 'forgot/reset password' con expiración y notificación.
- US-005: Como Admin, quiero imponer políticas de contraseñas y límites de sesiones concurrentes.

### Acceptance Criteria (ejemplos medibles)

- US-001: Login exitoso con credenciales válidas + 2FA; tasa de éxito > 99% en condiciones normales. Error por MFA faltante muestra instrucciones claras.
- US-002: Invalidación de sesiones revoca JWTs/refresh tokens y obliga a re-login en < 60s para sesiones activas.
- US-003: Historial muestra IP, user-agent, timestamp y acción; consultas por rango retornan resultados en < 200ms.
- US-004: Reset token expira en 1h; enlace enviado por email con sello; uso único; intento de reuse retorna 410.
- US-005: Política aplicada: longitud mínima 12, bloqueo tras 5 intentos en 15 minutos con CAPTCHA activable.

### Data Model (resumen)

- `central_users`: id, email, name, password_hash, is_global_admin, locked_until, created_at, updated_at
- `central_sessions`: id, user_id, token_hash, issued_at, expires_at, ip, user_agent, revoked_at
- `central_2fa`: id, user_id, method (totp, webauthn), secret, recovery_codes_hash, enrolled_at
- `central_audit_logs`: id, user_id, action, metadata(json), ip, created_at

### Events

- `CentralUserLoggedIn(user_id, session_id, ip)`
- `CentralUserLoggedOut(user_id, session_id)`
- `CentralSessionRevoked(user_id, session_id, reason)`
- `CentralPasswordResetRequested(user_id, token_id)`
- `Central2FAEnrolled(user_id, method)`

### API / Actions (endpoints y comandos)

- POST `/central/login` — credenciales -> inicia sesión (devuelve short-lived token + refresh)
- POST `/central/2fa/verify` — verificar TOTP / WebAuthn
- POST `/central/logout` — revoca sesión actual
- POST `/central/sessions/revoke` — revoca sesión(s) por id/user (admin)
- POST `/central/password/forgot` — solicita reset
- POST `/central/password/reset` — aplica nuevo password
- GET `/central/audit/logs` — consulta audit logs (roles autorizados)

### Security & Operational Controls

- Rate limit por IP y por cuenta (ej. 100 req/min IP, 10 req/min cuenta para login).
- WAF + CAPTCHA tras N fallos. Lockouts temporales con notificación.
- Fortify integration para políticas de password y validación.
- TOS: almacenar solo hashes bcrypt/argon2id; nunca tokens en texto. Usar token hashes para revocación.
- Rotación de keys para WebAuthn y renovación de recovery codes.
- Monitoreo de anomalías (picos de fallos, login desde geolocalizaciones distintas).

### UX Flow (resumen)

- Login: email+password -> solicitar 2FA -> acceso -> mostrar sesiones activas y opción "Cerrar otras sesiones".
- Forgot password: request -> email con enlace seguro -> reset -> confirmation + audit log.
- 2FA enrollment: verify device -> store credential -> show recovery codes (one-time).

### Tests (mínimos)

- Unit: validación de password, generación/verificación de 2FA, revocación de sesiones.
- Integration: flujo completo login+2FA+refresh, forgot/reset end-to-end con mail stub.
- Security tests: rate-limiting, brute-force simulation, token revocation verification.

### Metrics & SLAs

- Tiempo de login (p95) < 300ms (sin 2FA externo).
- Tiempo para revocación efectiva de sesión < 60s.
- Detección y alerta por bloqueo masivo en < 5 minutos.

### Owners / Dependencies

- Owner: TBD (assignar producto/seguridad)
- Depende de: Email gateway, Redis (rate limits/sessions), Auth service (Fortify), Telemetry/Logging.

### Edge Cases & Mitigations

- brute force: aplicar lockouts, CAPTCHA, IP throttling, alerting.
- session hijacking: revocación forzada, refresh token rotation, detect concurrent logins y alertar.
- expired reset: tokens one-time + expiración corta + mensajes claros; uso de audit logs para intentos.
- MFA recovery: flujo de recuperación con verificación SSO/ops approval y registro de autorización.

---

## 2. Provisioning

### Overview

Onboarding y creación de tenants con proceso atómico y verificaciones de dominio/subdominio.

### Business Goal

Reducir errores de provisioning y asegurar consistencia y rollback seguro.

### User Stories

- Como prospecto, quiero registrarme y que se cree el tenant automáticamente.

### Functional Requirements

- onboarding
- tenant creation
- subdomain reservation
- domain validation
- maintenance
- archival
- deletion
- atomic provisioning
- slug reservation
- blacklist
- onboarding wizard
- tenant activation
- maintenance mode
- read-only mode
- archive tenant
- hard delete
- inter-tenant migration

### Edge Cases

- duplicate slug
- partial provisioning
- rollback
- orphan storage
- failed welcome job

### Acceptance Criteria

- (TODO)

---

## 3. Billing

### Overview

Gestión de planes, suscripciones, facturación y conciliación centralizada.

### Business Goal

Soportar modelos de suscripción, cobros recurrentes y manejo de overages.

### Functional Requirements

- plans
- pricing
- subscriptions
- dunning
- taxes
- invoices
- overages
- reconciliation
- plan matrix
- coupon engine
- checkout orchestration
- subscription sync
- webhook handling
- retries
- suspension
- recovery
- consolidated invoices
- credit balance
- over-usage billing
- tax engine

### Edge Cases

- duplicated webhooks
- partial payment
- payment gateway timeout
- subscription drift
- stale cache

### Acceptance Criteria

- (TODO)

---

## 4. Support

### Overview

Herramientas para soporte central: impersonation, broadcast y overrides.

### Business Goal

Permitir asistencia segura a tenants y rastreo/auditoría de acciones de soporte.

### Functional Requirements

- impersonation
- broadcast
- tenant assistance
- overrides
- audited impersonation
- reason capture
- support sessions
- banners
- notifications
- quota override
- support notes
- tenant history

### Edge Cases

- impersonation abuse
- support escalation
- override expiration

### Acceptance Criteria

- (TODO)

---

## 5. Infrastructure

### Overview

Telemetría, colas, cache y observabilidad del contexto central.

### Business Goal

Mantener operabilidad y límites para evitar noisy neighbors y degradación.

### Functional Requirements

- telemetry
- queues
- cache
- rate limiting
- observability
- health monitor
- Horizon
- queue isolation
- cache priming
- noisy neighbor detection
- plan rate limits
- performance dashboards
- incident logs

### Edge Cases

- queue contamination
- Redis outage
- cache poisoning
- noisy tenant

### Acceptance Criteria

- (TODO)

---

## Links y Diagramas

- Diagrama maestro: see docs/\*.mermaid

## Notes

Seguir plantilla PRD por feature (Overview, User Stories, Acceptance Criteria, Data Model, Events, API, Security, Testing).
