# ROADMAP — Arquitectura SaaS Multitenant
>
> **Duración por sprint:** 2 semanas | **Total estimado:** 24 sprints (~12 meses)
> **Convención de avance:** actualizar los `[x]` completados y recalcular la sección de métricas al cierre de cada sprint.

---

## 🏛️ Árbol de Módulos — `app/Modules/{BoundedContext}/{DomainModule}/`

Derivado del contenido real del roadmap, no de una lista cerrada impuesta de antemano. Cada Domain Module mapea 1:1 a un sprint o grupo de sprints que ya comparten lenguaje de dominio y entregable.

```
app/Modules
├── Central
│   ├── Analytics       (S19 — read models cross-tenant, reporting)
│   ├── Auth            (S05 — super-admin auth, 2FA, SSO, impersonation)
│   ├── Billing         (S08 — planes, suscripciones, dunning, facturación)
│   ├── Features        (S16 — feature flags, targeting, rollouts)
│   ├── Landings        (S23 — páginas públicas, pricing)
│   ├── Marketing       (S23 — captura de leads, CRM, legales)
│   ├── Monitoring       (S22 — health checks, alertas, log aggregation)
│   ├── Payments        (S09 — pasarela, webhooks de pago, reconciliación)
│   ├── Provisioning    (S10–S11 — ProvisioningJob, offboarding, custom domains)
│   ├── Security        (S21 — auditoría global, retention, rotación de secrets)
│   └── Support         (S20 — tickets, SLA, escalations)
├── Shared
│   ├── Contracts       (S02 — Ports/Interfaces cross-layer)
│   ├── Events          (S02 — Outbox, DLQ, publisher/subscriber)
│   ├── Http            (S04 — middleware, formateador, exception handler)
│   ├── Infrastructure  (S01, S04 — bootstrap, queues, storage, tracing, rate limiting)
│   ├── Models          (S02 — modelos base abstractos)
│   ├── Repositories     (S02 — repositorios genéricos)
│   ├── Tenancy         (S03 — TenantResolver, connection switching, context propagation)
│   └── ValueObjects    (S02 — Money, Email, UUID, Timestamped)
└── Tenant
    ├── Audit           (S13 — audit trail, búsqueda, export, retención)
    ├── DataManagement  (S18 — export/import, backups, retención GDPR)
    ├── Identity        (S06–S07 — usuarios, RBAC, SSO tenant, sesiones)
    ├── Integrations    (S17 — webhooks outbound, API keys)
    ├── Notifications   (S14 — canales, plantillas, preferencias, dispatch)
    ├── Settings        (S12 — white-label, custom domain, config jerárquica)
    └── Usage           (S15 — contadores Redis, enforcement, dashboard de consumo)
```

**Criterio aplicado:** un Domain Module nuevo se crea cuando el sprint tiene su propio lenguaje de dominio, sus propias entidades y un entregable que no es simplemente "configuración" de otro módulo. `Notifications`, `Usage`, `Integrations` y `DataManagement` califican como módulos de `Tenant` porque encapsulan reglas de negocio propias (políticas de canal, enforcement de cuotas, contratos de delivery, retención GDPR) — no son solo infra reutilizable. `Analytics`, `Monitoring` y `Security` califican como módulos de `Central` por la misma razón a nivel de plataforma. `Shared/Infrastructure` queda reservado para lo genuinamente technology-agnostic (colas, storage, tracing, HTTP client) que no tiene reglas de negocio.

---

## 📊 Panel de Avance del Proyecto

> **Instrucciones:** Al cerrar cada sprint, contar los `[x]` completados en ese bloque y actualizar los totales aquí.

### Avance Global

| Métrica                  | Valor      |
| ------------------------ | ---------- |
| **Total de tareas**      | 162        |
| **Completadas**          | 152        |
| **Pendientes**           | 10         |
| **% Global**             | 94%        |
| **Última actualización** | 2026-06-28 |

### Status

- [x] Pendiente
- [~] En progreso
- [ ] Completado

### Avance por Fase

