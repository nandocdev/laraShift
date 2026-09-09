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
* **Actor:** Sistema de Cobros (Dunning Engine) / Agente de Plataforma.
* **Precondición:** Agotamiento de reintentos de cobro (3 intentos fallidos en 7 días) o abuso flagrante de cuota.
* **Flujo Principal:**
1. El sistema actualiza el campo `status` del tenant a `suspended`.
2. Invalida la clave de caché `tenant_lookup:{slug}` en Redis y reescribe con estado `suspended`.
3. Termina de inmediato todas las sesiones HTTP y conexiones WebSocket abiertas asociadas a los usuarios del tenant.
4. El middleware de subdominio redirige todo tráfico a la vista pública de advertencia fiscal o técnica.


* **Postcondición:** Todo query a base de datos de dicho tenant es rechazado en capa de middleware antes de llegar al pool de conexiones.


* **UC-C-04: Purga Dura y Derecho al Olvido (GDPR/Compliance)**
* **Actor:** Job Programado / Compliance Officer.
* **Precondición:** Tenant en estado `soft_deleted` que ha superado el periodo de gracia contractual (ej. 30 días).
* **Flujo Principal:**
1. El worker localiza el `tenant_id` objetivo en una cola de baja prioridad.
2. Ejecuta un comando en lotes hacia el bucket S3/GCS para eliminar todos los objetos prefijados con `{tenant_id}/*`.
3. Elimina las métricas de consumo y rate limiting en Redis.
4. Ejecuta un borrado en cascada `DELETE FROM tenants WHERE id = ?`, eliminando físicamente registros en todas las tablas con clave foránea.
5. Escribe una entrada inmutable en `host_audit_logs` con hash SHA-256 del evento de purga.


* **Postcondición:** Cero bytes persistidos del tenant en disco o almacenamiento estructurado.



---

#### 2. Monetización, Facturación y Planes (BILLING)

* **UC-C-05: Modificación Global de Catálogo y Feature Packaging**
* **Actor:** Billing Ops / Administrador de Producto.
* **Precondición:** Nuevas capacidades desarrolladas que deben empaquetarse en un tier comercial.
* **Flujo Principal:**
1. El operador define un nuevo plan en el panel central: define límites numéricos (asientos, gigabytes, llamadas API/mes) y asigna los slugs de los módulos permitidos (ej. `["ai_assistant", "custom_roles", "sso"]`).
2. Establece reglas de precio fijo base + precio marginal por metered usage (ej. $0.002 por token o llamada extra).
3. Guarda el registro; el sistema sincroniza de forma atómica los metadatos de precios con Stripe/Paddle vía API.


* **Postcondición:** El nuevo plan queda disponible inmediatamente en el selector de planes para upgrades sin requerir despliegue de código.


* **UC-C-06: Ingesta de Consumo Agregado y Facturación Medida (Metered Billing)**
* **Actor:** Pipeline de Eventos / Worker Cron.
* **Precondición:** Módulos de aplicación emitiendo eventos de uso a Redis (`INCRBY usage:{tenant_id}:{metric}`).
* **Flujo Principal:**
1. Al cierre del ciclo horario, el worker extrae el buffer de consumo de Redis mediante una operación atómica `MULTI/EXEC`.
2. Vuelca el volumen consolidado en la tabla `usage_records` de PostgreSQL categorizado por `tenant_id` y fecha.
3. Evalúa si el volumen acumulado supera la cuota contratada en el plan activo.
4. Si hay overage, reporta el diferencial a la pasarela de pagos para el cálculo de la factura fin de mes.


* **Postcondición:** Redis queda limpio para la siguiente ventana y PostgreSQL preserva el histórico de consumo para auditoría del cliente.


* **UC-C-07: Orquestación del Motor de Dunning y Periodos de Gracia**
* **Actor:** Webhook Handler de Pasarela de Pagos.
* **Precondición:** Recepción de un evento `invoice.payment_failed`.
* **Flujo Principal:**
1. El sistema verifica la firma criptográfica del webhook e identifica el `tenant_id`.
2. Cambia el estado de facturación a `past_due` y calcula una ventana de gracia de 5 días.
3. Programa reintentos inteligentes de cobro en 24, 72 y 120 horas.
4. Envía un correo con enlace transaccional para actualización urgente de tarjeta.
5. Inyecta un banner de advertencia severa en el layout del workspace del tenant.


* **Postcondición:** El workspace permanece operativo, pero con bandera de advertencia hasta la expiración de la gracia.



---

#### 3. Soporte e Impersonación Cero-Confianza (GOVERNANCE & SUPPORT)

* **UC-C-08: Inicio de Sesión de Soporte Asistido (Login As Tenant)**
* **Actor:** Support Agent / Super Admin.
* **Precondición:** Solicitud abierta por el cliente o error crítico no reproducible; el agente cuenta con el rol de soporte asignado.
* **Flujo Principal:**
1. El agente selecciona el tenant en el panel Host y solicita una sesión de soporte con justificación textual y ticket ID obligatorio.
2. Si la cuenta requiere doble custodia (Enterprise), el sistema envía una alerta push/email al Owner del tenant exigiendo aprobación explícita.
3. Tras la aprobación, el sistema emite un token de sesión especial firmado con clave privada, con un TTL estricto de 30 minutos sin posibilidad de renovación.
4. Registra el evento en `host_audit_logs` con el ID del agente, IP y hora.
5. Abre el dashboard del tenant en una pestaña aislada forzando la visualización de un banner persistente: *"Sesión de Soporte Activa por [Agente]"*.