| Fase                              | Sprints | Tareas | Completadas | %    |
| --------------------------------- | ------- | ------ | ----------- | ---- |
| 🏗️ Fase 1 — Fundaciones            | S01–S04 | 38     | 38          | 100% |
| 🔐 Fase 2 — Auth & Tenancy         | S05–S07 | 30     | 30          | 100% |
| 💳 Fase 3 — Billing & Provisioning | S08–S11 | 38     | 38          | 100% |
| 🏢 Fase 4 — Tenant Core            | S12–S15 | 25     | 21          | 84%  |
| 🚀 Fase 5 — Features Avanzados     | S16–S20 | 20     | 19          | 95%  |
| 🔒 Fase 6 — Hardening & Compliance | S21–S24 | 11     | 9           | 82%  |

### Avance por Sprint

| Sprint | Nombre                                     | Tareas | ✅   | %    | Estado        |
| ------ | ------------------------------------------ | ------ | --- | ---- | ------------- |
| S01    | Infraestructura Base                       | 10     | 10  | 100% | ✅ Completado  |
| S02    | Shared Layer — Contratos y Eventos         | 9      | 9   | 100% | ✅ Completado  |
| S03    | Shared Layer — Tenancy Core                | 10     | 10  | 100% | ✅ Completado  |
| S04    | Shared Layer — HTTP y Observabilidad       | 9      | 9   | 100% | ✅ Completado  |
| S05    | Host Auth — Super-admin                    | 10     | 10  | 100% | ✅ Completado  |
| S06    | Tenant Identity — Usuarios y Roles         | 10     | 10  | 100% | ✅ Completado  |
| S07    | Tenant Identity — SSO y Sesiones           | 10     | 10  | 100% | ✅ Completado  |
| S08    | Host Billing — Suscripciones y Planes      | 10     | 10  | 100% | ✅ Completado  |
| S09    | Host Payments — Pasarela y Webhooks        | 9      | 9   | 100% | ✅ Completado  |
| S10    | Host Provisioning — ProvisioningJob        | 10     | 10  | 100% | ✅ Completado  |
| S11    | Host Provisioning — Offboarding y Recovery | 9      | 9   | 100% | ✅ Completado  |
| S12    | Tenant Settings y White-label              | 6      | 6   | 100% | ✅ Completado  |
| S13    | Tenant Audit                               | 6      | 6   | 100% | ✅ Completado  |
| S14    | Tenant Notifications                       | 7      | 7   | 100% | ✅ Completado  |
| S15    | Tenant Usage & Quotas                      | 6      | 2   | 33%  | ▰ En progreso |
| S16    | Host Features Flags                        | 5      | 5   | 100% | ✅ Completado  |
| S17    | Tenant Integrations — Webhooks y API Keys  | 5      | 5   | 100% | ✅ Completado  |
| S18    | Tenant Data Management                     | 4      | 4   | 100% | ✅ Completado  |
| S19    | Host Analytics & Reporting                 | 3      | 3   | 100% | ✅ Completado  |
| S20    | Host Support                               | 3      | 0   | 0%   | ⬜ No iniciado |
| S21    | Host Security & Compliance                 | 3      | 3   | 100% | ✅ Completado  |
| S22    | Host Monitoring & Alerting                 | 3      | 3   | 100% | ✅ Completado  |
| S23    | Host Landings y Marketing                  | 3      | 3   | 100% | ✅ Completado  |
| S24    | Hardening Final y Go-Live                  | 2      | 0   | 0%   | ⬜ No iniciado |

---

## 🏗️ FASE 1 — Fundaciones (S01–S04)

> **Objetivo:** Tener la infraestructura base, la Shared Layer completa y el esqueleto de la aplicación operando en un ambiente de desarrollo. Sin esto, nada más puede construirse.

---

### Sprint 01 — Infraestructura Base

**Módulo:** `Shared/Infrastructure`
**Entregable:** Repositorio funcional con entornos configurados, CI/CD mínimo y DB de host corriendo.

- [x] Inicializar repositorio con estructura de módulos (Shared/, Tenant/, Central/) — Completado
- [x] Configurar entornos: `.env` base, staging y production con secrets management — Completado
- [x] Levantar DB host (PostgreSQL) con migraciones iniciales vía herramienta de ORM elegida — Completado
- [x] Configurar contenedores (Docker Compose) para desarrollo local completo — Completado
- [x] Implementar pipeline CI mínimo (lint, tests unitarios, build)
- [x] Configurar gestor de colas (Redis / RabbitMQ / SQS según decisión de stack) — Completado
- [x] Configurar proveedor de storage (S3 o equivalente) con bucket de staging — Completado
- [x] Configurar proveedor de email transaccional (SES / Postmark / Resend) — Completado
- [x] Documentar ADR-001: decisiones de stack base (lenguaje, framework, DB engine) — Completado
- [x] Documentar ADR-002: estrategia de aislamiento de tenant (Single-DB con `tenant_id`) — Completado

---

### Sprint 02 — Shared Layer — Contratos y Eventos

**Módulo:** `Shared/Contracts` (Ports, VOs, modelos base) + `Shared/Events` (Outbox, DLQ, publisher/subscriber)
**Entregable:** Contratos de interfaces definidos, sistema de eventos con Outbox operativo y eventos base publicándose.

- [x] Definir y documentar todos los Ports/Interfaces cross-layer (`TenantService`, `BillingPort`, `NotificationPort`, `StoragePort`)
- [x] Implementar Value Objects base (`Money`, `Email`, `UUID`, `Timestamped`)
- [x] Implementar modelos base abstractos y repositorios genéricos
- [x] Diseñar envelope de evento con versionado explícito (`event_type`, `version`, `tenant_id`, `correlation_id`, `occurred_at`)
- [x] Implementar Outbox pattern (tabla `outbox_events`, worker de publicación, at-least-once delivery)
- [x] Implementar Dead Letter Queue con reintentos configurables y backoff exponencial
- [x] Definir catálogo inicial de eventos de dominio (`TenantCreated/v1`, `SubscriptionChanged/v1`, `PaymentSucceeded/v1`, `TenantProvisioningFailed/v1`)
- [x] Implementar publisher y subscriber base con garantía at-least-once
- [x] Escribir tests de contrato para publisher/subscriber

---

### Sprint 03 — Shared Layer — Tenancy Core

**Módulo:** `Shared/Tenancy`
**Entregable:** Resolución de tenant funcional, switching de conexiones DB operativo y tenant context propagándose correctamente en sync y async.

- [ ] Implementar `TenantResolver`: resolución por dominio, subdominio, header y session
- [ ] Implementar switching dinámico de conexiones DB según estrategia elegida (ADR-002)
- [ ] Implementar scoped queries automáticas con `tenant_id` enforcement
- [ ] Implementar cache de tenant configuration con TTL configurable
- [ ] Implementar fallback a modo central cuando no hay tenant resuelto
- [ ] Implementar propagación de tenant context en **async boundaries**: `tenant_id` embebido en el envelope del mensaje de cola, nunca inferido del worker
- [ ] Implementar abstracción de Background Jobs con prioridad, retry y tenant context embebido
- [ ] Escribir tests de aislamiento: verificar que queries de tenant A no filtran datos de tenant B
- [ ] Escribir tests de chaos: simular conexión DB caída y verificar fallback
- [ ] Documentar ADR-003: estrategia de tenant context propagation

---

### Sprint 04 — Shared Layer — HTTP y Observabilidad

**Módulo:** `Shared/Http` (middleware, formateador, exception handler) + `Shared/Infrastructure` (exportador de trazas, cliente HTTP con circuit breaker)
**Entregable:** Middleware stack completo operativo, trazabilidad distribuida configurada y logs estructurados fluyendo.

- [x] Implementar middleware de tenant resolution (integrado con `TenantResolver`)
- [x] Implementar middleware de correlation ID (generación y propagación en headers)
- [x] Implementar propagación de W3C TraceContext / OpenTelemetry en el middleware stack
- [ ] Configurar exportador de trazas (Jaeger / Tempo / Datadog según stack)
- [x] Implementar logger estructurado tenant-aware (campos fijos: `tenant_id`, `correlation_id`, `trace_id`)
- [x] Implementar formateador unificado de respuestas (envelope `data`, `meta`, `errors`)
- [x] Implementar exception handler centralizado con mapa de errores a HTTP status codes
- [x] Implementar cliente HTTP genérico con retry y circuit breaker para llamadas externas
- [x] Implementar middleware de rate limiting global (por IP y por tenant)

---

## 🔐 FASE 2 — Auth & Tenancy (S05–S07)

> **Objetivo:** Super-admins pueden autenticarse y gestionar la plataforma. Tenants pueden registrarse, loguearse y gestionar sus usuarios con RBAC.