* **Postcondición:** Todas las acciones ejecutadas por el agente quedan taggeadas con `impersonated_by: agent_id` en los logs del tenant.


* **UC-C-09: Terminación Forzada de Impersonación y Auditoría Forense**
* **Actor:** Owner del Tenant / Sistema (Expiración de TTL) / SecOps.
* **Precondición:** Sesión de soporte en curso.
* **Flujo Principal:**
1. El Owner del tenant presiona el botón "Terminar Acceso" en su banner de seguridad (o el contador del token llega a cero).
2. El backend revoca inmediatamente el identificador de sesión en la lista de tokens válidos en Redis.
3. El agente de soporte es expulsado automáticamente en su siguiente request con un código HTTP 401.
4. El sistema compila un reporte consolidado con cada endpoint y mutación ejecutada durante la sesión y lo remite por correo al Owner.


* **Postcondición:** Cero retención de privilegios y registro forense inmutable almacenado.



---

#### 4. Resiliencia, Tráfico y Límites (OPERATIONS & SRE)

* **UC-C-10: Mitigación de Vecinos Ruidosos (Adaptive Throttling)**
* **Actor:** Middleware de Gateway / Redis Token Bucket.
* **Precondición:** Un tenant dispara scripts masivos contra la API o satura el pool de queries complejas.
* **Flujo Principal:**
1. El algoritmo de bucket detecta que el `tenant_id` ha consumido más de 120 requests/segundo sostenidos (sobrepasando su límite contratado de 30 req/s).
2. El gateway responde de forma autónoma con un error HTTP 429 (`Too Many Requests`) incluyendo el header `Retry-After: 30`.
3. Si la saturación es por queries a base de datos, el sistema degrada dinámicamente la prioridad de sus workers en segundo plano a una cola `low-priority`.


* **Postcondición:** La latencia p99 del resto de tenants en la infraestructura compartida no se desvía más del 2%.


* **UC-C-11: Despliegue Canario y Activación Selectiva de Feature Flags**
* **Actor:** Administrador de Plataforma / Release Manager.
* **Precondición:** Módulo en versión beta que no debe exponerse a la totalidad de los tenants.
* **Flujo Principal:**
1. El operador define un feature flag en el Host (`new_analytics_engine`).
2. Asigna la regla de evaluación: activado para tenants del plan Enterprise Y pertenecientes a la lista explícita de `beta_testers`.
3. El middleware del tenant evalúa el flag consultando Redis (`SISMEMBER feature_flags:new_analytics_engine tenant_id`).


* **Postcondición:** Solo los clientes seleccionados visualizan y acceden a las nuevas rutas y APIs sin desplegar ramas de código divergentes.

* **UC-C-12: Orquestación del Ciclo de Vida del Tenant**
* **Actor:** Sistema (Triggers automáticos) / Super Admin.
* **Alcance:** Ejecución de la máquina de estados descrita (`pending` hasta `purged`). Incluye validación de bloqueos administrativos, diferenciación del motivo de suspensión (`fraud`, `billing`, `abuse`, `maintenance`) y ejecución de rollbacks/compensaciones si el provisioning falla a mitad de camino.
* **Aislamiento/Gobernanza:** Cada cambio de estado invalida inmediatamente el caché de enrutamiento del Edge (`tenant_lookup:{slug}`) y expulsa sesiones en Redis.


* **UC-C-13: Ciclo de Vida de Suscripciones y Cambios de Plan**
* **Actor:** Billing Ops / Sistema de Suscripciones.
* **Alcance:** Gestión de transiciones comerciales complejas: downgrades con validación de cuotas excedidas (ej. no permitir bajar de tier si el tenant tiene 15 usuarios y el nuevo plan solo admite 5), cancelaciones programadas al fin de ciclo (*cancel at period end*), cambios de ciclo mensual a anual con liquidación de crédito, y reactivaciones de suscripciones pausadas.
* **Efectos:** Recalcula y aprovisiona límites en Redis en tiempo real; programa webhooks hacia la pasarela externa.


* **UC-C-14: Conciliación Financiera, Disputas y Fallos de Pago**
* **Actor:** Billing Ops / Webhook Engine.
* **Alcance:** Control integral de eventos transaccionales fuera del flujo nominal: captura de webhooks de fallos de cobro, conciliación de facturas entre pasarela y base de datos local, gestión de disputas (*chargebacks*), emisión de reembolsos (*refunds*) con reducción proporcional de límites y detección de discrepancias contables.


* **UC-C-15: Incidentes, Mantenimiento Global y Resiliencia**
* **Actor:** SRE / Incident Commander.
* **Alcance:** Puesta en marcha de modo de degradación controlada o mantenimiento selectivo (por tenant, cluster o plataforma completa). Bloquea escrituras manteniendo lecturas mediante variables de sesión transaccionales, despacha advertencias masivas o localizadas a los clientes afectados y ejecuta protocolos de failover o recuperación de datos tras una caída crítica.


* **UC-C-16: Observabilidad, Telemetría Cruzada y Auditoría Forense**
* **Actor:** SRE / Agente de Soporte Nivel 3.
* **Alcance:** Ingesta de métricas de salud por tenant (p95/p99 de queries, concurrencia de conexiones de BD, uso de storage), agregación de logs con correlación entre peticiones del host y del tenant (`request_id` + `tenant_id`), y registro inmutable de acciones administrativas con fines de certificación (SOC2 / ISO 27001).

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