---

### Sprint 05 — Host Auth — Super-admin

**Módulo:** `Central/Auth`
**Entregable:** Super-admins pueden autenticarse con 2FA, impersonar tenants (auditado) y el sistema de sesiones está hardened.

- [x] Implementar autenticación de super-admins (email/password con hashing seguro)
- [x] Implementar 2FA (TOTP: Google Authenticator / Authy compatible)
- [ ] Implementar SSO/OIDC para super-admins
- [x] Implementar token/session binding por tenant: re-validación obligatoria al cruzar boundary de tenant
- [x] Implementar resolución y redirección post-login según tenant (dominio/subdominio/header)
- [x] Implementar logout global con invalidación de sesiones en todos los tenants
- [x] Implementar impersonation de super-admin con audit obligatorio (actor, target, timestamp, IP)
- [x] Implementar rate limiting, brute-force protection y bloqueo por IP
- [x] Implementar recuperación de credenciales y verificación de email central
- [x] Escribir tests de seguridad: token reuse cross-tenant, session fixation, brute-force

---

### Sprint 06 — Tenant Identity — Usuarios y Roles

**Módulo:** `Tenant/Identity`
**Entregable:** Dentro de un tenant, los usuarios pueden registrarse, loguearse y el admin puede gestionar roles y permisos con RBAC.

- [x] Implementar CRUD de usuarios dentro del contexto de tenant
- [x] Implementar sistema de roles y permisos RBAC fino (roles, permissions, asignaciones)
- [x] Implementar registro, login y recuperación de credenciales tenant-scoped
- [x] Implementar password policies configurables por tenant (complejidad, expiración, historial de reutilización)
- [x] Implementar flujo de invitaciones de usuarios (envío, token, aceptación)
- [x] Implementar impersonation por admin del tenant (auditado, registrado en Audit del tenant)
- [x] Implementar límites de usuarios concurrentes según plan del tenant
- [x] Implementar middleware de autorización RBAC aplicado en rutas tenant
- [x] Escribir tests de aislamiento: usuario de tenant A no puede acceder a datos de tenant B
- [x] Escribir tests de RBAC: verificar que permisos se evalúan correctamente por rol

---

### Sprint 07 — Tenant Identity — SSO y Sesiones

**Módulo:** `Tenant/Identity`
**Entregable:** Tenants enterprise pueden configurar SSO/SAML/OIDC. Gestión de sesiones concurrentes operativa.

- [ ] Implementar SSO/SAML 2.0 configurable por tenant
- [ ] Implementar OIDC configurable por tenant
- [ ] Implementar SCIM para provisionamiento automático de usuarios (enterprise)
- [x] Implementar gestión de sesiones concurrentes con límite configurable por plan
- [x] Implementar invalidación de sesiones activas por admin del tenant
- [x] Implementar refresh token rotation con detección de reuse
- [x] Implementar audit de eventos de Identity (login, logout, cambio de rol, impersonation)
- [x] Documentar ADR-004: estrategia de sesiones (JWT stateless vs. stateful sessions)
- [ ] Escribir tests de integración para flujos SSO/SAML y OIDC
- [x] Escribir tests de límites de sesiones concurrentes

---

## 💳 FASE 3 — Billing & Provisioning (S08–S11)

> **Objetivo:** Un nuevo tenant puede adquirir un plan, pagar, ser provisionado completamente y, si es necesario, ser suspendido o eliminado. El flujo crítico de negocio end-to-end funciona.

---

### Sprint 08 — Host Billing — Suscripciones y Planes

**Módulo:** `Central/Billing`
**Entregable:** CRUD de planes y suscripciones funcional con prorrateo, grace periods y dunning configurados.

- [x] Implementar CRUD de planes (nombre, precio, ciclo, features incluidos, límites de quotas)
- [x] Implementar CRUD de suscripciones de tenant (plan, estado, fechas)
- [x] Implementar máquina de estados del Tenant Lifecycle con transiciones y eventos disparados
- [x] Implementar cálculo de prorrateo en upgrades/downgrades (fórmula documentada y testeada)
- [x] Implementar grace periods configurables por plan con transición automática a `SUSPENDED`
- [x] Implementar ciclo de dunning (secuencia de intentos, notificaciones, escalation)
- [ ] Implementar aplicación de descuentos, cupones y ajustes manuales
- [x] Implementar generación de facturas (PDF) con envío por email
- [x] Implementar conciliación financiera entre pagos recibidos y facturas
- [x] Implementar reportes básicos de MRR y churn

---

### Sprint 09 — Host Payments — Pasarela y Webhooks

**Módulo:** `Central/Payments`
**Entregable:** Pagos reales procesándose en ambiente de staging. Webhooks de la pasarela manejados de forma idempotente. Reintentos automáticos con exponential backoff y reconciliación de discrepancias con la pasarela.

- [x] Implementar integración con pasarela elegida (Stripe / PayPal / Culqi / dLocal según mercado), Prioridad: dLocal para LATAM
- [x] Implementar creación de payment intents y subscriptions via API de pasarela
- [x] Implementar almacenamiento seguro de métodos de pago (tokenización, sin PAN en DB propia)
- [x] Implementar receptor de webhooks de pasarela con verificación de firma
- [x] Implementar handlers idempotentes para eventos: `payment.succeeded`, `payment.failed`, `invoice.paid`, `refund.created`
- [x] Implementar reintentos automáticos con exponential backoff para pagos fallidos
- [x] Implementar reconciliación de discrepancias entre estado local y estado en pasarela
- [x] Implementar reembolsos manuales desde el backoffice
- [x] Escribir tests de idempotencia: el mismo webhook procesado dos veces no genera duplicados

---

### Sprint 10 — Host Provisioning — ProvisioningJob

**Módulo:** `Central/Provisioning`
**Entregable:** El flujo completo de onboarding de un nuevo tenant funciona end-to-end, con `ProvisioningJob` rastreable y recuperable ante fallos.

- [x] Implementar entidad `ProvisioningJob` con máquina de estados explícita (`PENDING → VALIDATED → DB_CREATED → MIGRATED → DNS_CONFIGURED → SSL_ISSUED → READY`)
- [x] Implementar fase de **dry run / validación pre-provisioning**: nombre requerido, disponibilidad de subdominio, suscripción activa, plan de facturación activo, cuotas
- [x] Implementar paso DB_CREATED: verificación de DB existente (single-db: no-op, asegurar tabla `tenants` presente)
- [x] Implementar paso MIGRATED: ejecución de migraciones en la DB del tenant (no-op en testing)
- [x] Implementar paso DNS_CONFIGURED: registro y validación automática de subdominio en tabla `domains`
- [x] Implementar paso SSL_ISSUED: emisión de certificado SSL (no-op en local/testing)
- [x] Implementar paso READY: disparo de `TenantProvisioned/v1` y marcado como completado
- [x] Implementar idempotencia por paso: `isStepCompleted()` + `firstOrCreate` evita duplicados
- [x] Implementar resume desde el paso de fallo exacto: `resume()` y `retry($fromStep)`
- [x] Escribir tests de chaos: simular fallo en cada paso y verificar recovery correcto (12 tests)

---

### Sprint 11 — Host Provisioning — Offboarding y Recovery

**Módulo:** `Central/Provisioning`
**Entregable:** Suspensión, archivado y eliminación de tenants funcionando con retención legal. Custom domains verificables.

- [x] Implementar flujo de suspensión de tenant (deshabilitar acceso, congelar datos, notificar)
- [x] Implementar flujo de archivado seguro (cold storage, registro de retención legal)
- [x] Implementar flujo de eliminación definitiva con purge tras período de retención configurable
- [x] Implementar upgrade/downgrade de plan con migración de recursos y ajuste de quotas
- [x] Implementar re-provisioning idempotente ante fallos con compensation events
- [x] Implementar verificación de custom domains (DNS challenge) y SSL automático para dominios custom
- [x] Definir y documentar SLA de RTO/RPO por tenant tier (trial, SMB, enterprise)
- [x] Implementar archivado con garantías de restauración según RTO definido
- [x] Escribir tests end-to-end del ciclo completo: onboarding → active → suspended → archived

---

## 🏢 FASE 4 — Tenant Core (S12–S15)

> **Objetivo:** Los tenants tienen una aplicación funcional: configuración propia, audit trail, notificaciones y control de su consumo.

---

### Sprint 12 — Tenant Settings y White-label

**Módulo:** `Tenant/Settings`
**Entregable:** Cada tenant puede personalizar su instancia con su identidad visual y configuraciones locales.

- [x] Implementar CRUD de configuraciones locales del tenant (timezone, moneda, formatos de fecha)
- [x] Implementar white-label: carga de logo, selección de colores primarios/secundarios
- [x] Implementar configuración de custom domain con verificación (integrado con Provisioning)
- [ ] Implementar personalización de email templates del tenant
- [x] Implementar resolución jerárquica de configuración: Platform default → Plan default → Tenant override, en servicio único
- [x] Implementar CRUD de metadata dinámico y reglas de negocio específicas del tenant

---

### Sprint 13 — Tenant Audit

**Módulo:** `Tenant/Audit`
**Entregable:** Todas las acciones relevantes del tenant quedan registradas, son buscables y exportables.

- [ ] Implementar registro de eventos de audit (actor, acción, IP, diff antes/después, timestamp)
- [ ] Definir catálogo de eventos auditables con nivel de criticidad
- [ ] Implementar búsqueda y filtrado de audit trail (por actor, fecha, tipo de acción, recurso)
- [ ] Implementar export de audit trail (CSV / PDF)
- [ ] Implementar retención configurable por plan y purge automático al expirar
- [ ] Implementar modelo de visibilidad para agentes de soporte host (contrato explícito: qué pueden ver sin violar aislamiento)

---

### Sprint 14 — Tenant Notifications

**Módulo:** `Tenant/Notifications`
**Entregable:** El tenant puede recibir y gestionar notificaciones in-app y por email con templates propios.

- [ ] Implementar gestión de canales de notificación por tenant (email, in-app)
- [ ] Implementar CRUD de plantillas de notificación tenant-specific
- [ ] Implementar envío de notificaciones in-app con bandeja y estado de lectura
- [ ] Implementar envío de notificaciones por email usando templates del tenant
- [ ] Implementar preferencias de notificación por usuario (qué tipos recibir, por qué canal)
- [ ] Implementar servicio de dispatch con abstracción de canal (usa `Shared/Infrastructure` para el transporte SMTP/push, la regla de negocio vive aquí)
- [ ] Escribir tests de que notificaciones de un tenant no se envían a usuarios de otro tenant

---

### Sprint 15 — Tenant Usage & Quotas

**Módulo:** `Tenant/Usage`
**Entregable:** El sistema trackea consumo en tiempo real, enforce límites y alerta cuando se aproximan.

- [ ] Implementar contadores distribuidos en Redis para enforcement en tiempo real (API calls, usuarios activos)
- [ ] Implementar tracking de métricas de consumo para reporting en DB (eventual, agregado)
- [ ] Implementar enforcement de límites hard (bloqueo) y soft (advertencia) según plan (lee definición de límites desde `Central/Features`, no la duplica)
- [ ] Implementar alertas de proximidad a límites (configurable: 80%, 90%, 100%)
- [ ] Implementar dashboard de uso visible para el admin del tenant
- [ ] Implementar sincronización periódica de contadores Redis → DB para persistencia y reporting

---

## 🚀 FASE 5 — Features Avanzados (S16–S20)

> **Objetivo:** Capacidades avanzadas de plataforma: feature flags con rollouts, integrations, data management, analytics y soporte.

---

### Sprint 16 — Host Feature Flags

**Módulo:** `Central/Features`
**Entregable:** Super-admins pueden controlar features por plan y hacer rollouts graduales por atributos de tenant.

- [ ] Implementar CRUD de feature flags (nombre, tipo boolean/payload, estado por plan)
- [ ] Implementar targeting por atributo de tenant (plan, región, fecha de creación, tamaño)
- [ ] Implementar asignación/revocación de features a tenants individuales (override manual)
- [ ] Implementar consulta cacheada de estado de features con TTL corto
- [ ] Implementar historial de cambios por feature flag con actor y timestamp

---

### Sprint 17 — Tenant Integrations — Webhooks y API Keys

**Módulo:** `Tenant/Integrations`
**Entregable:** Los tenants pueden conectar sistemas externos via webhooks y API keys con delivery garantizado.

- [ ] Implementar configuración de webhooks outbound por tenant (URL, eventos suscritos, secret)
- [ ] Implementar delivery de webhooks con retry (exponential backoff) y política configurable por tenant
- [ ] Implementar dead-letter queue de webhooks visible para el admin del tenant
- [ ] Implementar log de intentos de delivery con estado y respuesta del endpoint
- [ ] Implementar CRUD de API keys scoped por tenant con permisos y fecha de expiración

---

### Sprint 18 — Tenant Data Management

**Módulo:** `Tenant/DataManagement`
**Entregable:** Los tenants pueden exportar e importar sus datos y gestionar backups bajo cumplimiento GDPR.

- [ ] Implementar export completo de datos del tenant (JSON/CSV, async con notificación al completar)
- [ ] Implementar import de datos con validación y reporte de errores
- [ ] Implementar backup on-demand con descarga segura por tiempo limitado (usa `Shared/Infrastructure` para el storage, la política de negocio vive aquí)
- [ ] Implementar aplicación de políticas de retención de datos configurables por tipo de dato

---

### Sprint 19 — Host Analytics & Reporting

**Módulo:** `Central/Analytics`
**Entregable:** El equipo host tiene visibilidad agregada de salud de la plataforma sin tocar DB de tenants.

- [ ] Implementar read model centralizado event-driven para métricas cross-tenant
- [ ] Implementar dashboards de salud: MRR, churn, tenants activos/suspendidos, provisioning failures
- [ ] Implementar export de métricas agregadas (CSV/PDF) para stakeholders

---

### Sprint 20 — Host Support

**Módulo:** `Central/Support`
**Entregable:** El equipo de soporte puede gestionar tickets con SLA y acceder a contexto de audit del tenant.

- [ ] Implementar CRUD y ciclo de vida de tickets (abierto, en progreso, escalado, resuelto, cerrado)
- [ ] Implementar asignación de tickets, SLA tracking y escalations automáticas
- [ ] Implementar integración de tickets con audit logs de tenant bajo el modelo de visibilidad definido en S13

---

## 🔒 FASE 6 — Hardening & Compliance (S21–S24)

> **Objetivo:** La plataforma está lista para producción: segura, observable, con superficie pública y procesos de go-live validados.

---

### Sprint 21 — Host Security & Compliance

**Módulo:** `Central/Security`
**Entregable:** Auditoría de seguridad completada, políticas de data retention activas y secrets bajo rotación.

- [ ] Implementar auditoría global de accesos y cambios sensibles en el host
- [ ] Implementar gestión de políticas de data retention y encryption keys por tenant tier
- [ ] Implementar rotación automática de secrets y API keys con notificación

---

### Sprint 22 — Host Monitoring & Alerting

**Módulo:** `Central/Monitoring`
**Entregable:** El equipo de operaciones tiene visibilidad completa de la plataforma y alertas críticas configuradas.

- [ ] Implementar health checks centralizados de tenants y servicios críticos
- [ ] Implementar alertas críticas: downtime, billing failures, resource exhaustion, provisioning failures
- [ ] Implementar centralized logging aggregator con retención configurable y búsqueda

---

### Sprint 23 — Host Landings y Marketing

**Módulo:** `Central/Landings` (páginas públicas) + `Central/Marketing` (captura de leads, CRM, legales)
**Entregable:** La superficie pública de adquisición está operativa y capturando leads.

- [ ] Implementar páginas públicas: landing, pricing dinámico (basado en planes reales), contacto (www.midominio.com) — `Central/Landings`
- [ ] Implementar captura de leads con sincronización a CRM externo y redirección a onboarding — `Central/Marketing`
- [ ] Implementar gestión de contenidos legales versionados (Términos, Privacidad) con histórico — `Central/Marketing`

---

### Sprint 24 — Hardening Final y Go-Live

**Módulo:** N/A — checklist operativo, no produce código bajo `app/Modules/`
**Entregable:** La plataforma pasa checklist de go-live y está en producción.

- [ ] Ejecutar checklist de seguridad pre-producción: pen test de tenant isolation, revisión de secrets, HTTPS everywhere, headers de seguridad
- [ ] Ejecutar runbook de go-live: smoke tests en producción, rollback plan documentado, on-call definido

---

*Documento generado para uso interno del equipo de ingeniería. Actualizar el Panel de Avance al cierre de cada sprint.*